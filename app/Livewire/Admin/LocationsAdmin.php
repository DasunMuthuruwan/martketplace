<?php

namespace App\Livewire\Admin;

use App\Actions\Locations\MergeCities;
use App\Authorization\Permissions;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\Region;
use App\Services\Audit\AuditLogger;
use App\Services\Geo\GeoNames\UniqueSlug;
use App\Services\Geo\RegionChain;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Location manager (ADM-03, GLB-16, global design §4): regions (one or two levels), cities and
 * localities of one country. Slugs never change once created (location URLs and imports rely on
 * them); places used by listings are switched off, never deleted. Edited cities become curated so
 * GeoNames re-imports keep their name and region. Moving a city to another region moves its
 * listings' region chain too; duplicates are merged (MergeCities). Every change is audited.
 */
#[Layout('layouts.admin')]
#[Title('Locations')]
class LocationsAdmin extends Component
{
    use RequiresPermission, WithPagination;

    private const CITIES_PER_PAGE = 50;

    public string $countryId = '';

    // ---- Region form ------------------------------------------------------------------------

    public bool $regionEditing = false;

    #[Locked]
    public ?int $regionId = null;

    public string $regionParent = '';

    public string $regionName = '';

    public string $regionCode = '';

    public int $regionSort = 0;

    public bool $regionActive = true;

    // ---- Cities -----------------------------------------------------------------------------

    public string $cityFilter = '';

    public string $citySearch = '';

    public bool $cityEditing = false;

    #[Locked]
    public ?int $cityId = null;

    public string $cityName = '';

    public string $cityRegion = '';

    public bool $cityActive = true;

    /** The duplicate being merged. */
    #[Locked]
    public ?int $mergeId = null;

    public string $mergeSearch = '';

    // ---- Localities -------------------------------------------------------------------------

    /** City whose localities are shown. */
    #[Locked]
    public ?int $localitiesOf = null;

    #[Locked]
    public ?int $localityId = null;

    public string $localityName = '';

    public bool $localityActive = true;

