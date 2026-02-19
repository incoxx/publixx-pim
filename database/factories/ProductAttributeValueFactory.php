<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductAttributeValueFactory extends Factory
{
    protected $model = ProductAttributeValue::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'attribute_id' => Attribute::factory(),
            'value_string' => fake()->sentence(3),
            'multiplied_index' => 0,
            'is_inherited' => false,
        ];
    }

    public function numeric(): static
    {
        return $this->state(fn () => [
            'value_string' => null,
            'value_number' => fake()->randomFloat(2, 0, 1000),
        ]);
    }

    public function withLanguage(string $lang): static
    {
        return $this->state(fn () => ['language' => $lang]);
    }
}
