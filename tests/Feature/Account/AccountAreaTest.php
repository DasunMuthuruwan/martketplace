<?php

namespace Tests\Feature\Account;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F3.2: account overview, My ads (tabs and actions), saved ads (routes doc §3, layout doc §3.5). */
class AccountAreaTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Country $country;

    private Category $cars;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['name' => 'Kamal']);
        $this->country = Country::factory()->create();
        $root = Category::factory()->create();
        $this->cars = Category::factory()->create(['parent_id' => $root->id]);
    }

    private function ad(array $attributes = [], ?User $owner = null): Listing
    {
        return Listing::factory()->inCountry($this->country)->for($owner ?? $this->seller)->create($attributes + ['category_id' => $this->cars->id]);
    }

    public function test_pages_need_login(): void
    {
        foreach (['account.overview', 'account.listings', 'account.favourites'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_overview_shows_counts_expiring_ads_and_shortcuts(): void
    {
        $this->ad(['title' => 'Live car', 'expires_at' => now()->addDays(40)]);
        $this->ad(['title' => 'Soon car', 'expires_at' => now()->addDays(2)]);
        $this->ad(['status' => ListingStatus::Rejected, 'moderation_note' => 'Blurry photos']);

        $html = $this->actingAs($this->seller)->get(route('account.overview'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString(__('account.overview.title', ['name' => 'Kamal']), $html);
        $this->assertStringContainsString('Soon car', $html);
        $this->assertStringNotContainsString('Live car', $html);
        $this->assertStringContainsString(__('account.overview.attention', ['rejected' => 1, 'expired' => 0]), $html);
    }

    public function test_tabs_split_ads_by_status_with_counts(): void
    {
        $this->ad(['title' => 'Live one']);
        $this->ad(['title' => 'Past date', 'expires_at' => now()->subDay()]);
        $this->ad(['title' => 'Waiting', 'status' => ListingStatus::PendingReview]);
        $this->ad(['title' => 'Fix me', 'status' => ListingStatus::Rejected, 'moderation_note' => 'Blurry photos']);
        $this->ad(['title' => 'Someone else'], User::factory()->create());

        $active = $this->actingAs($this->seller)->get(route('account.listings'))->getContent();
        $this->assertStringContainsString('Live one', $active);
        $this->assertStringNotContainsString('Past date', $active);
        $this->assertStringNotContainsString('Someone else', $active);

        $expired = $this->get(route('account.listings', ['status' => 'expired']))->getContent();
        $this->assertStringContainsString('Past date', $expired);
        $this->assertStringContainsString(__('account.listings.renew'), $expired); // just expired: renewable

        $rejected = $this->get(route('account.listings', ['status' => 'rejected']))->getContent();
        $this->assertStringContainsString('Reason: Blurry photos', $rejected);

        $this->assertStringContainsString('aria-current="page"', $rejected);
    }

    public function test_mark_sold_renew_and_delete(): void
    {
        $live = $this->ad(['expires_at' => now()->addDays(40)]);
        $this->actingAs($this->seller)->post(route('account.listings.sold', $live))
            ->assertRedirect(route('account.listings', ['status' => 'sold']))->assertSessionHas('status', __('account.listings.marked_sold'));
        $this->assertSame(ListingStatus::Sold, $live->fresh()->status);

        $expired = $this->ad(['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);
        $this->post(route('account.listings.renew', $expired))->assertRedirect(route('account.listings', ['status' => 'active']));
        $this->assertSame(ListingStatus::Active, $expired->fresh()->status);

        $tooEarly = $this->ad(['expires_at' => now()->addDays(40)]);
        $this->from(route('account.listings'))->post(route('account.listings.renew', $tooEarly))
            ->assertRedirect(route('account.listings'))->assertSessionHas('error');

        $this->delete(route('account.listings.destroy', $tooEarly))->assertSessionHas('status', __('account.listings.deleted'));
        $this->assertSoftDeleted($tooEarly);
    }

    public function test_actions_on_someone_elses_ad_are_refused(): void
    {
        $other = $this->ad([], User::factory()->create());

        $this->actingAs($this->seller)->post(route('account.listings.sold', $other))->assertForbidden();
        $this->delete(route('account.listings.destroy', $other))->assertForbidden();
        $this->assertNotSoftDeleted($other);
    }

    public function test_saved_ads_show_live_ones_as_cards_and_gone_ones_as_unavailable(): void
    {
        $live = $this->ad(['title' => 'Saved live']);
        $gone = $this->ad(['title' => 'Saved gone', 'status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);
        $buyer = User::factory()->create();
        $buyer->favourites()->attach([$live->id, $gone->id]);

        $html = $this->actingAs($buyer)->get(route('account.favourites'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.$live->publicUrl().'"', $html);
        $this->assertStringContainsString('Saved gone', $html);
        $this->assertStringContainsString(__('account.favourites.unavailable'), $html);
        $this->assertStringNotContainsString('href="'.$gone->publicUrl().'"', $html);
        $this->assertStringContainsString('data-favourite-url="'.route('listings.favourite', $live).'"', $html);
    }

    public function test_header_and_bottom_nav_now_link_my_ads_and_saved(): void
    {
        $html = $this->actingAs($this->seller)->get('/countries')->getContent();

        $this->assertStringContainsString('href="'.route('account.listings').'"', $html);
        $this->assertStringContainsString('href="'.route('account.favourites').'"', $html);
        $this->assertStringContainsString('href="'.route('account.overview').'"', $html);
    }

    public function test_settings_pages_use_the_account_layout(): void
    {
        foreach (['account.settings.profile', 'account.settings.security'] as $route) {
            $html = $this->actingAs($this->seller)->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<h1'));
            $this->assertStringContainsString('aria-label="'.__('account.nav.label').'"', $html);
        }
    }
}
