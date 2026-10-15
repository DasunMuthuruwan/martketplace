<?php

namespace Tests\Feature\Listings;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\LocationRequest;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F4.1 part 1: wizard shell, category and location steps (layout doc §3.4, global UX §2, §4). */
class PostingWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Country $country;

    private Category $vehicles;

    private Category $cars;

    private Region $region;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create();
        $this->country = Country::factory()->create(['name' => ['en' => 'Testland'], 'region_levels' => 1, 'require_region' => true, 'require_city' => true]);
        $this->vehicles = Category::factory()->create(['name' => ['en' => 'Vehicles'], 'icon' => 'car']);
        $this->cars = Category::factory()->create(['parent_id' => $this->vehicles->id, 'name' => ['en' => 'Cars']]);
        $this->region = Region::factory()->create(['country_id' => $this->country->id, 'name' => ['en' => 'West']]);
        $this->city = City::factory()->inRegion($this->region)->create(['name' => ['en' => 'Port Town']]);
    }

    private function location(array $overrides = []): array
    {
        return $overrides + ['category_id' => $this->cars->id, 'country_id' => $this->country->id, 'region_id' => $this->region->id, 'city_id' => $this->city->id];
    }

    private function draft(array $attributes = []): Listing
    {
        return Listing::factory()->inCountry($this->country)->for($this->seller)->draft()
            ->create($attributes + ['category_id' => $this->cars->id, 'region_id' => $this->region->id, 'city_id' => $this->city->id]);
    }

    public function test_only_verified_members_can_post(): void
    {
        $this->get(route('listings.create'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get(route('listings.create'))->assertRedirect(route('verification.notice'));

        $other = Listing::factory()->for(User::factory()->create())->draft()->create();
        $this->actingAs($this->seller)->get(route('listings.edit', $other))->assertForbidden();
        $this->post(route('listings.update', [$other, 'location']), $this->location())->assertForbidden();
    }

    public function test_category_step_lists_top_level_then_subcategories_with_search(): void
    {
        $html = $this->actingAs($this->seller)->get(route('listings.create'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString(route('listings.create').'?root='.$this->vehicles->id, $html);
        $this->assertStringContainsString('data-category-filter', $html);
        $this->assertStringContainsString('aria-current="step"', $html);
        $this->assertStringNotContainsString('data-bottom-nav', $html);

        $sub = $this->get(route('listings.create', ['root' => $this->vehicles->id]))->getContent();
        $this->assertStringContainsString(e(route('listings.create', ['category' => $this->cars->id])), $sub);
    }

    public function test_location_step_uses_the_countrys_terms_and_loads_towns_after_the_region(): void
    {
        $this->country->update(['region_label' => ['en' => 'Province']]);

        $before = $this->actingAs($this->seller)->get(route('listings.create', ['category' => $this->cars->id, 'country_id' => $this->country->id]))->assertOk()->getContent();
        $this->assertStringContainsString('Province', $before);
        $this->assertStringContainsString('data-reload=', $before);
        $this->assertStringNotContainsString('Port Town', $before);

        $after = $this->get(route('listings.create', ['category' => $this->cars->id, 'country_id' => $this->country->id, 'region_id' => $this->region->id]))->getContent();
        $this->assertStringContainsString('Port Town', $after);
        $this->assertStringContainsString('role="combobox"', $after);
    }

    public function test_saving_the_location_creates_the_draft_and_moves_on(): void
    {
        $this->actingAs($this->seller)->post(route('listings.store'), $this->location(['locality_text' => 'Near the lake']))
            ->assertRedirect()->assertSessionHas('status', __('posting.saved_draft'));

        $listing = Listing::where('user_id', $this->seller->id)->firstOrFail();
        $this->assertSame(ListingStatus::Draft, $listing->status);
        $this->assertSame([$this->cars->id, $this->city->id, 'Near the lake'], [$listing->category_id, $listing->city_id, $listing->locality_text]);
        $this->assertSame($this->country->default_currency, $listing->currency);

        $this->get(route('listings.edit', [$listing, 'details']))->assertOk()->assertSee(__('posting.pending.title'));
    }

    public function test_location_errors_are_shown_on_the_step(): void
    {
        // Follow the redirect: asserting on the session first would use up the flashed errors.
        $html = $this->actingAs($this->seller)->from(route('listings.create', ['category' => $this->cars->id]))
            ->followingRedirects()->post(route('listings.store'), $this->location(['city_id' => null]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-error-summary', $html);
        $this->assertStringContainsString('id="f-city_id-error"', $html);
        $this->assertSame(0, Listing::count());
    }

    public function test_changing_the_category_of_a_draft_keeps_its_location(): void
    {
        $vans = Category::factory()->create(['parent_id' => $this->vehicles->id, 'name' => ['en' => 'Vans']]);
        $listing = $this->draft();

        $html = $this->actingAs($this->seller)->get(route('listings.edit', [$listing, 'category']).'?root='.$this->vehicles->id)->getContent();
        $this->assertStringContainsString('name="keep_location" value="1"', $html);

        $this->post(route('listings.update', [$listing, 'category']), ['category_id' => $vans->id, 'keep_location' => 1])->assertRedirect();
        $this->assertSame([$vans->id, $this->city->id], [$listing->fresh()->category_id, $listing->fresh()->city_id]);
    }

    public function test_changing_the_country_asks_first_when_values_would_be_cleared(): void
    {
        $other = Country::factory()->create(['name' => ['en' => 'Otherland'], 'region_levels' => 0, 'require_region' => false, 'require_city' => false]);
        $listing = $this->draft(['price' => '1000', 'price_type' => 'fixed']);
        $input = ['category_id' => $this->cars->id, 'country_id' => $other->id];

        $this->actingAs($this->seller)->from(route('listings.edit', [$listing, 'location']))
            ->post(route('listings.update', [$listing, 'location']), $input)
            ->assertRedirect(route('listings.edit', [$listing, 'location']))->assertSessionHas('country_change');
        $this->assertSame($this->country->id, $listing->fresh()->country_id);

        $dialog = $this->get(route('listings.edit', [$listing, 'location']))->getContent();
        $this->assertStringContainsString('<dialog id="country-change"', $dialog);
        $this->assertStringContainsString('name="confirm_country_change" value="1"', $dialog);

        $this->post(route('listings.update', [$listing, 'location']), $input + ['confirm_country_change' => 1])->assertRedirect();
        $this->assertSame([$other->id, null], [$listing->fresh()->country_id, $listing->fresh()->price]);
    }

    public function test_town_not_listed_creates_a_request_for_staff(): void
    {
        $this->actingAs($this->seller)->post(route('listings.store'), $this->location(['proposed_city' => 'Little Hamlet']))->assertRedirect();

        $request = LocationRequest::firstOrFail();
        $this->assertSame(['Little Hamlet', $this->region->id, $this->seller->id], [$request->proposed_city, $request->region_id, $request->user_id]);
    }

    public function test_steps_ahead_of_progress_redirect_to_the_next_open_step(): void
    {
        $listing = $this->draft(['title' => null, 'description' => null]);

        $this->actingAs($this->seller)->get(route('listings.edit', [$listing, 'photos']))->assertRedirect(route('listings.edit', [$listing, 'details']));
        $this->get(route('listings.edit', [$listing, 'nonsense']))->assertNotFound();
    }

    public function test_post_button_and_my_ads_edit_link_now_lead_to_the_wizard(): void
    {
        $html = $this->actingAs($this->seller)->get('/countries')->getContent();
        $this->assertStringContainsString('href="'.route('listings.create').'"', $html);

        $listing = $this->draft();
        $mine = $this->get(route('account.listings', ['status' => 'drafts']))->getContent();
        $this->assertStringContainsString(route('listings.edit', $listing), $mine);
    }
}
