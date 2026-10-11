<?php

namespace Tests\Feature\Catalogue;

use App\Enums\ListingType;
use App\Enums\PriceMode;
use App\Enums\PriceType;
use App\Exceptions\InvalidCategoryTree;
use App\Models\Category;
use App\Models\SlugRedirect;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_level_tree_with_leaves(): void
    {
        $vehicles = Category::factory()->create();
        $cars = Category::factory()->childOf($vehicles)->create(['sort_order' => 2]);
        $vans = Category::factory()->childOf($vehicles)->create(['sort_order' => 1]);

        $this->assertTrue($vehicles->isRoot());
        $this->assertFalse($vehicles->isLeaf());
        $this->assertTrue($cars->isLeaf());
        $this->assertTrue($cars->parent->is($vehicles));
        $this->assertSame([$vans->id, $cars->id], $vehicles->children->pluck('id')->all(), 'Ordered by sort_order');
        $this->assertSame([$vehicles->id], Category::roots()->pluck('id')->all());
    }

    public function test_a_third_level_is_rejected(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->childOf($root)->create();

        $this->expectException(InvalidCategoryTree::class);
        Category::factory()->childOf($child)->create();
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $root = Category::factory()->create();

        $this->expectException(InvalidCategoryTree::class);
        $root->update(['parent_id' => $root->id]);
    }

    public function test_a_category_with_subcategories_cannot_become_a_subcategory(): void
    {
        $first = Category::factory()->create();
        Category::factory()->childOf($first)->create();
        $second = Category::factory()->create();

        $this->expectException(InvalidCategoryTree::class);
        $first->update(['parent_id' => $second->id]);
    }

    public function test_a_category_with_subcategories_cannot_be_deleted(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->childOf($root)->create();

        try {
            $root->delete();
            $this->fail('Deleting a parent should be blocked');
        } catch (InvalidCategoryTree) {
            $this->assertModelExists($root);
        }

        $child->delete();
        $root->delete();
        $this->assertModelMissing($root);
    }

    public function test_database_also_blocks_deleting_a_parent(): void
    {
        $root = Category::factory()->create();
        Category::factory()->childOf($root)->create();

        $this->expectException(QueryException::class);
        Category::query()->whereKey($root->id)->delete(); // bypasses model events
    }

    public function test_slug_is_generated_from_the_english_name_and_kept_unique(): void
    {
        $first = Category::factory()->create(['name' => ['en' => 'Garden Tools'], 'slug' => null]);
        $second = Category::factory()->create(['name' => ['en' => 'Garden Tools'], 'slug' => null]);

        $this->assertSame('garden-tools', $first->slug);
        $this->assertSame('garden-tools-2', $second->slug);
    }

    public function test_renaming_a_slug_keeps_old_urls_working(): void
    {
        $category = Category::factory()->create(['slug' => 'mobile-phones']);

        $category->update(['slug' => 'phones']);
        $category->update(['slug' => 'smartphones']);

        $this->assertTrue(Category::findBySlugOrRedirect('mobile-phones')->is($category));
        $this->assertTrue(Category::findBySlugOrRedirect('phones')->is($category));
        $this->assertTrue(Category::findBySlugOrRedirect('smartphones')->is($category));
        $this->assertNull(Category::findBySlugOrRedirect('nothing-here'));
    }

    public function test_reusing_an_old_slug_removes_its_redirect(): void
    {
        $old = Category::factory()->create(['slug' => 'tablets']);
        $old->update(['slug' => 'tablets-old']);

        $new = Category::factory()->create(['slug' => 'tablets']);

        $this->assertTrue(Category::findBySlugOrRedirect('tablets')->is($new));
        $this->assertSame(0, SlugRedirect::where('old_slug', 'tablets')->count());
    }

    public function test_deleting_a_category_removes_its_redirects(): void
    {
        $category = Category::factory()->create(['slug' => 'boats']);
        $category->update(['slug' => 'boats-and-yachts']);

        $category->delete();

        $this->assertSame(0, SlugRedirect::count());
    }

    public function test_country_scope_is_normalised(): void
    {
        $category = Category::factory()->create(['scope_countries' => ['lk', ' in ', 'LK']]);
        $this->assertSame(['IN', 'LK'], $category->fresh()->scope_countries);

        $category->update(['scope_countries' => []]);
        $this->assertNull($category->fresh()->scope_countries, 'Empty list means every country');
    }

    public function test_country_scope_query_and_check(): void
    {
        $global = Category::factory()->create();
        $scoped = Category::factory()->scopedTo(['IN', 'LK'])->create();

        $this->assertEqualsCanonicalizing([$global->id, $scoped->id], Category::inScopeFor('lk')->pluck('id')->all());
        $this->assertSame([$global->id], Category::inScopeFor('US')->pluck('id')->all());
        $this->assertTrue($scoped->isInScopeFor('in'));
        $this->assertFalse($scoped->isInScopeFor('GB'));
    }

    public function test_availability_needs_the_parent_active_and_in_scope(): void
    {
        $parent = Category::factory()->scopedTo(['LK'])->create();
        $leaf = Category::factory()->childOf($parent)->create();

        $this->assertTrue($leaf->isAvailableIn('LK'));
        $this->assertFalse($leaf->isAvailableIn('US'), 'Parent out of scope');

        $parent->update(['is_active' => false]);
        $this->assertFalse($leaf->fresh()->isAvailableIn('LK'), 'Parent inactive');
    }

    public function test_listing_types_are_validated_and_stored_in_a_stable_order(): void
    {
        $category = Category::factory()->create(['allowed_types' => ['wanted', 'sale', 'sale']]);

        $this->assertSame(['sale', 'wanted'], $category->fresh()->allowed_types);
        $this->assertSame([ListingType::Sale, ListingType::Wanted], $category->listingTypes());
        $this->assertTrue($category->allowsListingType(ListingType::Wanted));
        $this->assertFalse($category->allowsListingType(ListingType::Rent));
    }

    public function test_unknown_or_empty_listing_types_are_rejected(): void
    {
        try {
            Category::factory()->create(['allowed_types' => ['sale', 'auction']]);
            $this->fail('Unknown type accepted');
        } catch (InvalidCategoryTree) {
        }

        $this->expectException(InvalidCategoryTree::class);
        Category::factory()->create(['allowed_types' => []]);
    }

    public function test_price_type_rules(): void
    {
        $land = Category::factory()->create(['allowed_price_types' => ['negotiable', 'fixed'], 'allow_free' => true]);
        $furniture = Category::factory()->create(['allow_free' => true]);
        $phones = Category::factory()->create(['allow_free' => false]);
        $noPrice = Category::factory()->create(['price_mode' => PriceMode::None]);

        $this->assertSame([PriceType::Fixed, PriceType::Negotiable], $land->priceTypes(), 'Land: no free / on request');
        $this->assertSame(PriceType::cases(), $furniture->priceTypes());
        $this->assertSame([PriceType::Fixed, PriceType::Negotiable, PriceType::OnRequest], $phones->priceTypes());
        $this->assertSame([], $noPrice->priceTypes());
    }

    public function test_name_is_translatable_with_english_fallback(): void
    {
        $category = Category::factory()->create(['name' => ['en' => 'Cars', 'si' => 'මෝටර් රථ']]);

        $this->assertSame('Cars', $category->getTranslation('name', 'en'));
        $this->assertSame('මෝටර් රථ', $category->getTranslation('name', 'si'));
        $this->assertSame('Cars', $category->getTranslation('name', 'ta', true));
    }
}
