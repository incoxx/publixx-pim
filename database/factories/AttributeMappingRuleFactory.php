<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeMappingRule;
use App\Models\Hierarchy;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeMappingRuleFactory extends Factory
{
    protected $model = AttributeMappingRule::class;

    public function definition(): array
    {
        return [
            'output_hierarchy_id' => Hierarchy::factory(),
            'name' => fake()->sentence(3),
            'condition_attribute_id' => Attribute::factory(),
            'condition_operator' => '=',
            'condition_value' => fake()->word(),
            'actions' => [
                [
                    'target_attribute_id' => fake()->uuid(),
                    'value' => fake()->word(),
                    'value_type' => 'static',
                ],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
