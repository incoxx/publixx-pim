<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SearchProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class SearchProfileFactory extends Factory
{
    protected $model = SearchProfile::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'is_shared' => false,
            'search_mode' => 'like',
            'include_descendants' => true,
            'sort_field' => 'updated_at',
            'sort_order' => 'desc',
        ];
    }
}
