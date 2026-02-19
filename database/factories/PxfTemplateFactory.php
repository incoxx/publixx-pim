<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PxfTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class PxfTemplateFactory extends Factory
{
    protected $model = PxfTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'version' => '1.0',
            'orientation' => 'a4hoch',
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
