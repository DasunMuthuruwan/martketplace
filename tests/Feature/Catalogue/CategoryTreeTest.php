<?php

namespace Tests\Feature\Catalogue;

use App\Models\Category;
use App\Services\Catalogue\CategoryTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    private function tree(?string $iso2): array
    {
        return app(CategoryTree::class)->forCountry($iso2)
            ->mapWithKeys(fn (Category $root) => [$root->slug => $root->children->pluck('slug')->all()])
            ->all();
    }

    public function test_tree_respects_activity_and_country_scope(): void
    {
        $vehicles = Category::factory()->create(['slug' => 'vehicles', 'sort_order' => 1]);
        Category::factory()->childOf($vehicles)->create(['slug' => 'cars', 'sort_order' => 1]);
        Category::factory()->childOf($vehicles)->scopedTo(['IN', 'LK'])->create(['slug' => 'three-wheelers', 'sort_order' => 2]);
        Category::factory()->childOf($vehicles)->inactive()->create(['slug' => 'boats', 'sort_order' => 3]);

        $hidden = Category::factory()->inactive()->create(['slug' => 'hidden', 'sort_order' => 2]);
        Category::factory()->childOf($hidden)->create(['slug' => 'hidden-leaf']);

        $local = Category::factory()->scopedTo(['LK'])->create(['slug' => 'local-only', 'sort_order' => 3]);
        Category::factory()->childOf($local)->create(['slug' => 'local-leaf']);

        Category::factory()->create(['slug' => 'empty-root', 'sort_order' => 4]);

        $this->assertSame([
            'vehicles' => ['cars', 'three-wheelers'],
            'local-only' => ['local-leaf'],
        ], $this->tree('lk'));

        $this->assertSame(['vehicles' => ['cars']], $this->tree('US'));

        // Global tree: every active category whatever its scope.
        $this->assertSame([
            'vehicles' => ['cars', 'three-wheelers'],
            'local-only' => ['local-leaf'],
        ], $this->tree(null));
    }

    public function test_tree_is_cached_and_flushed_on_change(): void
    {
        $root = Category::factory()->create(['slug' => 'electronics']);
        $leaf = Category::factory()->childOf($root)->create(['slug' => 'laptops']);

        $this->assertSame(['electronics' => ['laptops']], $this->tree('GB'));

        // A raw query does not fire model events, so the cached tree is still served.
        Category::query()->whereKey($leaf->id)->update(['is_active' => false]);
        $this->assertSame(['electronics' => ['laptops']], $this->tree('GB'));

        // A model change flushes it.
        $leaf->refresh()->update(['name' => ['en' => 'Laptops & Notebooks']]);
        $this->assertSame([], $this->tree('GB'));
    }
}
