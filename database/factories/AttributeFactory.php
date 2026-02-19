<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    public function definition(): array
    {
        return [
            'technical_name' => fake()->unique()->slug(3),
            'name_de' => fake()->words(3, true),
            'name_en' => fake()->words(3, true),
            'data_type' => fake()->randomElement(['String', 'Number', 'Float', 'Date', 'Flag', 'Selection']),
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_country_specific' => false,
            'is_inheritable' => true,
            'status' => 'active',
            'position' => fake()->numberBetween(0, 200),
        ];
    }

    public function translatable(): static
    {
        return $this->state(fn () => ['is_translatable' => true, 'data_type' => 'String']);
    }

    public function numeric(): static
    {
        return $this->state(fn () => ['data_type' => 'Number', 'max_pre_decimal' => 10, 'max_post_decimal' => 2]);
    }
}
