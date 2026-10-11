<?php

namespace App\Services\Catalogue;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The category tree as offered in one country: active top-level categories in scope, each with
 * its active, in-scope subcategories. Top-level categories left without subcategories are
 * dropped (nothing could be posted there). Cached; any category change flushes the cache.
 */
class CategoryTree
{
    private const VERSION_KEY = 'catalogue:category-tree:version';

    /**
     * @param  string|null  $iso2  ISO 3166-1 alpha-2, or null for the global tree (all active categories, any scope)
     * @return Collection<int, Category>
     */
    public function forCountry(?string $iso2): Collection
    {
        $key = 'catalogue:category-tree:'.self::version().':'.($iso2 === null ? 'all' : strtoupper($iso2));

        return Cache::rememberForever($key, fn () => $this->build($iso2));
    }

    /** Invalidate every cached tree (called by Category model events). */
    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, Str::random(12));
    }

    private static function version(): string
    {
        return (string) Cache::rememberForever(self::VERSION_KEY, fn () => Str::random(12));
    }

    /** @return Collection<int, Category> */
    private function build(?string $iso2): Collection
    {
        $scoped = fn ($query) => $iso2 === null ? $query : $query->inScopeFor($iso2);

        $roots = Category::query()
            ->roots()
            ->active()
            ->tap($scoped)
            ->with(['children' => fn ($query) => $scoped($query->active())])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $roots->filter(fn (Category $root) => $root->children->isNotEmpty())->values();
    }
}
