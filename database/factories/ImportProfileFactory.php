<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImportProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportProfileFactory extends Factory
{
    protected $model = ImportProfile::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'user_id' => User::factory(),
            'is_shared' => false,
            'sku_column' => 'SKU',
            'column_mappings' => [],
        ];
    }

    public function shared(): static
    {
        return $this->state(fn () => ['is_shared' => true]);
    }
}
