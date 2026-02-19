<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class ValueListEntryFactory extends Factory
{
    protected $model = ValueListEntry::class;

    public function definition(): array
    {
        return [
            'value_list_id' => ValueList::factory(),
            'technical_name' => fake()->unique()->slug(1),
            'display_value_de' => fake()->word(),
            'display_value_en' => fake()->word(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
