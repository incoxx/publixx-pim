<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'file_name' => fake()->uuid() . '.jpg',
            'file_path' => 'media/' . fake()->uuid() . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(10000, 5000000),
            'media_type' => 'image',
            'title_de' => fake()->sentence(3),
            'title_en' => fake()->sentence(3),
            'width' => fake()->numberBetween(400, 4000),
            'height' => fake()->numberBetween(300, 3000),
        ];
    }
}
