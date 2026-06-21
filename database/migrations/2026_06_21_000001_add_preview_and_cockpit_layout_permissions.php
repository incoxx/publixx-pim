<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ergänzt Berechtigungen additiv:
 *  - Katalog-Vorschau (Preview-Einstieg im Header) → preview.view
 *  - Cockpit-Layouts (rollenbasierter Editor)      → cockpit-layouts.{view,edit}
 *
 * Additiv (givePermissionTo) statt syncPermissions, damit bestehende
 * Rollen-Rechte nicht überschrieben werden. Cockpit-Layouts ist eine
 * Administrationsfunktion und bleibt Sysadmin/Admin vorbehalten; preview.view
 * erhalten zusätzlich lesende/pflegende Rollen.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $all = [
            'preview.view',
            'cockpit-layouts.view', 'cockpit-layouts.edit',
        ];
        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        // Sysadmin + Admin: alles (inkl. Cockpit-Layouts verwalten)
        foreach (['Sysadmin', 'Admin'] as $roleName) {
            $this->grant($roleName, $all);
        }

        // Katalog-Vorschau für pflegende/lesende Rollen
        foreach (['Data Steward', 'Product Manager', 'Viewer', 'Marketing'] as $roleName) {
            $this->grant($roleName, ['preview.view']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'preview.view',
            'cockpit-layouts.view', 'cockpit-layouts.edit',
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