    public function mount(): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $first = Country::where('is_active', true)->orderBy('sort_order')->orderBy('iso2')->value('id');
        $this->countryId = $first === null ? '' : (string) $first;
    }

    public function updatedCountryId(): void
    {
        $this->reset(['cityFilter', 'citySearch', 'localitiesOf', 'mergeId', 'mergeSearch']);
        $this->closeRegion();
        $this->closeCity();
        $this->closeLocality();
        $this->resetPage();
    }

    public function updatedCityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCitySearch(): void
    {
        $this->resetPage();
    }

    // ---- Regions ----------------------------------------------------------------------------

    public function createRegion(?int $parentId = null): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $this->closeRegion();
        $this->regionParent = $parentId === null ? '' : (string) $parentId;
        $this->regionEditing = true;
    }

    public function editRegion(int $id): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $region = $this->region($id);
        $this->closeRegion();
        $this->regionId = $region->id;
        $this->regionParent = (string) ($region->parent_id ?? '');
        $this->regionName = (string) $region->getTranslation('name', 'en', false);
        $this->regionCode = (string) $region->code;
        $this->regionSort = $region->sort_order;
        $this->regionActive = $region->is_active;
        $this->regionEditing = true;
    }

    public function saveRegion(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $country = $this->country();
        $data = $this->validate([
            'regionName' => ['required', 'string', 'max:100'],
            'regionCode' => ['nullable', 'string', 'max:40'],
            'regionSort' => ['integer', 'min:0', 'max:65535'],
            'regionParent' => ['nullable', Rule::exists('regions', 'id')->where('country_id', $country->id)->where('level', 1)],
        ]);
        $parentId = blank($data['regionParent']) ? null : (int) $data['regionParent'];
        $level = $parentId === null ? 1 : 2;
        if ($country->region_levels < $level) {
            $this->addError('regionName', __('locations.no_such_level'));

            return;
        }

        $region = $this->regionId === null ? new Region : $this->region($this->regionId);
        $created = ! $region->exists;
        if ($created) {
            $taken = Region::where('country_id', $country->id)->pluck('slug')->flip();
            $region->fill([
                'country_id' => $country->id,
                'parent_id' => $parentId,
                'level' => $level,
                'slug' => UniqueSlug::make($data['regionName'], fn (string $slug) => $taken->has($slug)),
            ]);
        }
        $region->fill([
            'code' => blank($data['regionCode']) ? null : trim($data['regionCode']),
            'sort_order' => $data['regionSort'],
            'is_active' => $this->regionActive,
        ]);
        $region->setTranslation('name', 'en', trim($data['regionName']));
        $region->save();

        $audit->record(Auth::user(), $created ? 'region.created' : 'region.updated', $region, null, $region->only(['slug', 'level', 'parent_id', 'is_active']));
        session()->flash('status', __('admin.catalogue.saved', ['name' => $region->name]));
        $this->closeRegion();
    }

    public function deleteRegion(int $id, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $region = $this->region($id);
        $used = Region::where('parent_id', $region->id)->exists()
            || City::where('region_id', $region->id)->exists()
            || Listing::withTrashed()->where(fn ($q) => $q->where('region_id', $region->id)->orWhere('subregion_id', $region->id))->exists();
        if ($used) {
            $this->addError('regions', __('locations.region_in_use'));

            return;
        }

        $region->delete();
        $audit->record(Auth::user(), 'region.deleted', null, null, ['id' => $region->id, 'slug' => $region->slug, 'country_id' => $region->country_id]);
        session()->flash('status', __('admin.catalogue.deleted', ['name' => $region->name]));
        $this->closeRegion();
    }

    public function closeRegion(): void
    {
        $this->reset(['regionEditing', 'regionId', 'regionParent', 'regionName', 'regionCode', 'regionSort', 'regionActive']);
        $this->resetErrorBag();
    }

    // ---- Cities -----------------------------------------------------------------------------

    public function createCity(): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $this->closeCity();
        $this->cityRegion = $this->cityFilter;
        $this->cityEditing = true;
    }

    public function editCity(int $id): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $city = $this->city($id);
        $this->closeCity();
        $this->cityId = $city->id;
        $this->cityName = (string) $city->getTranslation('name', 'en', false);
        $this->cityRegion = (string) ($city->region_id ?? '');
        $this->cityActive = $city->is_active;
        $this->cityEditing = true;
    }

    public function saveCity(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $country = $this->country();
        $level = RegionChain::cityLevel($country);
        $data = $this->validate([
            'cityName' => ['required', 'string', 'max:100'],
            'cityRegion' => $level === null
                ? ['nullable', 'prohibited']
                : ['required', Rule::exists('regions', 'id')->where('country_id', $country->id)->where('level', $level)],
        ]);
        $regionId = blank($data['cityRegion']) ? null : (int) $data['cityRegion'];

        $city = $this->cityId === null ? new City(['country_id' => $country->id]) : $this->city($this->cityId);
        $created = ! $city->exists;
        $moved = ! $created && $city->region_id !== $regionId;
        $slug = $created ? UniqueSlug::make($data['cityName'], fn (string $s) => $this->citySlugTaken($country, $regionId, $s)) : $city->slug;
        if ($moved && $this->citySlugTaken($country, $regionId, $slug, $city->id)) {
            $this->addError('cityRegion', __('locations.slug_clash'));

            return;
        }

        $listings = DB::transaction(function () use ($city, $data, $regionId, $slug, $moved): int {
            $city->fill([
                'region_id' => $regionId,
                'ascii_name' => Str::ascii(trim($data['cityName'])),
                'slug' => $slug,
                'is_active' => $this->cityActive,
                'is_curated' => true, // imports keep a staff-edited name and region
            ]);
            $city->setTranslation('name', 'en', trim($data['cityName']));
            $city->save();

            return $moved ? Listing::withTrashed()->where('city_id', $city->id)->update(RegionChain::forCity($city)) : 0;
        });

        $audit->record(Auth::user(), $created ? 'city.created' : 'city.updated', $city, null, $city->only(['slug', 'region_id', 'is_active']) + ['listings_moved' => $listings]);
        session()->flash('status', __('admin.catalogue.saved', ['name' => $city->name]));
        $this->closeCity();
    }

    public function deleteCity(int $id, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $city = $this->city($id);
        if ($city->listings()->withTrashed()->exists() || $city->localities()->exists()) {
            $this->addError('cities', __('locations.city_in_use'));

            return;
        }

        $city->delete();
        $audit->record(Auth::user(), 'city.deleted', null, null, ['id' => $city->id, 'slug' => $city->slug, 'country_id' => $city->country_id]);
        session()->flash('status', __('admin.catalogue.deleted', ['name' => $city->name]));
        if ($this->localitiesOf === $id) {
            $this->localitiesOf = null;
        }
    }

    public function closeCity(): void
    {
        $this->reset(['cityEditing', 'cityId', 'cityName', 'cityRegion', 'cityActive']);
        $this->resetErrorBag();
    }

    public function startMerge(int $id): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $this->mergeId = $this->city($id)->id;
        $this->mergeSearch = '';
        $this->resetErrorBag();
    }

    public function mergeInto(int $keepId, MergeCities $merge): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $duplicate = $this->city((int) $this->mergeId);
        $keep = $this->city($keepId);

        try {
            $result = $merge->handle(Auth::user(), $duplicate, $keep);
        } catch (ValidationException $e) {
            $this->addError('mergeInto', (string) collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('status', __('locations.merged', ['from' => $duplicate->name, 'to' => $keep->name, 'count' => $result['listings']]));
        $this->cancelMerge();
    }

    public function cancelMerge(): void
    {
        $this->reset(['mergeId', 'mergeSearch']);
    }

    // ---- Localities -------------------------------------------------------------------------

    public function showLocalities(int $cityId): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $this->localitiesOf = $this->city($cityId)->id;
        $this->closeLocality();
    }

    public function editLocality(int $id): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $locality = $this->locality($id);
        $this->localityId = $locality->id;
        $this->localityName = (string) $locality->getTranslation('name', 'en', false);
        $this->localityActive = $locality->is_active;
        $this->resetErrorBag();
    }

    public function saveLocality(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $city = $this->city((int) $this->localitiesOf);
        $data = $this->validate(['localityName' => ['required', 'string', 'max:100']]);

        $locality = $this->localityId === null ? new Locality(['city_id' => $city->id]) : $this->locality($this->localityId);
        $created = ! $locality->exists;
        if ($created) {
            $taken = Locality::where('city_id', $city->id)->pluck('slug')->flip();
            $locality->slug = UniqueSlug::make($data['localityName'], fn (string $slug) => $taken->has($slug));
        }
        $locality->is_active = $this->localityActive;
        $locality->setTranslation('name', 'en', trim($data['localityName']));
        $locality->save();

        $audit->record(Auth::user(), $created ? 'locality.created' : 'locality.updated', $locality, null, $locality->only(['city_id', 'slug', 'is_active']));
        session()->flash('status', __('admin.catalogue.saved', ['name' => $locality->name]));
        $this->closeLocality();
    }

    public function deleteLocality(int $id, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $locality = $this->locality($id);
        if (Listing::withTrashed()->where('locality_id', $locality->id)->exists()) {
            $this->addError('localities', __('locations.locality_in_use'));

            return;
        }

        $locality->delete();
        $audit->record(Auth::user(), 'locality.deleted', null, null, ['id' => $locality->id, 'slug' => $locality->slug, 'city_id' => $locality->city_id]);
        $this->closeLocality();
    }

    public function closeLocality(): void
    {
        $this->reset(['localityId', 'localityName', 'localityActive']);
        $this->resetErrorBag();
    }

    // ---- View -------------------------------------------------------------------------------

    public function render(): View
    {
        $country = $this->countryId === '' ? null : Country::find((int) $this->countryId);
        $cityLevel = $country === null ? null : RegionChain::cityLevel($country);
        $regions = $country === null ? collect() : Region::where('country_id', $country->id)->orderBy('level')->orderBy('sort_order')->orderBy('slug')->get();
        $term = '%'.addcslashes($this->citySearch, '%_\\').'%';
        $mergeTerm = '%'.addcslashes($this->mergeSearch, '%_\\').'%';

        return view('livewire.admin.locations', [
            'countries' => Country::orderByDesc('is_active')->orderBy('iso2')->get(['id', 'iso2', 'name', 'is_active']),
            'country' => $country,
            'level1' => $regions->where('level', 1)->values(),
            'level2' => $regions->where('level', 2)->groupBy('parent_id'),
            'cityRegions' => $cityLevel === null ? collect() : $regions->where('level', $cityLevel)->values(),
            'regionNames' => $regions->mapWithKeys(fn (Region $r) => [$r->id => $r->name]),
            'cities' => $country === null ? null : City::where('country_id', $country->id)
                ->when($this->cityFilter !== '', fn ($q) => $q->where('region_id', (int) $this->cityFilter))
                ->when($this->citySearch !== '', fn ($q) => $q->where(fn ($w) => $w->where('ascii_name', 'like', $term)->orWhere('name', 'like', $term)))
                ->withCount(['listings' => fn ($q) => $q->withTrashed()])
                ->orderBy('ascii_name')
                ->paginate(self::CITIES_PER_PAGE),
            'mergeFrom' => $this->mergeId === null ? null : City::find($this->mergeId),
            'mergeTargets' => $this->mergeId === null || mb_strlen($this->mergeSearch) < 2 || $country === null ? collect()
                : City::where('country_id', $country->id)->whereKeyNot($this->mergeId)
                    ->where(fn ($w) => $w->where('ascii_name', 'like', $mergeTerm)->orWhere('name', 'like', $mergeTerm))
                    ->orderBy('ascii_name')->limit(10)->get(),
            'localityCity' => $this->localitiesOf === null ? null : City::with(['localities' => fn ($q) => $q->orderBy('slug')])->find($this->localitiesOf),
        ]);
    }

    private function country(): Country
    {
        return Country::findOrFail((int) $this->countryId);
    }

    /** A region of the selected country (never another country's, whatever id is sent). */
    private function region(int $id): Region
    {
        return Region::where('country_id', (int) $this->countryId)->findOrFail($id);
    }

    private function city(int $id): City
    {
        return City::where('country_id', (int) $this->countryId)->findOrFail($id);
    }

    private function locality(int $id): Locality
    {
        return Locality::where('city_id', (int) $this->localitiesOf)->findOrFail($id);
    }

    private function citySlugTaken(Country $country, ?int $regionId, string $slug, ?int $except = null): bool
    {
        return City::where('country_id', $country->id)->where('region_id', $regionId)->where('slug', $slug)
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except))
            ->exists();
    }
}
