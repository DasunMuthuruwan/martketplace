<?php

namespace App\Console\Commands\Geo;

use App\Models\Country;
use App\Services\Geo\GeoNames\ImportResult;
use Illuminate\Console\Command;

/**
 * Shared helpers for the geo:import-* commands. These run on a developer machine or staging,
 * never automatically in production (docs/architecture/13-global-marketplace.md §4.3).
 */
abstract class GeoImportCommand extends Command
{
    protected function resolveCountry(string $iso2): ?Country
    {
        $country = Country::where('iso2', strtoupper($iso2))->first();

        if ($country === null) {
            $this->error("Unknown country code [{$iso2}]. Import countries first (geo:import-countries).");
        }

        return $country;
    }

    /** A file name inside --path (or the configured import folder), or an absolute path. */
    protected function resolveFile(string $file): ?string
    {
        $path = is_file($file) ? $file : rtrim((string) ($this->option('path') ?: config('marketplace.geo.import_path')), '\\/').DIRECTORY_SEPARATOR.$file;

        if (! is_file($path)) {
            $this->error("GeoNames file not found: {$path}");
            $this->line('Download it from https://download.geonames.org/export/dump/ (CC BY 4.0).');

            return null;
        }

        return $path;
    }

    protected function report(string $what, ImportResult $result): void
    {
        $this->info($what);
        $this->table(['created', 'updated', 'unchanged', 'deactivated', 'skipped', 'filtered'], [array_values($result->counts())]);

        foreach ($result->notes as $note) {
            $this->line("  • {$note}");
        }
    }
}
