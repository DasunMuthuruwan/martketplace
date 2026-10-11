<?php

namespace App\Services\Money;

use App\Models\Country;
use App\Models\MeasurementUnit;
use Illuminate\Database\Eloquent\Collection;

/**
 * Area units offered in a country (global design §5): the country's own list, first the default,
 * or, for a country without one (or an unknown country), the configured fallback (metric).
 * Inactive units are never offered.
 */
class AreaUnits
{
    /** @return Collection<int, MeasurementUnit> default unit first */
    public function forCountry(?Country $country): Collection
    {
        $units = $country === null ? new Collection : $country->areaUnits()->area()->active()->get();

        if ($units->isEmpty()) {
            $codes = (array) config('marketplace.area.fallback_units');

            return MeasurementUnit::area()->active()->whereIn('code', $codes)->get()
                ->sortBy(fn (MeasurementUnit $unit) => array_search($unit->code, $codes, true))
                ->values();
        }

        $default = $units->first(fn (MeasurementUnit $unit) => (bool) $unit->getRelation('pivot')->is_default);

        return $default === null
            ? $units
            : $units->reject(fn (MeasurementUnit $unit) => $unit->is($default))->prepend($default)->values();
    }

    public function defaultFor(?Country $country): ?MeasurementUnit
    {
        return $this->forCountry($country)->first();
    }

    /** The unit used for "price per" on land in this country, if active; null = no per-unit price. */
    public function landPriceUnit(Country $country): ?MeasurementUnit
    {
        $unit = $country->landPriceUnit;

        return $unit !== null && $unit->is_active ? $unit : null;
    }
}
