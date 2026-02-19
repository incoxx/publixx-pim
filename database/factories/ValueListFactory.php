<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ValueList;
use Illuminate\Database\Eloquent\Factories\Factory;

class ValueListFactory extends Factory
{
    protected $model = ValueList::class;

    public function definition(): array
    {
        return [
            'technical_name' => fake()->unique()->slug(2),
            'name_de' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'value_data_type' => 'String',
            'max_depth' => 1,
        ];
    }
}
