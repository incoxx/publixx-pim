<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'product_type_id' => ProductType::factory(),
            'sku' => fake()->unique()->bothify('??-###-###'),
            'ean' => fake()->ean13(),
            'name' => fake()->sentence(4),
            'status' => 'draft',
            'product_type_ref' => 'product',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function variant(): static
    {
        return $this->state(fn () => ['product_type_ref' => 'variant']);
    }
}
