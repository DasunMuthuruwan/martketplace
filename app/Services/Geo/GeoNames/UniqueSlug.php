<?php

namespace App\Services\Geo\GeoNames;

use Closure;
use Illuminate\Support\Str;

/**
 * Builds a URL slug and adds -2, -3… until it is free in the given scope.
 * Slugs are assigned once on creation and never changed by re-imports (URLs stay stable).
 */
class UniqueSlug
{
    /**
     * @param  Closure(string): bool  $taken
     */
    public static function make(string $name, Closure $taken, string $fallback = 'place'): string
    {
        $base = Str::slug(Str::ascii($name)) ?: $fallback;
        $base = Str::limit($base, 110, '');
        $slug = $base;

        for ($i = 2; $taken($slug); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
