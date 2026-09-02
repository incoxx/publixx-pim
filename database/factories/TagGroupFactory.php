<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TagGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TagGroup>
 */
class TagGroupFactory extends Factory
{
    protected $model = TagGroup::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'technical_name' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name_de' => ucfirst($name),
            'name_en' => ucfirst($name),
            'sort_order' => 0,
        ];
    }
}
