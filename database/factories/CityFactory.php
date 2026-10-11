<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'region_id' => Region::factory(),
            'country_id' => fn (array $attributes) => $attributes['region_id'] !== null
                ? Region::findOrFail($attributes['region_id'])->country_id
                : Country::factory(),
            'name' => ['en' => $name],
            'ascii_name' => Str::ascii($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
            'is_curated' => false,
        ];
    }

    /** City in the given (deepest-level) region and its country. */
    public function inRegion(Region $region): static
    {
        return $this->state(['region_id' => $region->id, 'country_id' => $region->country_id]);
    }

    /** City in a country without regions. */
    public function inCountryWithoutRegions(Country $country): static
    {
        return $this->state(['region_id' => null, 'country_id' => $country->id]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
