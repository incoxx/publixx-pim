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
            DemoAttributeSeeder::class,
            DemoHierarchySeeder::class,
            DemoProductSeeder::class,
        ]);
    }
}
