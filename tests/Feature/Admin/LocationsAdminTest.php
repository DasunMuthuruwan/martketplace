<?php

namespace Tests\Feature\Admin;

use App\Enums\LocationRequestStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\CountriesAdmin;
use App\Livewire\Admin\LocationRequestsAdmin;
use App\Livewire\Admin\LocationsAdmin;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\LocationRequest;
use App\Models\MeasurementUnit;
use App\Models\Region;
use App\Models\User;
use App\Validation\LocationChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** T6.4: locations admin (ADM-03, GLB-02, GLB-16, global scenario 10). */
class LocationsAdminTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $owner;

    private User $admin;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->owner = $this->staff(StaffRole::Owner);
        $this->admin = $this->staff(StaffRole::Admin);
        $this->country = Country::factory()->create(['region_levels' => 2]);
    }

    private function locations(?User $actor = null): Testable
    {
        return Livewire::actingAs($actor ?? $this->admin)->test(LocationsAdmin::class)->set('countryId', (string) $this->country->id);
    }

    private function countries(?User $actor = null): Testable
    {
        return Livewire::actingAs($actor ?? $this->admin)->test(CountriesAdmin::class)->call('select', $this->country->id);
    }

    /** Level 1 → level 2 → city in this country. */
    private function chain(): array
    {
        $province = Region::factory()->create(['country_id' => $this->country->id]);
        $district = Region::factory()->childOf($province)->create();
        $city = City::factory()->inRegion($district)->create();

        return [$province, $district, $city];
    }

    // ---- Access -----------------------------------------------------------------------------

    public function test_pages_need_the_locations_permission(): void
    {
        foreach (['admin.locations', 'admin.countries', 'admin.location-requests'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
            $this->actingAs($this->staff(StaffRole::Moderator, twoFactor: false))->get(route($route))->assertForbidden();
        }
        Livewire::actingAs(User::factory()->create())->test(LocationsAdmin::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(LocationRequestsAdmin::class)->assertForbidden();
    }

    public function test_places_of_another_country_cannot_be_touched(): void
    {
        $foreign = City::factory()->create();

        $this->locations()->call('deleteCity', $foreign->id)->assertNotFound();
        $this->assertModelExists($foreign);
    }

    // ---- Countries --------------------------------------------------------------------------

    public function test_edit_country_settings_currency_and_area_units(): void
    {
        $currency = Currency::factory()->create();
        $acre = MeasurementUnit::factory()->create(['dimension' => MeasurementUnit::AREA]);
        $sqm = MeasurementUnit::factory()->create(['dimension' => MeasurementUnit::AREA]);

        $this->countries()
            ->set('name', 'Testland')->set('regionLabel', 'State')->set('currency', $currency->code)->set('numberLocale', 'en_QM')
            ->set('timezone', 'Asia/Colombo')->set('requireCity', false)
            ->set('areaUnits', [(string) $acre->id, (string) $sqm->id])->set('defaultAreaUnit', (string) $sqm->id)->set('landPriceUnit', (string) $acre->id)
            ->call('save')->assertHasNoErrors();

        $country = $this->country->fresh();
        $this->assertSame('Testland', $country->name);
        $this->assertSame('State', $country->label('region'));
        $this->assertSame($currency->code, $country->default_currency);
        $this->assertSame([$currency->code], $country->currencies()->pluck('code')->all());
        $this->assertSame($acre->id, $country->land_price_unit_id);
        $this->assertSame([$acre->id, $sqm->id], $country->areaUnits()->pluck('measurement_units.id')->all());
        $this->assertTrue((bool) $country->areaUnits()->whereKey($sqm->id)->first()->pivot->is_default);
        $this->assertFalse($country->require_city);
        $this->assertTrue(AuditLog::where('action', 'country.updated')->exists());

        $this->countries()->set('timezone', 'Mars/Base')->set('numberLocale', 'not a locale')->call('save')->assertHasErrors(['timezone', 'numberLocale']);
    }

    public function test_region_levels_are_fixed_once_regions_exist(): void
    {
        $this->countries()->set('regionLevels', 1)->call('save')->assertHasNoErrors();
        $this->assertSame(1, $this->country->fresh()->region_levels);

        Region::factory()->create(['country_id' => $this->country->id]);
        $this->countries()->set('regionLevels', 2)->call('save')->assertHasErrors('regionLevels');
        $this->assertSame(1, $this->country->fresh()->region_levels);
    }

    public function test_only_the_owner_activates_and_only_with_the_checklist_done(): void
    {
        $country = Country::factory()->create(['is_active' => false, 'region_levels' => 1]);

        Livewire::actingAs($this->admin)->test(CountriesAdmin::class)->call('select', $country->id)->call('toggleActive')->assertForbidden();

        $owner = Livewire::actingAs($this->owner)->test(CountriesAdmin::class)->call('select', $country->id);
        $owner->call('toggleActive')->assertHasErrors('activate'); // no regions or cities yet
        $this->assertFalse($country->fresh()->is_active);

        City::factory()->inRegion(Region::factory()->create(['country_id' => $country->id]))->create();
        $owner->call('toggleActive')->assertHasNoErrors();
        $this->assertTrue($country->fresh()->is_active);
        $this->assertTrue(AuditLog::where('action', 'country.activated')->where('subject_id', $country->id)->exists());
    }

    /** Global scenario 10: pages 404, new ads blocked, existing ads hidden. */
    public function test_deactivating_a_country_hides_it_everywhere(): void
    {
        [$province, $district, $city] = $this->chain();
        $listing = Listing::factory()->inCountry($this->country)->create(['region_id' => $province->id, 'subregion_id' => $district->id, 'city_id' => $city->id]);
        $this->assertTrue(Listing::public()->whereKey($listing->id)->exists());
        $this->get('/'.strtolower($this->country->iso2))->assertOk();

        Livewire::actingAs($this->owner)->test(CountriesAdmin::class)->call('select', $this->country->id)->call('toggleActive')->assertHasNoErrors();

        $this->assertFalse(Listing::public()->whereKey($listing->id)->exists());
        $this->get('/'.strtolower($this->country->iso2))->assertNotFound();
        $this->get($listing->publicUrl())->assertNotFound();
        $errors = (new LocationChain)->check(['country_id' => $this->country->id, 'region_id' => $province->id, 'subregion_id' => $district->id, 'city_id' => $city->id]);
        $this->assertArrayHasKey('country_id', $errors);
        $this->assertTrue(AuditLog::where('action', 'country.deactivated')->exists());
    }

    // ---- Regions ----------------------------------------------------------------------------

    public function test_regions_two_levels_with_fixed_slugs_and_delete_only_when_unused(): void
    {
        $this->locations()->call('createRegion')->set('regionName', 'Western')->call('saveRegion')->assertHasNoErrors();
        $western = Region::where('country_id', $this->country->id)->where('slug', 'western')->firstOrFail();
        $this->assertSame(1, $western->level);

        $this->locations()->call('createRegion', $western->id)->set('regionName', 'Colombo')->call('saveRegion')->assertHasNoErrors();
        $colombo = Region::where('slug', 'colombo')->firstOrFail();
        $this->assertSame([2, $western->id], [$colombo->level, $colombo->parent_id]);

        // Rename keeps the slug (location URLs rely on it).
        $this->locations()->call('editRegion', $western->id)->set('regionName', 'Western Province')->call('saveRegion');
        $this->assertSame(['Western Province', 'western'], [$western->fresh()->name, $western->fresh()->slug]);

        $this->locations()->call('deleteRegion', $western->id)->assertHasErrors('regions'); // has a child
        $this->locations()->call('editRegion', $western->id)->set('regionActive', false)->call('saveRegion');
        $this->assertFalse($western->fresh()->is_active);

        $this->locations()->call('deleteRegion', $colombo->id)->assertHasNoErrors();
        $this->assertModelMissing($colombo);
    }

    public function test_a_country_with_one_level_has_no_subregions(): void
    {
        $this->country->update(['region_levels' => 1]);
        $region = Region::factory()->create(['country_id' => $this->country->id]);

        $this->locations()->call('createRegion', $region->id)->set('regionName', 'Deeper')->call('saveRegion')->assertHasErrors('regionName');
    }

    // ---- Cities -----------------------------------------------------------------------------

    public function test_add_and_edit_city_becomes_curated_and_moving_it_moves_listings(): void
    {
        [$province, $district] = $this->chain();
        $otherProvince = Region::factory()->create(['country_id' => $this->country->id]);
        $otherDistrict = Region::factory()->childOf($otherProvince)->create();

        $this->locations()->call('createCity')->set('cityName', 'Kotte')->set('cityRegion', (string) $province->id)
            ->call('saveCity')->assertHasErrors('cityRegion'); // cities hang off the deepest level
        $this->locations()->call('createCity')->set('cityName', 'Kotte')->set('cityRegion', (string) $district->id)->call('saveCity')->assertHasNoErrors();
        $city = City::where('slug', 'kotte')->firstOrFail();
        $this->assertTrue($city->is_curated);

        $listing = Listing::factory()->inCountry($this->country)->create(['region_id' => $province->id, 'subregion_id' => $district->id, 'city_id' => $city->id]);

        $this->locations()->call('editCity', $city->id)->set('cityRegion', (string) $otherDistrict->id)->call('saveCity')->assertHasNoErrors();

        $this->assertSame($otherDistrict->id, $city->fresh()->region_id);
        $this->assertSame([$otherProvince->id, $otherDistrict->id], [$listing->fresh()->region_id, $listing->fresh()->subregion_id]);
        $this->locations()->call('deleteCity', $city->id)->assertHasErrors('cities');
    }

    /** Merge integrity: listings (incl. deleted), localities and requests move in one transaction. */
    public function test_merge_duplicate_city(): void
    {
        [$province, $district, $keep] = $this->chain();
        $otherDistrict = Region::factory()->childOf($province)->create();
        $duplicate = City::factory()->inRegion($otherDistrict)->create(['geonames_id' => 123456]);

        $listing = Listing::factory()->inCountry($this->country)->create(['region_id' => $province->id, 'subregion_id' => $otherDistrict->id, 'city_id' => $duplicate->id]);
        $deleted = Listing::factory()->inCountry($this->country)->create(['city_id' => $duplicate->id]);
        $deleted->delete();

        $sameSlugKeep = Locality::factory()->create(['city_id' => $keep->id, 'slug' => 'fort']);
        $sameSlugDup = Locality::factory()->create(['city_id' => $duplicate->id, 'slug' => 'fort']);
        $onlyDup = Locality::factory()->create(['city_id' => $duplicate->id, 'slug' => 'pettah']);
        $listing->update(['locality_id' => $sameSlugDup->id]);
        $request = LocationRequest::create(['country_id' => $this->country->id, 'city_id' => $duplicate->id, 'proposed_locality' => 'X']);

        $this->locations()->call('startMerge', $duplicate->id)->set('mergeSearch', mb_substr($keep->ascii_name, 0, 4))
            ->assertSee($keep->name)
            ->call('mergeInto', $keep->id)->assertHasNoErrors();

        $listing->refresh();
        $this->assertSame([$keep->id, $province->id, $district->id, $sameSlugKeep->id], [$listing->city_id, $listing->region_id, $listing->subregion_id, $listing->locality_id]);
        $this->assertSame($keep->id, Listing::withTrashed()->find($deleted->id)->city_id);
        $this->assertModelMissing($sameSlugDup);
        $this->assertSame($keep->id, $onlyDup->fresh()->city_id);
        $this->assertSame($keep->id, $request->fresh()->city_id);
        // Imported duplicate stays (switched off) so a re-import does not create it again.
        $this->assertFalse($duplicate->fresh()->is_active);
        $this->assertSame(0, Listing::withTrashed()->where('city_id', $duplicate->id)->count());
        $this->assertTrue(AuditLog::where('action', 'city.merged')->where('subject_id', $keep->id)->exists());

        // A manually added duplicate with nothing left is deleted; merging into itself or abroad is refused.
        $manual = City::factory()->inRegion($district)->create();
        $this->locations()->call('startMerge', $manual->id)->call('mergeInto', $keep->id);
        $this->assertModelMissing($manual);
        $this->locations()->call('startMerge', $keep->id)->call('mergeInto', $keep->id)->assertHasErrors('mergeInto');
    }

    public function test_localities_add_rename_and_delete_only_when_unused(): void
    {
        [, , $city] = $this->chain();

        $this->locations()->call('showLocalities', $city->id)->set('localityName', 'Fort')->call('saveLocality')->assertHasNoErrors();
        $fort = Locality::where('city_id', $city->id)->where('slug', 'fort')->firstOrFail();

        $this->locations()->call('showLocalities', $city->id)->call('editLocality', $fort->id)->set('localityName', 'Colombo Fort')->call('saveLocality');
        $this->assertSame(['Colombo Fort', 'fort'], [$fort->fresh()->name, $fort->fresh()->slug]);

        Listing::factory()->inCountry($this->country)->create(['city_id' => $city->id, 'locality_id' => $fort->id]);
        $this->locations()->call('showLocalities', $city->id)->call('deleteLocality', $fort->id)->assertHasErrors('localities');
        $this->assertModelExists($fort);
    }

    // ---- Location requests ------------------------------------------------------------------

    public function test_approve_request_creates_a_curated_city_and_locality(): void
    {
        [$province, $district] = $this->chain();
        $request = LocationRequest::create(['country_id' => $this->country->id, 'region_id' => $province->id, 'subregion_id' => $district->id,
            'proposed_city' => 'Newtown', 'proposed_locality' => 'Hill Side']);

        Livewire::actingAs($this->admin)->test(LocationRequestsAdmin::class)->assertSee('Newtown')
            ->call('select', $request->id)->set('approveCity', 'New Town')->call('approve')->assertHasNoErrors();

        $city = City::where('slug', 'new-town')->firstOrFail();
        $this->assertSame([$district->id, true], [$city->region_id, $city->is_curated]);
        $request->refresh();
        $this->assertSame(LocationRequestStatus::Approved, $request->status);
        $this->assertSame($city->id, $request->resolved_city_id);
        $this->assertSame('hill-side', Locality::find($request->resolved_locality_id)->slug);
        $this->assertSame($this->admin->id, $request->handled_by);

        // Handled once.
        Livewire::actingAs($this->admin)->test(LocationRequestsAdmin::class)->call('select', $request->id)->call('reject')->assertHasErrors('note');
        Livewire::actingAs($this->admin)->test(LocationRequestsAdmin::class)->call('select', $request->id)->set('note', 'x')->call('reject')->assertHasErrors('request');
    }

    public function test_map_and_reject_requests(): void
    {
        [, , $city] = $this->chain();
        $foreignCity = City::factory()->create();
        $mapMe = LocationRequest::create(['country_id' => $this->country->id, 'proposed_city' => 'Duplicate']);
        $rejectMe = LocationRequest::create(['country_id' => $this->country->id, 'proposed_city' => 'Nowhere']);

        $page = Livewire::actingAs($this->admin)->test(LocationRequestsAdmin::class);
        $page->call('select', $mapMe->id)->set('mapCity', (string) $foreignCity->id)->call('map')->assertHasErrors('mapCity');
        $page->call('select', $mapMe->id)->set('mapCity', (string) $city->id)->call('map')->assertHasNoErrors();
        $this->assertSame([LocationRequestStatus::Mapped, $city->id], [$mapMe->fresh()->status, $mapMe->fresh()->resolved_city_id]);

        $page->call('select', $rejectMe->id)->set('note', 'Not a real place')->call('reject')->assertHasNoErrors();
        $this->assertSame(LocationRequestStatus::Rejected, $rejectMe->fresh()->status);
        $this->assertSame('Not a real place', $rejectMe->fresh()->resolution_note);
        $this->assertTrue(AuditLog::where('action', 'location_request.rejected')->exists());
    }

    public function test_approve_needs_the_region_and_refuses_existing_places(): void
    {
        [$province, $district, $city] = $this->chain();
        $noRegion = LocationRequest::create(['country_id' => $this->country->id, 'proposed_city' => 'Floating']);
        $exists = LocationRequest::create(['country_id' => $this->country->id, 'region_id' => $province->id, 'subregion_id' => $district->id, 'proposed_city' => $city->name]);

        $page = Livewire::actingAs($this->admin)->test(LocationRequestsAdmin::class);
        $page->call('select', $noRegion->id)->call('approve')->assertHasErrors('approveCity');
        $page->call('select', $exists->id)->call('approve')->assertHasErrors('approveCity');
        $this->assertSame(LocationRequestStatus::Pending, $exists->fresh()->status);
    }
}
