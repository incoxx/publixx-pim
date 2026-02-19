<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImportJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'file_name' => fake()->uuid() . '.xlsx',
            'file_path' => 'imports/' . fake()->uuid() . '.xlsx',
            'status' => 'uploaded',
        ];
    }
}
