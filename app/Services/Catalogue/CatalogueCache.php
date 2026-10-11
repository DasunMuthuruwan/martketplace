<?php

namespace App\Services\Catalogue;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One cache generation for everything derived from the catalogue (category trees, attribute
 * schemas). Any change to categories, attributes, options or their links starts a new generation,
 * so no stale entry is ever served; old entries simply stop being read.
 */
final class CatalogueCache
{
    private const VERSION_KEY = 'catalogue:version';

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::rememberForever('catalogue:'.self::version().':'.$key, $callback);
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, Str::random(12));
    }

    private static function version(): string
    {
        return (string) Cache::rememberForever(self::VERSION_KEY, fn () => Str::random(12));
    }
}
