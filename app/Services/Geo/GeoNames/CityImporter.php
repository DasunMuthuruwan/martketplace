<?php

namespace App\Services\Geo\GeoNames;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports populated places (feature class P) for one country from a GeoNames geoname dump:
 * citiesN.txt (all countries, population threshold) or a per-country file such as LK.txt.
 *
 * Re-sync rules (docs/architecture/13-global-marketplace.md §4.3):
 * - matched by GeoNames ID; IDs and slugs never change;
 * - curated cities (fixed by staff) keep their name and region; only population and
 *   coordinates are refreshed;
 * - places below the population threshold or with excluded feature codes are "filtered",
 *   never deactivated; only places absent from the file are deactivated, and only with
 *   $deactivateMissing.
 */
class CityImporter
{
    /** Historical, abandoned, destroyed places and sections of places (better suited as localities). */
    public const DEFAULT_EXCLUDED_FEATURE_CODES = ['PPLH', 'PPLQ', 'PPLW', 'PPLCH', 'PPLX'];

    /**
     * @param  list<string>  $excludedFeatureCodes
     */
    public function import(
        Country $country,
        string $path,
        int $minPopulation = 0,
        bool $deactivateMissing = false,
        array $excludedFeatureCodes = self::DEFAULT_EXCLUDED_FEATURE_CODES,
    ): ImportResult {
        $result = new ImportResult;
        $regions = $this->regionIdsByCode($country);
        $existing = City::where('country_id', $country->id)->whereNotNull('geonames_id')->get()->keyBy('geonames_id');
        $takenSlugs = City::where('country_id', $country->id)->get(['region_id', 'slug'])
            ->mapWithKeys(fn (City $city) => [$city->region_id.'|'.$city->slug => true])
            ->all();
        $seen = [];

        DB::transaction(function () use ($country, $path, $minPopulation, $excludedFeatureCodes, $regions, $existing, &$takenSlugs, &$seen, $result): void {
            foreach (GeoNamesFile::rows($path) as $c) {
                if (count($c) < 15 || $c[8] !== $country->iso2 || $c[6] !== 'P' || ! ctype_digit($c[0])) {
                    continue;
                }

                $geonamesId = (int) $c[0];
                $seen[$geonamesId] = true;

                $population = ctype_digit($c[14]) ? (int) $c[14] : 0;
                if ($population < $minPopulation || in_array($c[7], $excludedFeatureCodes, true)) {
                    $result->filtered++;

                    continue;
                }

                $regionCode = match ($country->region_levels) {
                    2 => "{$country->iso2}.{$c[10]}.{$c[11]}",
                    1 => "{$country->iso2}.{$c[10]}",
                    default => null,
                };
                $regionId = $regionCode === null ? null : ($regions[$regionCode] ?? false);

                if ($regionId === false) {
                    $result->skipped++;
                    $result->note("{$c[1]} ({$geonamesId}): region code {$regionCode} is blank or not imported");

                    continue;
                }

                $attributes = [
                    'name' => trim($c[1]),
                    'ascii_name' => trim($c[2]) !== '' ? trim($c[2]) : Str::ascii(trim($c[1])),
                    'latitude' => is_numeric($c[4]) ? round((float) $c[4], 6) : null,
                    'longitude' => is_numeric($c[5]) ? round((float) $c[5], 6) : null,
                    'population' => $population > 0 ? $population : null,
                ];

                $city = $existing->get($geonamesId);

                if ($city === null) {
                    $slug = UniqueSlug::make($attributes['name'], fn (string $slug) => isset($takenSlugs[$regionId.'|'.$slug]));
                    $takenSlugs[$regionId.'|'.$slug] = true;

                    City::create([
                        'country_id' => $country->id,
                        'region_id' => $regionId,
                        'name' => ['en' => $attributes['name']],
                        'ascii_name' => $attributes['ascii_name'],
                        'slug' => $slug,
                        'latitude' => $attributes['latitude'],
                        'longitude' => $attributes['longitude'],
                        'population' => $attributes['population'],
                        'geonames_id' => $geonamesId,
                        'is_active' => true,
                        'is_curated' => false,
                    ]);
                    $result->created++;

                    continue;
                }

                $this->refresh($city, $attributes, $regionId) ? $result->updated++ : $result->unchanged++;
            }
        });

        if ($deactivateMissing) {
            $missing = $existing->keys()->reject(fn (int $id) => isset($seen[$id]))->values()->all();
            $result->deactivated = City::where('country_id', $country->id)
                ->whereIn('geonames_id', $missing)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        return $result;
    }

    /**
     * @param  array{name: string, ascii_name: string, latitude: float|null, longitude: float|null, population: int|null}  $attributes
     */
    private function refresh(City $city, array $attributes, ?int $regionId): bool
    {
        $city->population = $attributes['population'];
        $city->latitude = $attributes['latitude'] === null ? null : number_format($attributes['latitude'], 6, '.', '');
        $city->longitude = $attributes['longitude'] === null ? null : number_format($attributes['longitude'], 6, '.', '');

        if (! $city->is_curated) {
            $city->ascii_name = $attributes['ascii_name'];
            $city->region_id = $regionId;
            if ($city->getTranslation('name', 'en', false) !== $attributes['name']) {
                $city->setTranslation('name', 'en', $attributes['name']);
            }
        }

        if (! $city->isDirty()) {
            return false;
        }

        $city->save();

        return true;
    }

    /**
     * GeoNames code → region ID for the country's deepest region level.
     *
     * @return array<string, int>
     */
    private function regionIdsByCode(Country $country): array
    {
        if ($country->region_levels === 0) {
            return [];
        }

        return Region::where('country_id', $country->id)
            ->where('level', min($country->region_levels, 2))
            ->whereNotNull('code')
            ->pluck('id', 'code')
            ->all();
    }
}
