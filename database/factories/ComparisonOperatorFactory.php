<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ComparisonOperator;
use App\Models\ComparisonOperatorGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComparisonOperatorFactory extends Factory
{
    protected $model = ComparisonOperator::class;

    public function definition(): array
    {
        return [
            'group_id' => ComparisonOperatorGroup::factory(),
            'technical_name' => fake()->unique()->slug(1),
            'symbol' => fake()->randomElement(['=', '<', '>', '≤', '≥', '≈']),
            'description_de' => fake()->word(),
        ];
    }
}
