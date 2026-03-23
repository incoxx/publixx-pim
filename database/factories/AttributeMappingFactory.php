<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeMapping;
use App\Models\Hierarchy;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeMappingFactory extends Factory
{
    protected $model = AttributeMapping::class;

    public function definition(): array
    {
        return [
            'source_hierarchy_id' => Hierarchy::factory(),
            'source_attribute_id' => Attribute::factory(),
            'target_hierarchy_id' => Hierarchy::factory(),
            'target_attribute_id' => Attribute::factory(),
            'transform_type' => 'direct',
            'transform_config' => null,
            'ai_suggested' => false,
            'ai_confidence' => null,
        ];
    }

    public function aiSuggested(float $confidence = 0.85): static
    {
        return $this->state(fn () => [
            'ai_suggested' => true,
            'ai_confidence' => $confidence,
        ]);
    }

    public function unitConvert(string $fromUnit, string $toUnit, float $factor): static
    {
        return $this->state(fn () => [
            'transform_type' => 'unit_convert',
            'transform_config' => [
                'from_unit' => $fromUnit,
                'to_unit' => $toUnit,
                'factor' => $factor,
            ],
        ]);
    }

    public function valueMap(array $mapping): static
    {
        return $this->state(fn () => [
            'transform_type' => 'value_map',
            'transform_config' => ['mapping' => $mapping],
        ]);
    }
}
