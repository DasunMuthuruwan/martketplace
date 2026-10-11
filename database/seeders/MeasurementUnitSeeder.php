<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\MeasurementUnit;
use App\Services\Catalogue\CatalogueCache;
use Illuminate\Database\Seeder;

/**
 * Seeds area units and the G-6 country defaults (database/data/measurement_units.php). Run after
 * CountrySeeder. Existing units are not modified, and a country that already has units or a
 * "price per" unit is left alone, so admin changes survive a re-run.
 */
class MeasurementUnitSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{units: list<array{code: string, name: string, symbol: string, factor: string, sort: int}>, countries: array<string, array{units: list<string>, land_price_unit: string|null}>} $data */
        $data = require database_path('data/measurement_units.php');

        $units = [];
        foreach ($data['units'] as $unit) {
            $units[$unit['code']] = MeasurementUnit::firstOrCreate(['code' => $unit['code']], [
                'name' => ['en' => $unit['name']],
                'symbol' => $unit['symbol'],
                'dimension' => MeasurementUnit::AREA,
                'factor_to_base' => $unit['factor'],
                'sort_order' => $unit['sort'],
            ]);
        }

        foreach ($data['countries'] as $iso2 => $config) {
            $country = Country::where('iso2', $iso2)->first();
            if ($country === null) {
                continue;
            }

            if (! $country->areaUnits()->exists()) {
                foreach ($config['units'] as $order => $code) {
                    $country->areaUnits()->attach($units[$code]->id, ['is_default' => $order === 0, 'sort_order' => $order + 1]);
                }
            }

            if ($country->land_price_unit_id === null && $config['land_price_unit'] !== null) {
                $country->forceFill(['land_price_unit_id' => $units[$config['land_price_unit']]->id])->save();
            }
        }

        // DatabaseSeeder mutes model events.
        CatalogueCache::flush();
    }
}
