<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' Region';

        return [
            'country_id' => Country::factory(),
            'parent_id' => null,
            'level' => 1,
            'name' => ['en' => $name],
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** Level-2 region (district/county) under the given level-1 region, in the same country. */
    public function childOf(Region $parent): static
    {
        return $this->state([
            'country_id' => $parent->country_id,
            'parent_id' => $parent->id,
            'level' => 2,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
