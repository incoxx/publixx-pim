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

        // Sysadmin und Admin erhalten alle neuen Permissions automatisch
        $allPermissions = Permission::all();

        foreach (['Sysadmin', 'Admin'] as $roleName) {
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)
                ->where('guard_name', 'sanctum')
                ->first();

            if ($role) {
                $role->syncPermissions($allPermissions);
            }
        }
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
