<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProductType;
use Illuminate\Database\Seeder;

class ProductTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'technical_name' => 'physical_product',
                'name_de' => 'Physisches Produkt',
                'name_en' => 'Physical Product',
                'icon' => 'Package',
                'color' => '#3B82F6',
                'has_variants' => true,
                'has_ean' => true,
                'has_prices' => true,
                'has_media' => true,
                'has_stock' => true,
                'has_physical_dimensions' => true,
                'sort_order' => 10,
            ],
            [
                'technical_name' => 'training',
                'name_de' => 'Schulung',
                'name_en' => 'Training',
                'icon' => 'GraduationCap',
                'color' => '#10B981',
                'has_variants' => false,
                'has_ean' => false,
                'has_prices' => true,
                'has_media' => true,
                'has_stock' => false,
                'has_physical_dimensions' => false,
                'sort_order' => 20,
            ],
            [
                'technical_name' => 'service',
                'name_de' => 'Dienstleistung',
                'name_en' => 'Service',
                'icon' => 'Wrench',
                'color' => '#F59E0B',
                'has_variants' => false,
                'has_ean' => false,
                'has_prices' => true,
                'has_media' => false,
                'has_stock' => false,
                'has_physical_dimensions' => false,
                'sort_order' => 30,
            ],
            [
                'technical_name' => 'software',
                'name_de' => 'Software',
                'name_en' => 'Software',
                'icon' => 'Code',
                'color' => '#8B5CF6',
                'has_variants' => true,
                'has_ean' => false,
                'has_prices' => true,
                'has_media' => true,
                'has_stock' => false,
                'has_physical_dimensions' => false,
                'sort_order' => 40,
            ],
            [
                'technical_name' => 'bundle',
                'name_de' => 'Bundle',
                'name_en' => 'Bundle',
                'icon' => 'Boxes',
                'color' => '#EF4444',
                'has_variants' => false,
                'has_ean' => true,
                'has_prices' => true,
                'has_media' => true,
                'has_stock' => true,
                'has_physical_dimensions' => true,
                'sort_order' => 50,
            ],
            [
                'technical_name' => 'digital_asset',
                'name_de' => 'Digitales Asset',
                'name_en' => 'Digital Asset',
                'icon' => 'FileDigit',
                'color' => '#06B6D4',
                'has_variants' => false,
                'has_ean' => false,
                'has_prices' => true,
                'has_media' => true,
                'has_stock' => false,
                'has_physical_dimensions' => false,
                'sort_order' => 60,
            ],
        ];

        foreach ($types as $type) {
            ProductType::firstOrCreate(
                ['technical_name' => $type['technical_name']],
                $type
            );
        }
    }
}
