<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A top-level category by default; use childOf() for a leaf.
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'name' => ['en' => $name],
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'icon' => null,
            'sort_order' => 0,
            'is_active' => true,
            'scope_countries' => null,
            'allowed_types' => ['sale', 'wanted'],
            'price_mode' => 'required',
            'allow_free' => false,
            'allowed_price_types' => null,
            'show_condition' => true,
            'max_images' => 8,
            'moderation' => 'inherit',
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** @param  list<string>  $iso2 */
    public function scopedTo(array $iso2): static
    {
        return $this->state(['scope_countries' => $iso2]);
    }
}
