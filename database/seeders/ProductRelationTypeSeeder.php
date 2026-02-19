<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProductRelationType;
use Illuminate\Database\Seeder;

class ProductRelationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'technical_name' => 'spare_part',
                'name_de' => 'Ersatzteil',
                'name_en' => 'Spare Part',
                'name_json' => json_encode(['de' => 'Ersatzteil', 'en' => 'Spare Part']),
                'is_bidirectional' => false,
            ],
            [
                'technical_name' => 'accessory',
                'name_de' => 'Zubehör',
                'name_en' => 'Accessory',
                'name_json' => json_encode(['de' => 'Zubehör', 'en' => 'Accessory']),
                'is_bidirectional' => true,
            ],
            [
                'technical_name' => 'recommendation',
                'name_de' => 'Empfehlung',
                'name_en' => 'Recommendation',
                'name_json' => json_encode(['de' => 'Empfehlung', 'en' => 'Recommendation']),
                'is_bidirectional' => true,
            ],
        ];

        foreach ($types as $type) {
            ProductRelationType::firstOrCreate(
                ['technical_name' => $type['technical_name']],
                $type,
            );
        }
    }
}
