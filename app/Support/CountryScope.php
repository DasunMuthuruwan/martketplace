<?php

namespace App\Support;

/**
 * `scope_countries` lists (category tree doc §6): ISO 3166-1 alpha-2 codes, upper-case, unique and
 * sorted; null means every country.
 */
final class CountryScope
{
    /**
     * @param  array<int, mixed>|null  $countries
     * @return list<string>|null
     */
    public static function normalise(?array $countries): ?array
    {
        if ($countries === null) {
            return null;
        }

        $codes = array_values(array_unique(array_map(fn ($code) => strtoupper(trim((string) $code)), $countries)));
        sort($codes);

        return $codes === [] ? null : $codes;
    }

    /** @param  list<string>|null  $scope */
    public static function includes(?array $scope, string $iso2): bool
    {
        return $scope === null || in_array(strtoupper($iso2), $scope, true);
    }
}
