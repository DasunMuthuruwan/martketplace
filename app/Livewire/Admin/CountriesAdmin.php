<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\MeasurementUnit;
use App\Models\Region;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\CatalogueCache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Country settings (ADM-03, GLB-02, global design §2–§5): name and location labels, currency,
 * number locale, timezone, region levels (fixed once regions exist), required levels, area units
 * and the land price unit. Activating or deactivating a country is the Owner's decision
 * (`settings.manage`) and runs the activation checklist first. Every change is audited.
 */
#[Layout('layouts.admin')]
#[Title('Countries')]
class CountriesAdmin extends Component
{
    use RequiresPermission, WithPagination;

    public string $q = '';

    /** '' = all, '1' = active, '0' = inactive */
    public string $active = '';

    #[Locked]
    public ?int $selectedId = null;

    public string $name = '';

    public string $regionLabel = '';

    public string $subregionLabel = '';

    public string $cityLabel = '';

    public string $localityLabel = '';

    public string $currency = '';

    public string $numberLocale = '';

    public string $timezone = '';

    public int $regionLevels = 1;

    public bool $requireRegion = false;

    public bool $requireCity = false;

    public int $sortOrder = 0;

    /** @var list<string> unit ids offered in area inputs */
    public array $areaUnits = [];

    public string $defaultAreaUnit = '';

    public string $landPriceUnit = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedActive(): void
    {
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $country = Country::with('areaUnits')->findOrFail($id);
        $this->resetErrorBag();

        $this->selectedId = $country->id;
        $this->name = (string) $country->getTranslation('name', 'en', false);
        foreach (['region', 'subregion', 'city', 'locality'] as $level) {
            $this->{$level.'Label'} = (string) $country->getTranslation($level.'_label', 'en', false);
        }
        $this->currency = (string) $country->default_currency;
        $this->numberLocale = $country->number_locale;
        $this->timezone = (string) $country->timezone;
        $this->regionLevels = $country->region_levels;
        $this->requireRegion = $country->require_region;
        $this->requireCity = $country->require_city;
        $this->sortOrder = $country->sort_order;
        $this->areaUnits = $country->areaUnits->map(fn (MeasurementUnit $u) => (string) $u->id)->values()->all();
        $default = $country->areaUnits->first(fn (MeasurementUnit $u) => (bool) $u->getRelation('pivot')->getAttribute('is_default'));
        $this->defaultAreaUnit = $default === null ? '' : (string) $default->id;
        $this->landPriceUnit = (string) ($country->land_price_unit_id ?? '');
    }

