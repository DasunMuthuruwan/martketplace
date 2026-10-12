<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Active by default (tests usually need a usable country); use inactive() for the seeded state.
 *
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->country();

        return [
            'iso2' => fake()->unique()->randomElement(self::testIso2Codes()),
            'iso3' => fake()->unique()->regexify('Q[M-Z][A-Z]'),
            'name' => ['en' => $name],
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'default_currency' => Currency::factory(),
            'number_locale' => 'en',
            'phone_country_code' => (string) fake()->numberBetween(1, 999),
            'timezone' => 'UTC',
            'region_levels' => 1,
            'require_region' => true,
            'require_city' => true,
            'is_active' => true,
        ];
    }

    /**
     * ISO 3166 user-assigned codes (QM–QZ, XA–XZ), which no real country uses, so factory
     * countries never clash with explicitly created or seeded ones. XK is left out: GeoNames
     * uses it for Kosovo.
     *
     * @return list<string>
     */
    private static function testIso2Codes(): array
    {
        $codes = array_map(fn (string $letter) => 'Q'.$letter, range('M', 'Z'));
        foreach (range('A', 'Z') as $letter) {
            if ($letter !== 'K') {
                $codes[] = 'X'.$letter;
            }
        }

        return $codes;
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Country $country): void {
            if ($country->default_currency !== null) {
                $country->currencies()->syncWithoutDetaching([$country->default_currency => ['is_default' => true]]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** Country with province → district style hierarchy. */
    public function twoLevels(): static
    {
        return $this->state(['region_levels' => 2]);
    }

    /** Country without administrative regions (e.g. a city-state). */
    public function withoutRegions(): static
    {
        return $this->state(['region_levels' => 0, 'require_region' => false]);
    }

    public function optionalLocation(): static
    {
        return $this->state(['require_region' => false, 'require_city' => false]);
    }

    /**
     * @param  array<string, string>  $labels  level => label, e.g. ['region' => 'Province']
     */
    public function withLabels(array $labels): static
    {
        return $this->state(collect($labels)
            ->mapWithKeys(fn (string $label, string $level) => ["{$level}_label" => ['en' => $label]])
            ->all());
    }
}
