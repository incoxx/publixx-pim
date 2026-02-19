<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use Illuminate\Database\Eloquent\Factories\Factory;

class HierarchyNodeFactory extends Factory
{
    protected $model = HierarchyNode::class;

    public function definition(): array
    {
        return [
            'hierarchy_id' => Hierarchy::factory(),
            'name_de' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'path' => '/',
            'depth' => 0,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
