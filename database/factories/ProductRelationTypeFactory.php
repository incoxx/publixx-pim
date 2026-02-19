<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductRelationType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductRelationTypeFactory extends Factory
{
    protected $model = ProductRelationType::class;

    public function definition(): array
    {
        return [
            'technical_name' => fake()->unique()->slug(2),
            'name_de' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'is_bidirectional' => false,
        ];
    }
}
