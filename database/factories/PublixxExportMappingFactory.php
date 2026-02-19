<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PublixxExportMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublixxExportMappingFactory extends Factory
{
    protected $model = PublixxExportMapping::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'include_media' => true,
            'include_prices' => true,
            'include_variants' => false,
            'include_relations' => false,
            'languages' => ['de', 'en'],
            'flatten_mode' => 'flat',
        ];
    }
}
