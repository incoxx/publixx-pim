<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExportProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExportProfileFactory extends Factory
{
    protected $model = ExportProfile::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'user_id' => User::factory(),
            'is_shared' => false,
            'include_products' => true,
            'include_attributes' => true,
            'include_hierarchies' => false,
            'include_prices' => false,
            'include_relations' => false,
            'include_media' => false,
            'include_variants' => false,
            'attribute_ids' => [],
            'languages' => ['de', 'en'],
            'format' => 'excel',
            'file_name_template' => 'export_{date}',
        ];
    }

    public function shared(): static
    {
        return $this->state(fn () => ['is_shared' => true]);
    }
}
