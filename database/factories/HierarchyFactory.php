<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hierarchy;
use Illuminate\Database\Eloquent\Factories\Factory;

class HierarchyFactory extends Factory
{
    protected $model = Hierarchy::class;

    public function definition(): array
    {
        return [
            'technical_name' => fake()->unique()->slug(2),
            'name_de' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'hierarchy_type' => 'master',
        ];
    }

    public function output(): static
    {
        return $this->state(fn () => ['hierarchy_type' => 'output']);
    }
}
