<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A text attribute by default; see the type helpers.
 *
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    public function definition(): array
    {
        $label = Str::title(fake()->unique()->words(2, true));

        return [
            'key' => Str::snake(Str::lower($label)).'_'.Str::lower(Str::random(4)),
            'label' => ['en' => $label],
            'type' => AttributeType::Text,
            'unit' => null,
            'rules' => null,
            'depends_on_attribute_id' => null,
            'allows_other' => false,
            'help_text' => null,
            'is_active' => true,
        ];
    }

    public function ofType(AttributeType $type, ?array $rules = null): static
    {
        return $this->state(['type' => $type, 'rules' => $rules]);
    }

    /** A select with the given option values (labels = values in title case). */
    public function select(array $values = ['a', 'b'], bool $allowsOther = false): static
    {
        return $this->state(['type' => AttributeType::Select, 'allows_other' => $allowsOther])
            ->afterCreating(function (Attribute $attribute) use ($values): void {
                foreach ($values as $order => $value) {
                    AttributeOption::create([
                        'attribute_id' => $attribute->id,
                        'value' => $value,
                        'label' => ['en' => Str::title($value)],
                        'sort_order' => $order,
                    ]);
                }
            });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
