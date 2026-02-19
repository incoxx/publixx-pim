<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UnitGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitGroupFactory extends Factory
{
    protected $model = UnitGroup::class;

    public function definition(): array
    {
        return [
            'technical_name' => fake()->unique()->slug(2),
            'name_de' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
        ];
    }
}
