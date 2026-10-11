<?php

namespace Database\Seeders;

use App\Services\Geo\GeoNames\CountryImporter;
use Illuminate\Database\Seeder;

/**
 * Seeds every currency used by a country in the GeoNames snapshot (names, numeric codes and
 * minor units from ICU). Existing rows are never modified, so admin changes survive a re-run.
 */
class CurrencySeeder extends Seeder
{
    public function run(CountryImporter $importer): void
    {
        $importer->importCurrencies(database_path('data/geonames/countryInfo.txt'));
    }
}
