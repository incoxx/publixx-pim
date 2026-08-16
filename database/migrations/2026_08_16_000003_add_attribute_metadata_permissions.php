<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fügt die Berechtigungen für Attribut-Metadaten additiv hinzu.
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
            'attribute-metadata.view', 'attribute-metadata.create',
            'attribute-metadata.edit', 'attribute-metadata.delete',
        ];
        foreach ($new as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        // Vollzugriff: Sysadmin, Admin, Data Steward (wie Formatierungsregeln)
        foreach (['Sysadmin', 'Admin', 'Data Steward'] as $roleName) {
            $this->grant($roleName, $new);
        }

        // Nur lesend
        foreach (['Product Manager', 'Export Manager', 'API Designer', 'Marketing', 'Viewer'] as $roleName) {
            $this->grant($roleName, ['attribute-metadata.view']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'attribute-metadata.view', 'attribute-metadata.create',
            'attribute-metadata.edit', 'attribute-metadata.delete',
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
