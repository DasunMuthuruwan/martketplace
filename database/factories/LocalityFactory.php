<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Locality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Locality>
 */
class LocalityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->streetName();

        return [
            'city_id' => City::factory(),
            'name' => ['en' => $name],
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
