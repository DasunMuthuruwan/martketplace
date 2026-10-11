<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Services\Catalogue\CatalogueCache;
use Illuminate\Database\Seeder;

/**
 * Seeds the approved category tree (database/data/categories.php). Categories are matched by
 * slug and only created when missing, so admin edits (names, order, deactivation) survive a re-run.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{slug: string, name: string, icon?: string, settings?: array<string, mixed>, children: list<array<string, mixed>>}> $tree */
        $tree = require database_path('data/categories.php');

        foreach ($tree as $rootOrder => $root) {
            $settings = $root['settings'] ?? [];
            $parent = $this->firstOrCreate($root, null, $settings, $rootOrder + 1);

            foreach ($root['children'] as $childOrder => $child) {
                $this->firstOrCreate($child, $parent->id, array_merge($settings, $child['settings'] ?? []), $childOrder + 1);
            }
        }

        // DatabaseSeeder mutes model events, so flush the cached trees explicitly.
        CatalogueCache::flush();
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $settings
     */
    private function firstOrCreate(array $node, ?int $parentId, array $settings, int $sortOrder): Category
    {
        return Category::firstOrCreate(['slug' => $node['slug']], array_merge([
            'allow_free' => false,
            'allowed_price_types' => null,
            'scope_countries' => null,
        ], $settings, [
            'parent_id' => $parentId,
            'name' => ['en' => $node['name']],
            'icon' => $node['icon'] ?? null,
            'sort_order' => $sortOrder,
        ]));
    }
}
