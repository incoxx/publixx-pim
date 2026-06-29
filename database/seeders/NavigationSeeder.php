<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Navigation;
use Illuminate\Database\Seeder;

/**
 * Legt eine primäre Standard-Navigation ("main") an, in die Content-Seiten
 * und Produktkategorien einsortiert werden können.
 */
class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        Navigation::updateOrCreate(
            ['technical_name' => 'main'],
            [
                'name_de' => 'Hauptnavigation',
                'name_en' => 'Main Navigation',
                'locale_set' => ['de', 'en'],
                'is_primary' => true,
            ],
        );
    }
}
