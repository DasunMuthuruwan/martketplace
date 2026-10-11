<?php

namespace App\Console\Commands\Geo;

use App\Services\Geo\GeoNames\CityImporter;

class ImportCities extends GeoImportCommand
{
    protected $signature = 'geo:import-cities
        {country : ISO 3166-1 alpha-2 code, e.g. LK}
        {--file=cities1000.txt : GeoNames geoname dump: citiesN.txt or a per-country file such as LK.txt}
        {--path= : Folder containing the file (default: GEONAMES_PATH)}
        {--min-population=0 : Skip places with a smaller population (they are never deactivated)}
        {--deactivate-missing : Deactivate cities no longer present in the file (never deletes)}';

    protected $description = 'Import a country\'s cities and towns from a GeoNames dump (import regions first)';

    public function handle(CityImporter $importer): int
    {
        $country = $this->resolveCountry((string) $this->argument('country'));
        $file = $this->resolveFile((string) $this->option('file'));
        if ($country === null || $file === null) {
            return self::FAILURE;
        }

        $minPopulation = filter_var($this->option('min-population'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($minPopulation === false) {
            $this->error('--min-population must be a whole number of 0 or more.');

            return self::FAILURE;
        }

        $result = $importer->import($country, $file, $minPopulation, (bool) $this->option('deactivate-missing'));
        $this->report("Cities for {$country->iso2}", $result);

        if ($result->skipped > 0) {
            $this->warn("{$result->skipped} place(s) skipped: their GeoNames region code is blank or not imported (see notes above). "
                ."If regions are not imported yet, run geo:import-regions {$country->iso2} first; otherwise curate these places in Admin → Locations.");
        }

        return self::SUCCESS;
    }
}
