<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'sanctum';

        // ----- Permissions -----
        $permissions = [
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'attributes.view',
            'attributes.create',
            'attributes.edit',
            'attributes.delete',
            'hierarchies.view',
            'hierarchies.edit',
            'hierarchy-nodes.create',
            'hierarchy-nodes.move',
            'unit-groups.view',
            'unit-groups.create',
            'unit-groups.edit',
            'unit-groups.delete',
            'value-lists.view',
            'value-lists.create',
            'value-lists.edit',
            'value-lists.delete',
            'media.view',
            'media.create',
            'media.edit',
            'media.delete',
            'prices.view',
            'prices.edit',
            'export.view',
            'export.execute',
            'export.mappings.edit',
            'pxf-templates.view',
            'pxf-templates.edit',
            'import.view',
            'import.execute',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'roles.edit',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, $guard);
        }

        // ----- Roles -----
        $allPermissions = Permission::where('guard_name', $guard)->get();

        // 1. Admin – full access
        $admin = Role::findOrCreate('Admin', $guard);
        $admin->syncPermissions($allPermissions);

        // 2. Data Steward – structure management
        $dataSteward = Role::findOrCreate('Data Steward', $guard);
        $dataSteward->syncPermissions([
            'products.view',
            'attributes.view', 'attributes.create', 'attributes.edit', 'attributes.delete',
            'hierarchies.view', 'hierarchies.edit',
            'hierarchy-nodes.create', 'hierarchy-nodes.move',
            'unit-groups.view', 'unit-groups.create', 'unit-groups.edit', 'unit-groups.delete',
            'value-lists.view', 'value-lists.create', 'value-lists.edit', 'value-lists.delete',
            'media.view',
            'prices.view',
        ]);

        // 3. Product Manager – data maintenance
        $productManager = Role::findOrCreate('Product Manager', $guard);
        $productManager->syncPermissions([
            'products.view', 'products.create', 'products.edit',
            'attributes.view',
            'hierarchies.view',
            'unit-groups.view',
            'value-lists.view',
            'media.view', 'media.create', 'media.edit', 'media.delete',
            'prices.view',
            'import.view', 'import.execute',
        ]);

        // 4. Viewer – read-only
        $viewer = Role::findOrCreate('Viewer', $guard);
        $viewer->syncPermissions(
            $allPermissions->filter(fn (Permission $p) => str_ends_with($p->name, '.view'))->pluck('name')->toArray()
        );

        // 5. Export Manager – export + Publixx
        $exportManager = Role::findOrCreate('Export Manager', $guard);
        $exportManager->syncPermissions([
            'products.view',
            'attributes.view',
            'hierarchies.view',
            'media.view',
            'prices.view',
            'export.view', 'export.execute', 'export.mappings.edit',
            'pxf-templates.view', 'pxf-templates.edit',
        ]);
    }
}
