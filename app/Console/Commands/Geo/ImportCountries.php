<?php

namespace App\Console\Commands\Geo;

use App\Services\Geo\GeoNames\CountryImporter;

class ImportCountries extends GeoImportCommand
{
    protected $signature = 'geo:import-countries
        {--path= : Folder containing countryInfo.txt (default: the committed snapshot in database/data/geonames)}
        {--sync : Also update name, ISO3 and GeoNames ID of existing countries (activation, labels and settings are never changed)}';

    protected $description = 'Import currencies and countries from GeoNames countryInfo.txt (new countries are inactive)';

    public function handle(CountryImporter $importer): int
    {
        $file = $this->option('path')
            ? rtrim((string) $this->option('path'), '\\/').DIRECTORY_SEPARATOR.'countryInfo.txt'
            : database_path('data/geonames/countryInfo.txt');

        if (! is_file($file)) {
            $this->error("GeoNames file not found: {$file}");

            return self::FAILURE;
        }

        $this->report('Currencies', $importer->importCurrencies($file));
        $this->report('Countries', $importer->importCountries($file, (bool) $this->option('sync')));

        return self::SUCCESS;
    }
}
