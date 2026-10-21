<?php

namespace Tests\Feature\Boosts;

use App\Enums\BoostType;
use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\ListingBoost;
use App\Services\Boosts\BoostManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** B2 (ADR-026, proposal §4): Bump, Top ad and the top-ad block's trust rules. */
class TopAdsAndBumpsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private Country $lk;

    private Category $cars;

    private Category $vans;

    private BoostManager $boosts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $vehicles = Category::factory()->create(['slug' => 'vehicles']);
        $this->cars = Category::factory()->create(['parent_id' => $vehicles->id, 'slug' => 'cars']);
        $this->vans = Category::factory()->create(['parent_id' => $vehicles->id, 'slug' => 'vans']);
        $this->boosts = app(BoostManager::class);
    }

    private function ad(string $title, array $attributes = []): Listing
    {
        return Listing::factory()->inCountry($attributes['country'] ?? $this->lk)
            ->create(array_diff_key($attributes, ['country' => 1]) + ['category_id' => $this->cars->id, 'title' => $title, 'published_at' => now()->subDays(3)]);
    }

    private function top(Listing $ad, int $days = 7): ListingBoost
    {
        return $this->boosts->top($ad, $days);
    }

    /** @return list<string> titles in the top-ad block, in order */
    private function block(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        if (! preg_match('/<section[^>]*data-top-ads[^>]*>(.*?)<\/section>/s', $html, $m)) {
            return [];
        }
        preg_match_all('/<h3[^>]*>\s*<a[^>]*>([^<]+)<\/a>/', $m[1], $titles);

        return array_map('trim', $titles[1]);
    }

    // ---- Bump ---------------------------------------------------------------------------------

    public function test_a_bump_moves_the_ad_to_the_top_without_touching_its_edit_or_publication_time(): void
    {
        $ad = $this->ad('Bumped car', ['updated_at' => now()->subWeek()]);
        $this->ad('Newer car', ['published_at' => now()->subHour()]);
        $before = $ad->fresh();

        $this->boosts->bump($ad);

        $after = $ad->fresh();
        $this->assertTrue($after->sorted_at->equalTo(now()->startOfSecond()));
        $this->assertTrue($after->updated_at->equalTo($before->updated_at));
        $this->assertTrue($after->published_at->equalTo($before->published_at), 'cards keep the honest publication time');
        $html = $this->get('/lk/ads')->getContent();
        $this->assertLessThan(strpos($html, 'Newer car'), strpos($html, 'Bumped car'));
        $this->assertSame(BoostType::Bump, $ad->boosts()->sole()->type);
        $this->assertNull($ad->boosts()->sole()->ends_at);
    }

    public function test_only_live_ads_can_be_boosted(): void
    {
        foreach ([['status' => ListingStatus::PendingReview, 'published_at' => null], ['status' => ListingStatus::Sold], ['expires_at' => now()->subDay()]] as $state) {
            $ad = $this->ad('Not live', $state);
            foreach ([fn () => $this->boosts->bump($ad), fn () => $this->top($ad)] as $boost) {
                try {
                    $boost();
                    $this->fail('A boost was started on an ad that is not live');
                } catch (ValidationException $e) {
                    $this->assertSame(__('boosts.not_live'), $e->errors()['listing'][0]);
                }
            }
        }
        $this->assertSame(0, ListingBoost::count());
    }

    // ---- Top ad -------------------------------------------------------------------------------

    public function test_a_second_top_ad_queues_after_the_first_and_never_runs_past_expiry(): void
    {
        $ad = $this->ad('Car', ['expires_at' => now()->addDays(20)]);

        $first = $this->top($ad, 7);
        $second = $this->top($ad, 7);

        $this->assertTrue($second->starts_at->equalTo($first->ends_at));
        $this->expectException(ValidationException::class);
        $this->top($ad, 7); // would end on day 21, after the ad expires
    }

    public function test_top_ad_days_are_limited(): void
    {
        $ad = $this->ad('Car');
        foreach ([0, BoostManager::MAX_DAYS + 1] as $days) {
            try {
                $this->top($ad, $days);
                $this->fail("{$days} days accepted");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('days', $e->errors());
            }
        }
    }

    public function test_staff_grants_and_early_ends_are_audited(): void
    {
        $this->seedRoles();
        $admin = $this->staff(StaffRole::Admin);
        $ad = $this->ad('Car');

        $boost = $this->boosts->top($ad, 3, $admin);
        $this->boosts->end($boost, $admin, 'Test');

        $this->assertSame(['boost.granted', 'boost.ended'], AuditLog::orderBy('id')->pluck('action')->all());
        $this->assertSame($admin->id, $boost->fresh()->granted_by);
        $this->assertNotNull($boost->fresh()->ended_at);
    }

    // ---- The top-ad block ---------------------------------------------------------------------

    public function test_top_ads_are_labelled_limited_and_keep_their_normal_place(): void
    {
        foreach (['Top one', 'Top two', 'Top three'] as $title) {
            $this->top($this->ad($title));
        }
        $this->ad('Plain car');

        $response = $this->get('/lk/ads/vehicles/cars')->assertOk();

        $this->assertCount(2, $this->block('/lk/ads/vehicles/cars'), 'at most two per page (D5)');
        $response->assertSee(__('boosts.top.heading'))->assertSee(__('boosts.top.badge'))->assertSee(__('boosts.top.why'));
        $html = $response->getContent();
        $results = substr($html, (int) strpos($html, '</section>', (int) strpos($html, 'data-top-ads')));
        foreach (['Top one', 'Top two', 'Top three', 'Plain car'] as $title) {
            $this->assertStringContainsString($title, $results, "{$title} keeps its place in the results");
        }
    }

    public function test_top_ads_rotate_so_every_seller_is_seen(): void
    {
        foreach (['First', 'Second', 'Third'] as $title) {
            $this->top($this->ad($title));
        }

        $this->assertSame(['First', 'Second'], $this->block('/lk/ads'));
        $this->travel(1)->minutes();
        $this->assertSame(['Third', 'First'], $this->block('/lk/ads'));
        $this->travel(1)->minutes();
        $this->assertSame(['Second', 'Third'], $this->block('/lk/ads'));
    }

    public function test_top_ads_only_appear_where_they_are_relevant(): void
    {
        $this->top($this->ad('Toyota Aqua hybrid'));
        $this->top($this->ad('Nissan Caravan', ['category_id' => $this->vans->id]));
        $this->top($this->ad('Indian car', ['country' => Country::factory()->create(['iso2' => 'IN', 'is_active' => true])]));

        $this->assertSame(['Toyota Aqua hybrid'], $this->block('/lk/ads/vehicles/cars'), 'own category only');
        $this->assertEqualsCanonicalizing(['Toyota Aqua hybrid', 'Nissan Caravan'], $this->block('/lk/ads/vehicles'), 'parent category includes both');
        $this->assertSame([], $this->block('/lk/ads?sort=price_asc'), 'only with the default order');
        $this->assertSame([], $this->block('/ads'), 'not on the all-countries search');
        $this->assertSame(['Indian car'], $this->block('/in/ads'), 'own country only');
    }

    public function test_finished_ended_or_hidden_boosts_are_not_shown(): void
    {
        $this->travelTo(now()->subDays(10));
        $this->top($this->ad('Finished', ['published_at' => now()->subDay()]), 3);
        $this->travelBack();
        $this->boosts->end($this->top($this->ad('Ended early')));
        $sold = $this->ad('Sold car');
        $this->top($sold);
        $sold->forceFill(['status' => ListingStatus::Sold])->save();

        $this->assertSame([], $this->block('/lk/ads'));
    }

    public function test_the_slot_count_is_an_owner_setting(): void
    {
        $this->top($this->ad('Top one'));

        config(['marketplace.boosts.top_slots' => 0]);

        $this->assertSame([], $this->block('/lk/ads'));
    }

    // ---- Ending with the ad --------------------------------------------------------------------

    public function test_boosts_end_with_their_ad_and_do_not_come_back(): void
    {
        $sold = $this->ad('Sold');
        $kept = $this->ad('Still live');
        $this->top($sold);
        $this->top($kept);
        $sold->forceFill(['status' => ListingStatus::Sold])->save();

        $this->artisan('marketplace:end-boosts')->expectsOutputToContain('Boosts ended: 1')->assertSuccessful();

        $this->assertNotNull($sold->boosts()->sole()->ended_at);
        $this->assertNull($kept->boosts()->sole()->ended_at);
        $sold->forceFill(['status' => ListingStatus::Active])->save();
        $this->assertSame(['Still live'], $this->block('/lk/ads'), 'the ended boost stays ended');
    }
}
