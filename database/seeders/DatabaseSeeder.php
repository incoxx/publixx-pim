<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AdminUserSeeder::class,
            ProductTypeSeeder::class,
            ProductRelationTypeSeeder::class,
            SectionTypeSeeder::class,
            ContentTypeSeeder::class,
            ProductWidgetSeeder::class,
            NavigationSeeder::class,
            DemoAttributeSeeder::class,
            DemoHierarchySeeder::class,
            DemoProductSeeder::class,
            DemoMediaSeeder::class,
            EtimDemoSeeder::class,
            DemoCollectionSeeder::class,
            // Nach den Demo-Daten: vordefinierte Seiten-Templates (referenzieren
            // Demo-Produkte/-Kategorien für die Commerce-Sektionen).
            ContentTemplateSeeder::class,
        ]);
    }
}
