<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkflowStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowStatusFactory extends Factory
{
    protected $model = WorkflowStatus::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
