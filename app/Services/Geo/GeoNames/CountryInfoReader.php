<?php

namespace App\Services\Geo\GeoNames;

use RuntimeException;

/**
 * Reads GeoNames countryInfo.txt (tab-separated, '#' comment lines).
 * Licence: CC BY 4.0 — see database/data/geonames/README.md.
 */
class CountryInfoReader
{
    /**
     * @return list<array{iso2: string, iso3: string, name: string, capital: string, currency: string|null, geonames_id: int|null}>
     */
    public function read(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("GeoNames country file not readable: {$path}");
        }

        $rows = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            $columns = explode("\t", $line);
            if (count($columns) < 17 || ! preg_match('/^[A-Z]{2}$/', $columns[0])) {
                continue;
            }

            $rows[] = [
                'iso2' => $columns[0],
                'iso3' => $columns[1],
                'name' => trim($columns[4]),
                'capital' => trim($columns[5]),
                'currency' => preg_match('/^[A-Z]{3}$/', $columns[10]) ? $columns[10] : null,
                'geonames_id' => ctype_digit($columns[16]) ? (int) $columns[16] : null,
            ];
        }

        return $rows;
    }
}
