<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductRelationFactory extends Factory
{
    protected $model = ProductRelation::class;

    public function definition(): array
    {
        return [
            'source_product_id' => Product::factory(),
            'target_product_id' => Product::factory(),
            'relation_type_id' => ProductRelationType::factory(),
            'sort_order' => 0,
        ];
    }
}
