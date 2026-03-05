<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sync permissions and role assignments to match the current RoleAndPermissionSeeder.
 *
 * The initial migration (000040) had an incomplete permission list.
 * This migration adds the missing permissions and updates role assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // All permissions that should exist (from RoleAndPermissionSeeder)
        $allPermissions = [
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'product-types.view', 'product-types.create', 'product-types.edit', 'product-types.delete',
            'attributes.view', 'attributes.create', 'attributes.edit', 'attributes.delete',
            'attribute-types.view', 'attribute-types.create', 'attribute-types.edit', 'attribute-types.delete',
            'hierarchies.view', 'hierarchies.create', 'hierarchies.edit', 'hierarchies.delete',
            'hierarchy-nodes.view', 'hierarchy-nodes.create', 'hierarchy-nodes.edit', 'hierarchy-nodes.delete', 'hierarchy-nodes.move',
            'unit-groups.view', 'unit-groups.create', 'unit-groups.edit', 'unit-groups.delete',
            'units.view', 'units.create', 'units.edit', 'units.delete',
            'value-lists.view', 'value-lists.create', 'value-lists.edit', 'value-lists.delete',
            'attribute-views.view', 'attribute-views.create', 'attribute-views.edit', 'attribute-views.delete',
            'media.view', 'media.create', 'media.edit', 'media.delete',
            'prices.view', 'prices.create', 'prices.edit', 'prices.delete',
            'price-types.view', 'price-types.create', 'price-types.edit', 'price-types.delete',
            'media-usage-types.view', 'media-usage-types.create', 'media-usage-types.edit', 'media-usage-types.delete',
            'relation-types.view', 'relation-types.create', 'relation-types.edit', 'relation-types.delete',
            'imports.view', 'imports.create', 'imports.execute', 'imports.delete',
            'export.view', 'export.execute', 'export.mappings.edit',
            'publixx-mappings.view', 'publixx-mappings.create', 'publixx-mappings.edit', 'publixx-mappings.delete',
            'pxf-templates.view', 'pxf-templates.create', 'pxf-templates.edit', 'pxf-templates.delete',
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
        ];

        // Create any missing permissions
        foreach ($allPermissions as $name) {
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

        // Data Steward
        $dataSteward = Role::where('name', 'Data Steward')->where('guard_name', 'sanctum')->first();
        if ($dataSteward) {
            $dataSteward->syncPermissions([
                'attributes.view', 'attributes.create', 'attributes.edit', 'attributes.delete',
                'attribute-types.view', 'attribute-types.create', 'attribute-types.edit', 'attribute-types.delete',
                'product-types.view', 'product-types.create', 'product-types.edit', 'product-types.delete',
                'hierarchies.view', 'hierarchies.create', 'hierarchies.edit', 'hierarchies.delete',
                'hierarchy-nodes.view', 'hierarchy-nodes.create', 'hierarchy-nodes.edit', 'hierarchy-nodes.delete', 'hierarchy-nodes.move',
                'unit-groups.view', 'unit-groups.create', 'unit-groups.edit', 'unit-groups.delete',
                'units.view', 'units.create', 'units.edit', 'units.delete',
                'value-lists.view', 'value-lists.create', 'value-lists.edit', 'value-lists.delete',
                'attribute-views.view', 'attribute-views.create', 'attribute-views.edit', 'attribute-views.delete',
                'relation-types.view', 'relation-types.create', 'relation-types.edit', 'relation-types.delete',
                'price-types.view', 'price-types.create', 'price-types.edit', 'price-types.delete',
                'media-usage-types.view', 'media-usage-types.create', 'media-usage-types.edit', 'media-usage-types.delete',
                'products.view',
                'prices.view',
                'imports.view', 'imports.create', 'imports.execute', 'imports.delete',
            ]);
        }

        // Product Manager
        $productManager = Role::where('name', 'Product Manager')->where('guard_name', 'sanctum')->first();
        if ($productManager) {
            $productManager->syncPermissions([
                'products.view', 'products.create', 'products.edit',
                'product-types.view',
                'media.view', 'media.create', 'media.edit', 'media.delete',
                'prices.view', 'prices.create', 'prices.edit',
                'price-types.view',
                'attributes.view',
                'attribute-types.view',
                'hierarchies.view',
                'hierarchy-nodes.view',
                'unit-groups.view',
                'units.view',
                'value-lists.view',
                'attribute-views.view',
                'relation-types.view',
                'media-usage-types.view',
                'imports.view', 'imports.create', 'imports.execute',
            ]);
        }

        // Viewer — all *.view except users/roles
        $viewer = Role::where('name', 'Viewer')->where('guard_name', 'sanctum')->first();
        if ($viewer) {
            $viewer->syncPermissions(
                Permission::where('name', 'LIKE', '%.view')
                    ->whereNotIn('name', ['users.view', 'roles.view'])
                    ->get()
            );
        }

        // Export Manager
        $exportManager = Role::where('name', 'Export Manager')->where('guard_name', 'sanctum')->first();
        if ($exportManager) {
            $exportManager->syncPermissions([
                'export.view', 'export.execute', 'export.mappings.edit',
                'publixx-mappings.view', 'publixx-mappings.create', 'publixx-mappings.edit', 'publixx-mappings.delete',
                'pxf-templates.view', 'pxf-templates.create', 'pxf-templates.edit', 'pxf-templates.delete',
                'products.view',
                'product-types.view',
                'attributes.view',
                'attribute-types.view',
                'hierarchies.view',
                'hierarchy-nodes.view',
                'prices.view',
                'price-types.view',
                'relation-types.view',
                'units.view',
                'unit-groups.view',
                'value-lists.view',
            ]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally left empty — permissions should not be removed
    }
};
