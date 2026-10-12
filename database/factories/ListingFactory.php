<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A public listing by default (active, published, not expired) in a leaf category of a fresh
 * country, priced in that country's currency.
 *
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'country_id' => Country::factory(), // before currency, which reads it
            'category_id' => fn () => Category::factory()->childOf(Category::factory()->create())->create()->id,
            'type' => 'sale',
            'status' => ListingStatus::Active,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'price' => (string) fake()->numberBetween(1000, 50000000),
            'currency' => fn (array $attributes) => Country::findOrFail($attributes['country_id'])->default_currency,
            'price_type' => 'fixed',
            'condition' => 'used',
            'published_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function status(ListingStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function draft(): static
    {
        return $this->state(['status' => ListingStatus::Draft, 'published_at' => null, 'expires_at' => null]);
    }

    public function expired(): static
    {
        return $this->state(['published_at' => now()->subDays(40), 'expires_at' => now()->subDays(10)]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(['category_id' => $category->id]);
    }

    public function inCountry(Country $country): static
    {
        return $this->state(['country_id' => $country->id, 'currency' => $country->default_currency]);
    }
}
