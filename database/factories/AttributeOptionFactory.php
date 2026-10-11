<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AttributeOption> */
class AttributeOptionFactory extends Factory
{
    public function definition(): array
    {
        $label = Str::title(fake()->unique()->word());

        return [
            'attribute_id' => Attribute::factory()->select([]),
            'parent_option_id' => null,
            'value' => Str::slug($label),
            'label' => ['en' => $label],
            'sort_order' => 0,
            'is_active' => true,
            'scope_countries' => null,
        ];
    }
}
