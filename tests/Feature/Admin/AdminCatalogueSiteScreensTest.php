<?php

namespace Tests\Feature\Admin;

use App\Enums\StaffRole;
use App\Livewire\Admin\AttributesAdmin;
use App\Livewire\Admin\CategoriesAdmin;
use App\Livewire\Admin\CountriesAdmin;
use App\Livewire\Admin\LocationRequestsAdmin;
use App\Livewire\Admin\LocationsAdmin;
use App\Livewire\Admin\PagesAdmin;
use App\Livewire\Admin\SettingsAdmin;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\LocationRequest;
use App\Models\Page;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** F5.1 part 3: designed catalogue, locations (merge preview), place requests, settings and pages. */
class AdminCatalogueSiteScreensTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $owner;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->owner = $this->staff(StaffRole::Owner);
        session()->put('auth.password_confirmed_at', time());
        $this->country = Country::factory()->create(['region_levels' => 1]);
    }

    public function test_category_tree_has_named_actions_and_a_grouped_form(): void
    {
        $root = Category::factory()->create(['name' => ['en' => 'Vehicles']]);
        Category::factory()->create(['parent_id' => $root->id, 'name' => ['en' => 'Cars']]);

        Livewire::actingAs($this->owner)->test(CategoriesAdmin::class)
            ->assertSee(__('admin.catalogue.choose'))
            ->assertSeeHtml('aria-label="'.e(__('admin.catalogue.up_named', ['name' => 'Cars'])).'"')
            ->assertSee(trans_choice('admin.catalogue.subcategories', 1, ['count' => 1]))
            // A category with subcategories cannot be deleted, so it offers no delete button.
            ->assertDontSeeHtml('aria-label="'.e(__('admin.catalogue.delete_named', ['name' => 'Vehicles'])).'"')
            ->call('edit', $root->id)
            ->assertSee(__('admin.catalogue.edit_named', ['name' => 'Vehicles']))
            ->assertSee(__('admin.catalogue.section_basics'))
            ->assertSee(__('admin.catalogue.section_ads'))
            ->assertSee(__('admin.catalogue.section_seo'));
    }

    public function test_field_editor_replaces_the_list(): void
    {
        $attribute = Attribute::factory()->create(['key' => 'fuel_type', 'label' => ['en' => 'Fuel']]);

        Livewire::actingAs($this->owner)->test(AttributesAdmin::class)
            ->assertSee('fuel_type')
            ->call('select', $attribute->id)
            ->assertSee(__('admin.catalogue.back_to_fields'))
            ->assertSee(__('admin.catalogue.section_field'))
            ->assertSee(__('admin.catalogue.categories_using'))
            ->assertDontSee(__('admin.catalogue.search_fields'))
            ->call('cancel')
            ->assertSee(__('admin.catalogue.search_fields'));
    }

    public function test_country_pane_shows_the_checklist_and_section_tabs(): void
    {
        $html = Livewire::actingAs($this->owner)->test(CountriesAdmin::class)->call('select', $this->country->id)->html();

        $this->assertStringContainsString(__('locations.checklist'), $html);
        $this->assertStringContainsString(__('admin.locations.missing').':', $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('admin.countries'), '/').'"\s+aria-current="page"/', $html);
    }

    public function test_merge_shows_a_preview_before_anything_changes(): void
    {
        $region = Region::factory()->create(['country_id' => $this->country->id]);
        $keep = City::factory()->inRegion($region)->create(['name' => ['en' => 'Keeptown']]);
        $duplicate = City::factory()->inRegion($region)->create(['name' => ['en' => 'Dupetown'], 'geonames_id' => 987654]);
        Listing::factory()->count(2)->inCountry($this->country)->create(['region_id' => $region->id, 'city_id' => $duplicate->id]);
        Locality::factory()->create(['city_id' => $keep->id, 'slug' => 'fort']);
        Locality::factory()->create(['city_id' => $duplicate->id, 'slug' => 'fort']);
        Locality::factory()->create(['city_id' => $duplicate->id, 'slug' => 'harbour']);
        LocationRequest::create(['country_id' => $this->country->id, 'city_id' => $duplicate->id, 'proposed_locality' => 'X']);

        $component = Livewire::actingAs($this->owner)->test(LocationsAdmin::class)
            ->set('countryId', (string) $this->country->id)
            ->call('startMerge', $duplicate->id)->set('mergeSearch', 'Keep')
            ->assertSee(__('admin.locations.preview_merge'))
            ->call('previewMerge', $keep->id)
            ->assertSee(trans_choice('admin.locations.will_move_ads', 2, ['count' => 2, 'to' => 'Keeptown']))
            ->assertSee(trans_choice('admin.locations.will_move_requests', 1, ['count' => 1]))
            ->assertSee(__('admin.locations.will_switch_off', ['name' => 'Dupetown']))
            ->assertSee(__('admin.locations.confirm_merge', ['from' => 'Dupetown', 'to' => 'Keeptown']));

        // Nothing has moved yet.
        $this->assertSame(2, Listing::where('city_id', $duplicate->id)->count());

        $component->call('mergeInto', $keep->id)->assertHasNoErrors()->assertSet('mergeTargetId', null);
        $this->assertSame(0, Listing::where('city_id', $duplicate->id)->count());
    }

    public function test_merge_preview_refuses_the_same_town(): void
    {
        $region = Region::factory()->create(['country_id' => $this->country->id]);
        $city = City::factory()->inRegion($region)->create();

        Livewire::actingAs($this->owner)->test(LocationsAdmin::class)
            ->set('countryId', (string) $this->country->id)
            ->call('startMerge', $city->id)
            ->call('previewMerge', $city->id)->assertNotFound();
    }

    public function test_localities_panel_closes(): void
    {
        $region = Region::factory()->create(['country_id' => $this->country->id]);
        $city = City::factory()->inRegion($region)->create();

        Livewire::actingAs($this->owner)->test(LocationsAdmin::class)
            ->set('countryId', (string) $this->country->id)
            ->call('showLocalities', $city->id)->assertSet('localitiesOf', $city->id)
            ->call('hideLocalities')->assertSet('localitiesOf', null);
    }

    public function test_place_request_pane_offers_map_add_and_decline(): void
    {
        $request = LocationRequest::create(['country_id' => $this->country->id, 'proposed_city' => 'Newville']);

        Livewire::actingAs($this->owner)->test(LocationRequestsAdmin::class)
            ->call('select', $request->id)
            ->assertSee('Newville')
            ->assertSee(__('locations.requests.map_title'))
            ->assertSee(__('locations.requests.approve_title'))
            ->assertSee(__('locations.requests.reject_title'));
    }

    public function test_settings_and_page_editor_with_preview(): void
    {
        Livewire::actingAs($this->owner)->test(SettingsAdmin::class)
            ->assertSee(__('admin.settings.site'))
            ->assertSee(__('admin.settings.save'));

        $page = Page::create(['slug' => 'about-us', 'title' => ['en' => 'About us'], 'body' => ['en' => "## Who we are\n\nA marketplace."], 'is_published' => true]);

        Livewire::actingAs($this->owner)->test(PagesAdmin::class)
            ->assertSee('/page/about-us')
            ->call('edit', $page->id)->call('togglePreview')
            ->assertSee(__('admin.pages.back'))
            ->assertSeeHtml('class="page-body"')
            ->assertSeeHtml('<h2>Who we are</h2>');

        $this->get(route('page.show', 'about-us'))->assertOk()->assertSee('page-body', false)->assertSee('Who we are');
    }
}
