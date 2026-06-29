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
            NavigationSeeder::class,
            DemoAttributeSeeder::class,
            DemoHierarchySeeder::class,
            DemoProductSeeder::class,
            DemoMediaSeeder::class,
            EtimDemoSeeder::class,
        ]);
    }
}
