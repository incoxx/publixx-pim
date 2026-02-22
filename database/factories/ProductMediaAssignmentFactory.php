<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\Product;
use App\Models\ProductMediaAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductMediaAssignmentFactory extends Factory
{
    protected $model = ProductMediaAssignment::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'media_id' => Media::factory(),
            'usage_type_id' => MediaUsageType::factory(),
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }
}
