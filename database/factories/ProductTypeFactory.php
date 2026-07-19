<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductTypeFactory extends Factory
{
    protected $model = ProductType::class;

    public function definition(): array
    {
        return [
            'technical_name' => fake()->unique()->slug(2),
            'name_de' => fake()->words(2, true),
            'name_en' => fake()->words(2, true),
            'has_variants' => false,
            'has_ean' => true,
            'has_prices' => true,
            'has_media' => true,
            'has_stock' => false,
            'has_physical_dimensions' => false,
            'has_dynamic_cluster' => false,
            'allows_free_attributes' => false,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    /**
     * Produkttyp mit aktivierter Cluster-Vererbung (Klammer-Produkt mit
     * dynamisch aufgelösten Mitgliedern + Attribut-/Medien-Vererbung).
     */
    public function dynamicCluster(): static
    {
        return $this->state(fn () => ['has_dynamic_cluster' => true]);
    }

    /**
     * Produkttyp, dessen Produkte zusätzlich zu evtl. Hierarchie-Attributen
     * frei beliebige Attribute aus dem Katalog zugeordnet bekommen können.
     */
    public function freeAttributes(): static
    {
        return $this->state(fn () => ['allows_free_attributes' => true]);
    }
}
