<?php

namespace App\Console\Commands\Geo;

use App\Services\Geo\GeoNames\RegionImporter;

class ImportRegions extends GeoImportCommand
{
    protected $signature = 'geo:import-regions
        {country : ISO 3166-1 alpha-2 code, e.g. LK}
        {--path= : Folder with admin1CodesASCII.txt and admin2Codes.txt (default: GEONAMES_PATH)}
        {--deactivate-missing : Deactivate regions no longer present in GeoNames (never deletes)}';

    protected $description = 'Import a country\'s regions from GeoNames (level 2 only when the country has two region levels)';

    public function handle(RegionImporter $importer): int
    {
        $country = $this->resolveCountry((string) $this->argument('country'));
        $admin1 = $this->resolveFile('admin1CodesASCII.txt');
        if ($country === null || $admin1 === null) {
            return self::FAILURE;
        }

        $admin2 = null;
        if ($country->region_levels >= 2) {
            $admin2 = $this->resolveFile('admin2Codes.txt');
            if ($admin2 === null) {
                return self::FAILURE;
            }
        }

        $result = $importer->import($country, $admin1, $admin2, (bool) $this->option('deactivate-missing'));
        $this->report("Regions for {$country->iso2} ({$country->region_levels} level(s))", $result);

        return self::SUCCESS;
    }
}
