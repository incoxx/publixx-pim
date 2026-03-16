<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Add api-templates + dashboard permissions, API Designer and Project Management roles.
 * Remove obsolete pxf-templates permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── New permissions ─────────────────────────────────────────────
        $newPermissions = [
            'api-templates.view', 'api-templates.create', 'api-templates.edit', 'api-templates.delete',
            'dashboard.view',
            'price-regions.view', 'price-regions.create', 'price-regions.edit', 'price-regions.delete',
            'manufacturers.view', 'manufacturers.create', 'manufacturers.edit', 'manufacturers.delete',
            'workflow.view',
            'workflows.view', 'workflows.create', 'workflows.edit', 'workflows.delete',
            'workflow-statuses.view', 'workflow-statuses.create', 'workflow-statuses.edit', 'workflow-statuses.delete',
            'teams.view', 'teams.create', 'teams.edit', 'teams.delete',
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
        ];

        foreach ($newPermissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'sanctum',
            ]);
        }

        // ── Remove obsolete pxf-templates permissions ───────────────────
        Permission::whereIn('name', [
            'pxf-templates.view', 'pxf-templates.create', 'pxf-templates.edit', 'pxf-templates.delete',
        ])->delete();

        // ── Admin gets all permissions ──────────────────────────────────
        $admin = Role::where('name', 'Admin')->where('guard_name', 'sanctum')->first();
        if ($admin) {
            $admin->syncPermissions(Permission::all());
        }

        // ── API Designer role ───────────────────────────────────────────
        $apiDesigner = Role::firstOrCreate(
            ['name' => 'API Designer', 'guard_name' => 'sanctum'],
        );
        $apiDesigner->syncPermissions([
            'api-templates.view', 'api-templates.create', 'api-templates.edit', 'api-templates.delete',
            'products.view',
            'product-types.view',
            'attributes.view',
            'attribute-types.view',
            'hierarchies.view',
            'hierarchy-nodes.view',
            'prices.view',
            'price-types.view',
            'price-regions.view',
            'units.view',
            'unit-groups.view',
            'value-lists.view',
            'media.view',
            'search.view',
        ]);

        // ── Project Management role ─────────────────────────────────────
        $projectManagement = Role::firstOrCreate(
            ['name' => 'Project Management', 'guard_name' => 'sanctum'],
        );
        $projectManagement->syncPermissions([
            'dashboard.view',
            'workflows.view', 'workflows.create', 'workflows.edit', 'workflows.delete',
            'workflow-statuses.view', 'workflow-statuses.create', 'workflow-statuses.edit', 'workflow-statuses.delete',
            'teams.view', 'teams.create', 'teams.edit', 'teams.delete',
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
            'products.view',
            'calendar.view', 'calendar.create', 'calendar.edit', 'calendar.delete',
        ]);

        // ── Viewer gets new *.view permissions ──────────────────────────
        $viewer = Role::where('name', 'Viewer')->where('guard_name', 'sanctum')->first();
        if ($viewer) {
            $viewer->syncPermissions(
                Permission::where('name', 'LIKE', '%.view')
                    ->whereNotIn('name', ['users.view', 'roles.view', 'access-links.view', 'settings.view', 'journal.view'])
                    ->get()
            );
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally left empty — roles and permissions should not be removed
    }
};
