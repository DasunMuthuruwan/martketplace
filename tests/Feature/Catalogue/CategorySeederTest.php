<?php

namespace Tests\Feature\Catalogue;

use App\Enums\ListingType;
use App\Enums\PriceType;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_approved_tree(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame(7, Category::roots()->count());
        $this->assertSame(48, Category::whereNotNull('parent_id')->count());
        $this->assertSame(
            ['vehicles', 'property', 'vehicle-parts-accessories', 'mobile-phones-tablets', 'electronics-computers', 'home-furniture-appliances', 'other-items'],
            Category::roots()->orderBy('sort_order')->pluck('slug')->all(),
        );
        Category::roots()->get()->each(fn (Category $root) => $this->assertNotNull($root->icon, "{$root->slug} needs an icon"));
    }

    public function test_leaves_carry_the_documented_rules(): void
    {
        $this->seed(CategorySeeder::class);
        $bySlug = fn (string $slug) => Category::where('slug', $slug)->firstOrFail();

        $land = $bySlug('land');
        $this->assertSame([PriceType::Fixed, PriceType::Negotiable], $land->priceTypes());
        $this->assertSame([ListingType::Sale, ListingType::Rent, ListingType::Wanted], $land->listingTypes());
        $this->assertFalse($land->show_condition);
        $this->assertSame(10, $land->max_images);

        $this->assertSame(['IN', 'LK'], $bySlug('three-wheelers')->scope_countries);
        $this->assertSame(['IN', 'LK'], $bySlug('three-wheeler-parts')->scope_countries);
        $this->assertNull($bySlug('cars')->scope_countries);
        $this->assertSame(10, $bySlug('cars')->max_images);

        $this->assertSame([ListingType::Sale, ListingType::Wanted], $bySlug('mobile-phones')->listingTypes());
        $this->assertNotContains(PriceType::Free, $bySlug('mobile-phones')->priceTypes());
        $this->assertContains(PriceType::Free, $bySlug('furniture')->priceTypes());
        $this->assertSame([ListingType::Rent, ListingType::Wanted], $bySlug('rooms-annexes')->listingTypes());
    }

    public function test_reseeding_keeps_admin_edits_and_adds_nothing_twice(): void
    {
        $this->seed(CategorySeeder::class);
        Category::where('slug', 'boats')->firstOrFail()->update(['is_active' => false, 'name' => ['en' => 'Boats & Yachts']]);

        $this->seed(CategorySeeder::class);

        $this->assertSame(55, Category::count());
        $boats = Category::where('slug', 'boats')->firstOrFail();
        $this->assertFalse($boats->is_active);
        $this->assertSame('Boats & Yachts', $boats->name);
    }

    public function test_database_seeder_includes_categories(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(55, Category::count());
    }
}
