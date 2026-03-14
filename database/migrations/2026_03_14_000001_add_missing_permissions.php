<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Add permissions for menu items that were previously missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = [
            'access-links.view', 'access-links.create', 'access-links.delete',
            'reports.view', 'reports.create', 'reports.edit', 'reports.delete',
            'pdf-templates.view', 'pdf-templates.create', 'pdf-templates.edit', 'pdf-templates.delete',
            'calendar.view', 'calendar.create', 'calendar.edit', 'calendar.delete',
            'dictionary.view', 'dictionary.create', 'dictionary.edit', 'dictionary.delete',
            'translations.view', 'translations.edit',
            'journal.view',
            'settings.view', 'settings.edit',
            'watchlist.view', 'watchlist.edit',
            'search.view',
            'json-export-import.view', 'json-export-import.execute',
            'bmecat.view', 'bmecat.execute',
            'export-jobs.view', 'export-jobs.create', 'export-jobs.delete',
        ];

        foreach ($newPermissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'sanctum',
            ]);
        }

        // Admin gets all permissions
        $admin = Role::where('name', 'Admin')->where('guard_name', 'sanctum')->first();
        if ($admin) {
            $admin->syncPermissions(Permission::all());
        }

        // Viewer gets all new *.view permissions (except users/roles)
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
        // Intentionally left empty — permissions should not be removed
    }
};
