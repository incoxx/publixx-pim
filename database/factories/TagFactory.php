<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'technical_name' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name_de' => ucfirst($name),
            'name_en' => ucfirst($name),
            'name_json' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
