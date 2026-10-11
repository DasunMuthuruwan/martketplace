<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'numeric_code' => (string) fake()->numberBetween(100, 999),
            'name' => ['en' => fake()->words(2, true)],
            'minor_units' => 2,
            'symbol_override' => null,
            'display_decimals' => 'auto',
            'is_active' => true,
        ];
    }
}
