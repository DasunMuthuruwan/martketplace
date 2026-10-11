<?php

namespace Database\Factories;

use App\Models\MeasurementUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeasurementUnit> */
class MeasurementUnitFactory extends Factory
{
    public function definition(): array
    {
        $code = fake()->unique()->lexify('unit_????');

        return [
            'code' => $code,
            'name' => ['en' => $code],
            'symbol' => $code,
            'dimension' => MeasurementUnit::AREA,
            'factor_to_base' => '1',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
