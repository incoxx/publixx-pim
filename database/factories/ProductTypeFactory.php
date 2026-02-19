<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductTypeFactory extends Factory
{
    protected $model = ProductType::class;

    public function definition(): array
    {
        return [
            'technical_name' => fake()->unique()->slug(2),
            'name_de' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'has_variants' => false,
            'has_ean' => true,
            'has_prices' => true,
            'has_media' => true,
            'has_stock' => false,
            'has_physical_dimensions' => false,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
