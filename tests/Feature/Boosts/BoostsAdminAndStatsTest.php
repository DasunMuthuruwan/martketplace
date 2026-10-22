<?php

namespace Tests\Feature\Boosts;

use App\Authorization\Permissions;
use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\BoostsAdmin;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\ListingBoost;
use App\Models\User;
use App\Services\Boosts\BoostManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** B3 (ADR-026): staff grant and end boosts; sellers see their boost and views per day. */
class BoostsAdminAndStatsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $admin;

    private Listing $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->staff(StaffRole::Admin);
        $lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $cars = Category::factory()->create(['parent_id' => Category::factory()->create(['slug' => 'vehicles'])->id, 'slug' => 'cars']);
        $this->ad = Listing::factory()->inCountry($lk)->create(['category_id' => $cars->id, 'title' => 'Toyota Aqua 2015', 'published_at' => now()->subDays(20), 'expires_at' => now()->addDays(40)]);
    }

    // ---- Admin screen ---------------------------------------------------------------------------

    public function test_owner_and_admin_manage_boosts_moderators_do_not(): void
    {
        $this->assertTrue($this->admin->can(Permissions::BOOSTS_MANAGE));
        $this->assertTrue($this->staff(StaffRole::Owner)->can(Permissions::BOOSTS_MANAGE));
        $moderator = $this->staff(StaffRole::Moderator, twoFactor: false);
        $this->assertFalse($moderator->can(Permissions::BOOSTS_MANAGE));

        $this->actingAs($this->admin)->get(route('admin.boosts'))->assertOk()->assertSee(__('admin.boosts.grant_title'));
        $this->actingAs($moderator)->get(route('admin.boosts'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.dashboard'))->assertDontSee(route('admin.boosts'));
    }

    public function test_grant_a_top_ad_by_number_or_address_and_it_is_audited(): void
    {
        $this->freezeSecond();
        Livewire::actingAs($this->admin)->test(BoostsAdmin::class)
            ->set('ad', (string) $this->ad->id)->set('type', 'top')->set('days', 5)
            ->call('grant')->assertHasNoErrors();
        Livewire::actingAs($this->admin)->test(BoostsAdmin::class)
            ->set('ad', $this->ad->publicUrl())->set('type', 'bump')
            ->call('grant')->assertHasNoErrors();

        $this->assertSame(['top', 'bump'], $this->ad->boosts()->orderBy('id')->get()->map(fn ($b) => $b->type->value)->all());
        $this->assertTrue($this->ad->boosts()->where('type', 'top')->sole()->ends_at->equalTo(now()->addDays(5)->startOfSecond()));
        $this->assertSame(2, AuditLog::where('action', 'boost.granted')->where('actor_id', $this->admin->id)->count());
    }

    public function test_grants_are_refused_for_unknown_own_or_not_live_ads(): void
    {
        $test = Livewire::actingAs($this->admin)->test(BoostsAdmin::class)->set('type', 'top')->set('days', 7);

        $test->set('ad', '999999')->call('grant')->assertHasErrors(['ad']);
        $test->set('ad', 'not an ad')->call('grant')->assertHasErrors(['ad']);

        $own = Listing::factory()->for($this->admin)->create(['country_id' => $this->ad->country_id, 'currency' => $this->ad->currency, 'category_id' => $this->ad->category_id]);
        $test->set('ad', (string) $own->id)->call('grant')->assertHasErrors(['ad']);

        $this->ad->forceFill(['status' => ListingStatus::Sold])->save();
        $test->set('ad', (string) $this->ad->id)->call('grant')->assertHasErrors(['ad']);

        $this->assertSame(0, ListingBoost::count());
    }

    public function test_days_past_the_ads_expiry_are_refused_on_the_days_field(): void
    {
        Livewire::actingAs($this->admin)->test(BoostsAdmin::class)
            ->set('ad', (string) $this->ad->id)->set('type', 'top')->set('days', 60)
            ->call('grant')->assertHasErrors(['days']);
    }

    public function test_list_by_state_and_end_a_boost_early(): void
    {
        $running = app(BoostManager::class)->top($this->ad, 7);
        $upcoming = app(BoostManager::class)->top($this->ad, 7);

        $test = Livewire::actingAs($this->admin)->test(BoostsAdmin::class);
        $test->assertSee('Toyota Aqua 2015');
        $test->set('state', 'upcoming')->assertSee($upcoming->starts_at->toFormattedDateString());

        $test->set('state', 'running')->call('end', $running->id);

        $this->assertNotNull($running->fresh()->ended_at);
        $this->assertNull($upcoming->fresh()->ended_at);
        $this->assertSame(1, AuditLog::where('action', 'boost.ended')->count());
        $test->set('state', 'ended')->assertSee(__('admin.boosts.ended_on', ['date' => now()->toFormattedDateString()]));
    }

    // ---- Seller view ----------------------------------------------------------------------------

    public function test_my_ads_shows_the_top_ad_and_a_statistics_link(): void
    {
        $boost = app(BoostManager::class)->top($this->ad, 7);

        $this->actingAs($this->ad->user)->get(route('account.listings'))->assertOk()
            ->assertSee(__('account.listings.top_until', ['date' => $boost->ends_at->toFormattedDateString()]))
            ->assertSee(route('account.listings.stats', $this->ad));
    }

    public function test_statistics_show_views_per_day_top_days_and_the_comparison(): void
    {
        foreach (range(10, 4) as $daysAgo) { // a week before the Top ad: 2 views a day
            DB::table('listing_daily_views')->insert(['listing_id' => $this->ad->id, 'date' => now()->subDays($daysAgo)->toDateString(), 'views' => 2]);
        }
        $this->travelTo(now()->subDays(3));
        app(BoostManager::class)->top($this->ad, 7);
        $this->travelBack();
        foreach (range(3, 0) as $daysAgo) { // with the Top ad: 9 views a day
            DB::table('listing_daily_views')->insert(['listing_id' => $this->ad->id, 'date' => now()->subDays($daysAgo)->toDateString(), 'views' => 9]);
        }
        $this->ad->forceFill(['view_count' => 50])->save();

        $html = $this->actingAs($this->ad->user)->get(route('account.listings.stats', $this->ad))->assertOk()
            ->assertSee('Toyota Aqua 2015')
            ->assertSee('2 → 9')
            ->getContent();

        $this->assertSame(4, substr_count($html, 'data-top-day'), 'four days with the Top ad');
        $this->assertStringContainsString('<progress', $html);
        $this->assertStringContainsString('>50<', str_replace(' ', '', $html));
    }

    public function test_only_the_owner_sees_the_statistics(): void
    {
        $this->actingAs(User::factory()->create())->get(route('account.listings.stats', $this->ad))->assertForbidden();
        $this->actingAs($this->admin)->get(route('account.listings.stats', $this->ad))->assertForbidden();
    }
}
