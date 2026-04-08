<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'comparison-operator-groups.view',
            'comparison-operator-groups.create',
            'comparison-operator-groups.edit',
            'comparison-operator-groups.delete',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => 'sanctum',
            ]);
        }

        // Rollen-Zuweisung erfolgt beim nächsten Seeder-Lauf (php artisan db:seed --class=RoleAndPermissionSeeder).
        // syncPermissions() hier weglassen — kollidiert mit UUID-Primärschlüsseln.
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'comparison-operator-groups.view',
            'comparison-operator-groups.create',
            'comparison-operator-groups.edit',
            'comparison-operator-groups.delete',
        ])->delete();
    }
};
