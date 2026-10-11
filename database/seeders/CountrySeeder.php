<?php

namespace Database\Seeders;

use App\Services\Geo\GeoNames\CountryImporter;
use Illuminate\Database\Seeder;

/**
 * Seeds all countries from the GeoNames snapshot, inactive. Run after CurrencySeeder.
 * Existing rows are never modified, so the Owner's activation and label changes survive a re-run.
 */
class CountrySeeder extends Seeder
{
    public function run(CountryImporter $importer): void
    {
        $importer->importCountries(database_path('data/geonames/countryInfo.txt'));
    }
}
