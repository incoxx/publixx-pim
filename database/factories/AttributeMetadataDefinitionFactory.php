<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttributeMetadataDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeMetadataDefinitionFactory extends Factory
{
    protected $model = AttributeMetadataDefinition::class;

    public function definition(): array
    {
        return [
            'technical_name' => fake()->unique()->slug(2),
            'name_de' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'value_type' => 'text',
            'is_required' => false,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Auswahlfeld mit Optionen, z.B. Datenherkunft.
     *
     * @param array<int, string> $options
     */
    public function select(array $options = ['ERP', 'Agentur', 'Marketing']): static
    {
        return $this->state(fn () => [
            'value_type' => 'select',
            'options' => $options,
        ]);
    }
}
