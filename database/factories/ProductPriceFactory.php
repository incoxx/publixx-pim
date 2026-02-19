<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductPriceFactory extends Factory
{
    protected $model = ProductPrice::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price_type_id' => PriceType::factory(),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'currency' => 'EUR',
            'valid_from' => now()->toDateString(),
        ];
    }
}
