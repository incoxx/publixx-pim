<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fügt die Berechtigungen für Tags additiv hinzu.
 *
 * Additiv (givePermissionTo) statt syncPermissions, um bestehende Rollen-Rechte
 * nicht zu überschreiben — gleiches Vorgehen wie bei den Attribut-Metadaten
 * (2026_08_16_000003).
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $new = ['tags.view', 'tags.create', 'tags.edit', 'tags.delete'];
        foreach ($new as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        // Vollzugriff: Stammdatenpflege
        foreach (['Sysadmin', 'Admin', 'Data Steward'] as $roleName) {
            $this->grant($roleName, $new);
        }

        // Product Manager und Marketing vergeben Tags an Produkten/Medien und
        // dürfen dafür auch neue anlegen, aber keine löschen.
        foreach (['Product Manager', 'Marketing'] as $roleName) {
            $this->grant($roleName, ['tags.view', 'tags.create', 'tags.edit']);
        }

        // Nur lesend
        foreach (['Export Manager', 'API Designer', 'Viewer'] as $roleName) {
            $this->grant($roleName, ['tags.view']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', ['tags.view', 'tags.create', 'tags.edit', 'tags.delete'])
            ->where('guard_name', 'sanctum')
            ->delete();

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
