<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fügt die Berechtigungen für Referenz-Profile und Konformität additiv hinzu.
 *
 * Additiv (givePermissionTo) statt syncPermissions, um bestehende Rollen-Rechte
 * nicht zu überschreiben. KI-Erklärung (conformance.ai-explain) ist gesondert
 * wegen API-Kosten.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $new = [
            'reference-profiles.view', 'reference-profiles.create', 'reference-profiles.edit', 'reference-profiles.delete',
            'conformance.view', 'conformance.run', 'conformance.ai-explain',
        ];
        foreach ($new as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        // Vollzugriff: Sysadmin, Admin, Data Steward
        foreach (['Sysadmin', 'Admin', 'Data Steward'] as $roleName) {
            $this->grant($roleName, $new);
        }

        // Product Manager: pflegt & prüft Produkte (inkl. KI), liest Profile
        $this->grant('Product Manager', [
            'reference-profiles.view',
            'conformance.view', 'conformance.run', 'conformance.ai-explain',
        ]);

        // Viewer: nur lesen
        $this->grant('Viewer', ['reference-profiles.view', 'conformance.view']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'reference-profiles.view', 'reference-profiles.create', 'reference-profiles.edit', 'reference-profiles.delete',
            'conformance.view', 'conformance.run', 'conformance.ai-explain',
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
