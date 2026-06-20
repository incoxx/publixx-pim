<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ergänzt Berechtigungen für bislang RBAC-lose Features additiv:
 *  - KI-Assistent (Copilot)          → copilot.use / copilot.execute
 *  - Semantische Schnellsuche        → semantic-search.view
 *  - Excel-Designer (Sheet Designer) → excel-templates.{view,create,edit,delete}
 *  - Meilisearch-Verwaltung          → meilisearch-admin.{view,manage}
 *
 * Additiv (givePermissionTo) statt syncPermissions, damit bestehende
 * Rollen-Rechte nicht überschrieben werden. Meilisearch-Verwaltung bleibt
 * Sysadmin/Admin vorbehalten (Systemwartung).
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $all = [
            'copilot.use', 'copilot.execute',
            'semantic-search.view',
            'excel-templates.view', 'excel-templates.create', 'excel-templates.edit', 'excel-templates.delete',
            'meilisearch-admin.view', 'meilisearch-admin.manage',
        ];
        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        // Sysadmin + Admin: alles
        foreach (['Sysadmin', 'Admin'] as $roleName) {
            $this->grant($roleName, $all);
        }

        // Data Steward: KI, Suche, Excel-Designer voll (keine Systemwartung)
        $this->grant('Data Steward', [
            'copilot.use', 'copilot.execute',
            'semantic-search.view',
            'excel-templates.view', 'excel-templates.create', 'excel-templates.edit', 'excel-templates.delete',
        ]);

        // Product Manager: KI (inkl. Schreib-Aktionen), Suche, Excel-Designer lesen
        $this->grant('Product Manager', [
            'copilot.use', 'copilot.execute',
            'semantic-search.view',
            'excel-templates.view',
        ]);

        // Export Manager: KI-Chat, Suche, Excel-Designer voll (Export-Werkzeug)
        $this->grant('Export Manager', [
            'copilot.use',
            'semantic-search.view',
            'excel-templates.view', 'excel-templates.create', 'excel-templates.edit', 'excel-templates.delete',
        ]);

        // API Designer + Project Management: KI-Chat + Suche
        foreach (['API Designer', 'Project Management'] as $roleName) {
            $this->grant($roleName, ['copilot.use', 'semantic-search.view']);
        }

        // Viewer: nur lesen — KI-Chat + Suche + Excel-Designer ansehen
        $this->grant('Viewer', [
            'copilot.use',
            'semantic-search.view',
            'excel-templates.view',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'copilot.use', 'copilot.execute',
            'semantic-search.view',
            'excel-templates.view', 'excel-templates.create', 'excel-templates.edit', 'excel-templates.delete',
            'meilisearch-admin.view', 'meilisearch-admin.manage',
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
