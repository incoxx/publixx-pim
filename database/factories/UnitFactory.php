<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unit;
use App\Models\UnitGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'unit_group_id' => UnitGroup::factory(),
            'technical_name' => fake()->unique()->slug(1),
            'abbreviation' => fake()->lexify('??'),
            'conversion_factor' => 1,
            'is_base_unit' => true,
            'is_translatable' => false,
        ];
    }
}
