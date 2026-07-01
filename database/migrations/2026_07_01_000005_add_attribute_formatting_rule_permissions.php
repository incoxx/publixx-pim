<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fügt die Berechtigungen für Attribut-Formatierungsregeln additiv hinzu.
 *
 * Additiv (givePermissionTo) statt syncPermissions, um bestehende Rollen-Rechte
 * nicht zu überschreiben.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $new = [
            'attribute-formatting-rules.view', 'attribute-formatting-rules.create',
            'attribute-formatting-rules.edit', 'attribute-formatting-rules.delete',
        ];
        foreach ($new as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        // Vollzugriff: Sysadmin, Admin, Data Steward (wie Wertelisten)
        foreach (['Sysadmin', 'Admin', 'Data Steward'] as $roleName) {
            $this->grant($roleName, $new);
        }

        // Nur lesend: Rollen, die auch value-lists.view haben
        foreach (['Product Manager', 'Export Manager', 'API Designer', 'Marketing', 'Viewer'] as $roleName) {
            $this->grant($roleName, ['attribute-formatting-rules.view']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'attribute-formatting-rules.view', 'attribute-formatting-rules.create',
            'attribute-formatting-rules.edit', 'attribute-formatting-rules.delete',
        ])->where('guard_name', 'sanctum')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param array<int, string> $permissions
     */
    private function grant(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'sanctum')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
};
