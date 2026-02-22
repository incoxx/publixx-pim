<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MediaUsageType;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaUsageTypeFactory extends Factory
{
    protected $model = MediaUsageType::class;

    public function definition(): array
    {
        return [
            'technical_name' => $this->faker->unique()->slug(2),
            'name_de' => $this->faker->words(2, true),
            'name_en' => $this->faker->words(2, true),
            'sort_order' => 0,
        ];
    }
}
