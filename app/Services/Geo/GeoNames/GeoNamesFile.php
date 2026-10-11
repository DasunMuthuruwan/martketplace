<?php

namespace App\Services\Geo\GeoNames;

use Generator;
use RuntimeException;

/**
 * Streams a GeoNames tab-separated dump line by line (files can exceed 100 MB).
 * Licence: CC BY 4.0 — see database/data/geonames/README.md.
 */
class GeoNamesFile
{
    /**
     * @return Generator<int, list<string>>
     */
    public static function rows(string $path): Generator
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("GeoNames file not found or not readable: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open GeoNames file: {$path}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                yield explode("\t", $line);
            }
        } finally {
            fclose($handle);
        }
    }
}
