<?php

namespace App\Services\Geo\GeoNames;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Support\Facades\DB;

/**
 * Imports administrative divisions from GeoNames admin1CodesASCII.txt (level 1) and, for
 * countries configured with two region levels, admin2Codes.txt (level 2).
 *
 * Re-sync rules (docs/architecture/13-global-marketplace.md §4.3):
 * - rows are matched by GeoNames ID, then by GeoNames code; IDs never change;
 * - a rename updates the name only; the slug (used in URLs) is kept;
 * - regions missing from the file are deactivated only with $deactivateMissing, never deleted;
 * - deactivated regions are not reactivated automatically (an admin may have disabled them).
 */
class RegionImporter
{
    public function import(Country $country, string $admin1Path, ?string $admin2Path = null, bool $deactivateMissing = false): ImportResult
    {
        $result = new ImportResult;

        DB::transaction(function () use ($country, $admin1Path, $admin2Path, $deactivateMissing, $result): void {
            $seen = $this->importLevel($country, 1, $admin1Path, $result);

            if ($country->region_levels >= 2 && $admin2Path !== null) {
                $seen = array_merge($seen, $this->importLevel($country, 2, $admin2Path, $result));
            } elseif ($country->region_levels >= 2) {
                $result->note('Country has two region levels but no admin2 file was given: level 2 not imported.');
            }

            if ($deactivateMissing) {
                $levels = $country->region_levels >= 2 && $admin2Path !== null ? [1, 2] : [1];
                $result->deactivated = Region::where('country_id', $country->id)
                    ->whereIn('level', $levels)
                    ->whereNotNull('geonames_id')
                    ->whereNotIn('geonames_id', $seen)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });

        return $result;
    }

    /**
     * @return list<int> GeoNames IDs found in the file for this country and level
     */
    private function importLevel(Country $country, int $level, string $path, ImportResult $result): array
    {
        $prefix = $country->iso2.'.';
        $seen = [];

        foreach (GeoNamesFile::rows($path) as $columns) {
            [$code, $name] = [$columns[0] ?? '', trim($columns[1] ?? '')];
            $geonamesId = ctype_digit($columns[3] ?? '') ? (int) $columns[3] : null;

            if (! str_starts_with($code, $prefix) || substr_count($code, '.') !== $level || $name === '') {
                continue;
            }
            if ($geonamesId !== null) {
                $seen[] = $geonamesId;
            }

            $parentId = null;
            if ($level === 2) {
                $parentCode = substr($code, 0, (int) strrpos($code, '.'));
                $parentId = Region::where('country_id', $country->id)->where('level', 1)->where('code', $parentCode)->value('id');

                if ($parentId === null) {
                    $result->skipped++;
                    $result->note("{$code} {$name}: parent {$parentCode} not found");

                    continue;
                }
            }

            $region = ($geonamesId !== null ? Region::where('geonames_id', $geonamesId)->first() : null)
                ?? Region::where('country_id', $country->id)->where('code', $code)->first();

            if ($region === null) {
                Region::create([
                    'country_id' => $country->id,
                    'parent_id' => $parentId,
                    'level' => $level,
                    'name' => ['en' => $name],
                    'slug' => UniqueSlug::make($name, fn (string $slug) => Region::where('country_id', $country->id)->where('slug', $slug)->exists()),
                    'code' => $code,
                    'geonames_id' => $geonamesId,
                    'is_active' => true,
                ]);
                $result->created++;

                continue;
            }

            if ($region->country_id !== $country->id) {
                $result->skipped++;
                $result->note("{$code} {$name}: GeoNames ID {$geonamesId} already belongs to another country");

                continue;
            }

            $region->fill(['code' => $code, 'geonames_id' => $geonamesId, 'level' => $level, 'parent_id' => $parentId]);
            if ($region->getTranslation('name', 'en', false) !== $name) {
                $region->setTranslation('name', 'en', $name);
            }

            if ($region->isDirty()) {
                $region->save();
                $result->updated++;
            } else {
                $result->unchanged++;
            }
        }

        return $seen;
    }
}