    public function save(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::LOCATIONS_MANAGE);
        $country = Country::findOrFail($this->selectedId);
        $areaUnitIds = MeasurementUnit::where('dimension', MeasurementUnit::AREA)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'regionLabel' => ['nullable', 'string', 'max:40'],
            'subregionLabel' => ['nullable', 'string', 'max:40'],
            'cityLabel' => ['nullable', 'string', 'max:40'],
            'localityLabel' => ['nullable', 'string', 'max:40'],
            'currency' => ['required', Rule::exists('currencies', 'code')->where('is_active', true)],
            'numberLocale' => ['required', 'string', 'max:15', 'regex:/^[a-z]{2,3}(_[A-Za-z0-9]{2,4})*$/'],
            'timezone' => ['nullable', 'timezone:all'],
            'regionLevels' => ['integer', 'between:0,2'],
            'sortOrder' => ['integer', 'min:0', 'max:65535'],
            'areaUnits' => ['array'],
            'areaUnits.*' => [Rule::in($areaUnitIds)],
            'defaultAreaUnit' => ['nullable', Rule::in($this->areaUnits)],
            'landPriceUnit' => ['nullable', Rule::in($areaUnitIds)],
        ]);

        // Cities and listings hang off the existing levels: changing the number would orphan them.
        if ($data['regionLevels'] !== $country->region_levels && Region::where('country_id', $country->id)->exists()) {
            $this->addError('regionLevels', __('locations.levels_locked'));

            return;
        }

        $before = $this->snapshot($country);
        DB::transaction(function () use ($country, $data): void {
            $country->fill([
                'default_currency' => $data['currency'],
                'number_locale' => $data['numberLocale'],
                'timezone' => blank($data['timezone']) ? null : $data['timezone'],
                'region_levels' => $data['regionLevels'],
                'require_region' => $data['regionLevels'] > 0 && $this->requireRegion,
                'require_city' => $this->requireCity,
                'sort_order' => $data['sortOrder'],
                'land_price_unit_id' => blank($data['landPriceUnit']) ? null : (int) $data['landPriceUnit'],
            ]);
            $country->setTranslation('name', 'en', trim($data['name']));
            foreach (['region', 'subregion', 'city', 'locality'] as $level) {
                $country->setTranslation($level.'_label', 'en', trim((string) $data[$level.'Label']));
            }
            $country->save();

            // MVP: one currency per country (G-4); the pivot mirrors the default.
            DB::table('country_currencies')->where('country_id', $country->id)->delete();
            DB::table('country_currencies')->insert(['country_id' => $country->id, 'currency_code' => $data['currency'], 'is_default' => true]);

            $units = [];
            foreach (array_values($data['areaUnits']) as $position => $unitId) {
                $units[(int) $unitId] = ['is_default' => $unitId === $data['defaultAreaUnit'], 'sort_order' => $position + 1];
            }
            $country->areaUnits()->sync($units);
        });
        CatalogueCache::flush(); // area units and labels feed cached listing forms

        $audit->record(Auth::user(), 'country.updated', $country, null, ['before' => $before, 'after' => $this->snapshot($country->fresh())]);
        session()->flash('status', __('admin.catalogue.saved', ['name' => $country->name]));
    }

    /** Owner only (GLB-02). Activation needs the checklist's required items. */
    public function toggleActive(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::SETTINGS_MANAGE);
        $country = Country::findOrFail($this->selectedId);

        if (! $country->is_active) {
            $missing = array_keys(array_filter($this->checklist($country), fn (array $item) => $item['required'] && ! $item['ok']));
            if ($missing !== []) {
                $this->addError('activate', __('locations.activation_blocked'));

                return;
            }
        }

        $country->update(['is_active' => ! $country->is_active]);
        Cache::forget('public:country-ad-counts');
        CatalogueCache::flush();
        $audit->record(Auth::user(), $country->is_active ? 'country.activated' : 'country.deactivated', $country);
        session()->flash('status', __($country->is_active ? 'locations.activated' : 'locations.deactivated', ['name' => $country->name]));
    }

    public function close(): void
    {
        $this->selectedId = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $selected = $this->selectedId === null ? null : Country::find($this->selectedId);
        $term = '%'.addcslashes($this->q, '%_\\').'%';

        return view('livewire.admin.countries', [
            'countries' => Country::query()
                ->when($this->q !== '', fn ($query) => $query->where(fn ($w) => $w->where('iso2', 'like', $term)->orWhere('iso3', 'like', $term)->orWhere('name', 'like', $term)))
                ->when($this->active !== '', fn ($query) => $query->where('is_active', $this->active === '1'))
                ->orderByDesc('is_active')->orderBy('sort_order')->orderBy('iso2')
                ->paginate(30),
            'selected' => $selected,
            'checklist' => $selected === null ? [] : $this->checklist($selected),
            'hasRegions' => $selected !== null && Region::where('country_id', $selected->id)->exists(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'units' => MeasurementUnit::where('dimension', MeasurementUnit::AREA)->orderBy('sort_order')->get(),
            'canActivate' => Auth::user()?->can(Permissions::SETTINGS_MANAGE) ?? false,
        ]);
    }

    /**
     * Activation checklist (global design §2, G-10, G-12). Required items block activation; the
     * others are reminders for the Owner.
     *
     * @return array<string, array{ok: bool, required: bool}>
     */
    private function checklist(Country $country): array
    {
        $regions = Region::where('country_id', $country->id)->where('is_active', true)->exists();

        return [
            'currency' => ['ok' => $country->default_currency !== null, 'required' => true],
            'regions' => ['ok' => $country->region_levels === 0 || $regions, 'required' => true],
            'cities' => ['ok' => City::where('country_id', $country->id)->where('is_active', true)->exists(), 'required' => true],
            'area_units' => ['ok' => $country->areaUnits()->exists(), 'required' => false],
            'legal' => ['ok' => false, 'required' => false],
            'moderation' => ['ok' => false, 'required' => false],
            'search_language' => ['ok' => false, 'required' => false],
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(Country $country): array
    {
        return $country->only(['default_currency', 'number_locale', 'timezone', 'region_levels', 'require_region', 'require_city', 'sort_order', 'land_price_unit_id', 'is_active']);
    }
}
