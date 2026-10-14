<?php

namespace App\Services\Geo;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;

/**
 * The region ids a listing stores for a city (global design §4): cities hang off the deepest
 * region level of their country, listings keep both levels so browse pages can filter by either.
 */
final class RegionChain
{
    /** @return array{region_id: int|null, subregion_id: int|null} */
    public static function forCity(City $city): array
    {
        return self::forRegion(Country::findOrFail($city->country_id), $city->region_id === null ? null : Region::find($city->region_id));
    }

    /** @return array{region_id: int|null, subregion_id: int|null} */
    public static function forRegion(Country $country, ?Region $deepest): array
    {
        if ($deepest === null || $country->region_levels === 0) {
            return ['region_id' => null, 'subregion_id' => null];
        }
        if ($country->region_levels >= 2 && $deepest->level === 2) {
            return ['region_id' => $deepest->parent_id, 'subregion_id' => $deepest->id];
        }

        return ['region_id' => $deepest->id, 'subregion_id' => null];
    }

    /** The region level cities attach to in this country (null when it has no regions). */
    public static function cityLevel(Country $country): ?int
    {
        return $country->region_levels === 0 ? null : min($country->region_levels, 2);
    }
}
