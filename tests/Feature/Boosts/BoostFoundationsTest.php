<?php

namespace Tests\Feature\Boosts;

use App\Enums\BoostType;
use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\ListingBoost;
use App\Models\User;
use App\Services\Listings\ListingPublisher;
use App\Services\Listings\ListingViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** B1 (ADR-026): the newest order, boost records and daily view counts. */
class BoostFoundationsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';

    private Country $country;

    private Category $cars;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $this->cars = Category::factory()->create(['parent_id' => Category::factory()->create(['slug' => 'vehicles'])->id, 'slug' => 'cars']);
    }

    private function ad(array $attributes = []): Listing
    {
        return Listing::factory()->inCountry($this->country)->create(['category_id' => $this->cars->id] + $attributes);
    }

    // ---- The newest order ---------------------------------------------------------------------

    public function test_a_published_ad_takes_its_publication_time_as_sort_time(): void
    {
        $ad = $this->ad(['published_at' => now()->subDays(3)]);

        $this->assertTrue($ad->sorted_at->equalTo($ad->published_at));
    }

    public function test_publishing_moves_the_ad_to_the_top_but_renewal_does_not(): void
    {
        $ad = $this->ad(['status' => ListingStatus::PendingReview, 'published_at' => null, 'sorted_at' => null]);
        $this->travelTo(now()->addHour());
        app(ListingPublisher::class)->publish($ad);
        $this->assertTrue($ad->fresh()->sorted_at->equalTo(now()->startOfSecond()));

        $ad->forceFill(['expires_at' => now()->addDays(2)])->save();
        $before = $ad->fresh()->sorted_at;
        $this->travelTo(now()->addDay());
        $this->actingAs($ad->user)->post(route('account.listings.renew', $ad))->assertSessionHasNoErrors();

        $this->assertTrue($ad->fresh()->sorted_at->equalTo($before), 'renewing is not free bumping');
    }

    public function test_results_follow_the_sort_time_not_the_publication_time(): void
    {
        $old = $this->ad(['title' => 'Older ad moved up', 'published_at' => now()->subDays(5)]);
        $this->ad(['title' => 'Newer ad', 'published_at' => now()->subDay()]);
        $old->forceFill(['sorted_at' => now()])->save(); // what a Bump will do (B2)

        foreach (['/lk/ads', '/lk/ads/vehicles/cars', '/lk'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertLessThan(strpos($html, 'Newer ad'), strpos($html, 'Older ad moved up'), $url);
        }
    }

    // ---- Boost records ------------------------------------------------------------------------

    public function test_running_boosts(): void
    {
        $ad = $this->ad();
        $make = fn (array $a) => tap(new ListingBoost(['type' => BoostType::Top] + $a), fn ($b) => $b->listing()->associate($ad)->save());
        $running = $make(['starts_at' => now()->subDay(), 'ends_at' => now()->addDays(2)]);
        $make(['starts_at' => now()->subDays(9), 'ends_at' => now()->subDays(2)]);
        $make(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(5)]);
        $stopped = $make(['starts_at' => now()->subDay(), 'ends_at' => now()->addDays(2)]);
        $stopped->forceFill(['ended_at' => now()])->save();

        $this->assertSame([$running->id], ListingBoost::running()->pluck('id')->all());
        $this->assertTrue($running->isRunning());
        $this->assertFalse($stopped->fresh()->isRunning());
        $this->assertSame(4, $ad->boosts()->count());
        $this->assertFalse(BoostType::Bump->hasDuration());
        $this->assertTrue(BoostType::Top->hasDuration());
    }

    public function test_boosts_and_views_go_with_the_ad(): void
    {
        $ad = $this->ad();
        (new ListingBoost(['type' => BoostType::Bump, 'starts_at' => now()]))->listing()->associate($ad)->save();
        DB::table('listing_daily_views')->insert(['listing_id' => $ad->id, 'date' => now()->toDateString(), 'views' => 3]);

        $ad->forceDelete();

        $this->assertSame(0, ListingBoost::count());
        $this->assertSame(0, DB::table('listing_daily_views')->count());
    }

    // ---- Views ----------------------------------------------------------------------------------

    private function visit(Listing $ad, ?User $as = null, string $agent = self::BROWSER): void
    {
        ($as ? $this->actingAs($as) : $this)->withHeader('User-Agent', $agent)->get($ad->publicUrl())->assertOk();
    }

    private function views(Listing $ad): int
    {
        return (int) DB::table('listing_daily_views')->where('listing_id', $ad->id)->sum('views');
    }

    public function test_a_visitor_counts_once_per_ad_and_day(): void
    {
        $ad = $this->ad();
        $other = $this->ad();

        $this->visit($ad);
        $this->visit($ad);
        $this->visit($other);
        $this->assertSame(1, $this->views($ad));
        $this->assertSame(1, $this->views($other));
        $this->assertSame(1, $ad->fresh()->view_count);

        $this->travelTo(now()->addDay());
        $this->visit($ad);
        $this->assertSame(2, $this->views($ad));
        $this->assertSame(2, DB::table('listing_daily_views')->where('listing_id', $ad->id)->count(), 'one row per day');
    }

    public function test_another_visitor_counts_separately(): void
    {
        $ad = $this->ad();
        $this->visit($ad);

        $this->flushSession();
        $this->visit($ad);

        $this->assertSame(2, $this->views($ad));
    }

    public function test_the_owner_staff_and_bots_are_not_counted(): void
    {
        $this->seedRoles();
        $ad = $this->ad();

        $this->visit($ad, $ad->user);
        $this->visit($ad, $this->staff(StaffRole::Moderator, twoFactor: false));
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->visit($ad, null, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->visit($ad, null, '');

        $this->assertSame(0, $this->views($ad));
        $this->assertSame(0, $ad->fresh()->view_count);
    }

    public function test_counting_a_view_does_not_change_the_edit_time(): void
    {
        $ad = $this->ad(['updated_at' => now()->subWeek()]);
        $before = $ad->fresh()->updated_at;

        $this->visit($ad);

        $this->assertTrue($ad->fresh()->updated_at->equalTo($before));
    }

    public function test_daily_series_is_zero_filled_and_oldest_first(): void
    {
        $ad = $this->ad();
        DB::table('listing_daily_views')->insert([
            ['listing_id' => $ad->id, 'date' => now()->subDays(2)->toDateString(), 'views' => 4],
            ['listing_id' => $ad->id, 'date' => now()->toDateString(), 'views' => 7],
        ]);

        $series = app(ListingViews::class)->daily($ad, 3);

        $this->assertSame([now()->subDays(2)->toDateString() => 4, now()->subDay()->toDateString() => 0, now()->toDateString() => 7], $series);
    }
}
