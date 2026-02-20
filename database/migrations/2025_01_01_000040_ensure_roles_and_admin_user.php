<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Ensure roles, permissions, and admin user assignment exist.
     *
     * This migration is idempotent and handles all possible states:
     * - No roles/permissions exist (seeder never ran)
     * - Roles exist with wrong guard_name ('web' instead of 'sanctum')
     * - Roles exist correctly but user has no role assigned
     */
    public function up(): void
    {
        // Clear permission cache first
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Step 1: Fix guard_name on any existing records
        DB::table('roles')
            ->where('guard_name', 'web')
            ->update(['guard_name' => 'sanctum']);

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->update(['guard_name' => 'sanctum']);

        // Step 2: Create all permissions if they don't exist
        $permissions = [
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'attributes.view', 'attributes.create', 'attributes.edit', 'attributes.delete',
            'hierarchies.view', 'hierarchies.edit',
            'hierarchy-nodes.create', 'hierarchy-nodes.move',
            'unit-groups.view', 'unit-groups.create', 'unit-groups.edit', 'unit-groups.delete',
            'value-lists.view', 'value-lists.create', 'value-lists.edit', 'value-lists.delete',
            'attribute-views.view', 'attribute-views.create', 'attribute-views.edit', 'attribute-views.delete',
            'media.view', 'media.create', 'media.edit', 'media.delete',
            'prices.view', 'prices.create', 'prices.edit', 'prices.delete',
            'export.view', 'export.execute', 'export.mappings.edit',
            'publixx-mappings.view', 'publixx-mappings.create', 'publixx-mappings.edit', 'publixx-mappings.delete',
            'pxf-templates.view', 'pxf-templates.create', 'pxf-templates.edit', 'pxf-templates.delete',
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.edit',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'sanctum',
            ]);
        }

        // Step 3: Create Admin role if it doesn't exist, and sync all permissions
        $admin = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'sanctum'],
        );
        $admin->syncPermissions(Permission::all());

        // Step 4: Create other roles
        $roles = [
            'Data Steward' => [
                'attributes.view', 'attributes.create', 'attributes.edit', 'attributes.delete',
                'hierarchies.view', 'hierarchies.edit',
                'hierarchy-nodes.create', 'hierarchy-nodes.move',
                'unit-groups.view', 'unit-groups.create', 'unit-groups.edit', 'unit-groups.delete',
                'value-lists.view', 'value-lists.create', 'value-lists.edit', 'value-lists.delete',
                'attribute-views.view', 'attribute-views.create', 'attribute-views.edit', 'attribute-views.delete',
                'products.view', 'prices.view',
            ],
            'Product Manager' => [
                'products.view', 'products.create', 'products.edit',
                'media.view', 'media.create', 'media.edit', 'media.delete',
                'prices.view', 'attributes.view', 'hierarchies.view',
                'unit-groups.view', 'value-lists.view', 'attribute-views.view',
            ],
            'Viewer' => $permissions, // will be filtered to .view only below
            'Export Manager' => [
                'export.view', 'export.execute', 'export.mappings.edit',
                'publixx-mappings.view', 'publixx-mappings.create', 'publixx-mappings.edit', 'publixx-mappings.delete',
                'pxf-templates.view', 'pxf-templates.create', 'pxf-templates.edit', 'pxf-templates.delete',
                'products.view', 'attributes.view', 'hierarchies.view', 'prices.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'sanctum'],
            );

            if ($roleName === 'Viewer') {
                $role->syncPermissions(
                    Permission::where('name', 'LIKE', '%.view')->get()
                );
            } else {
                $role->syncPermissions($rolePermissions);
            }
        }

        // Step 5: Assign Admin role to admin users
        $adminEmails = ['admin@publixx.com', 'admin@example.com'];

        foreach ($adminEmails as $email) {
            $user = User::where('email', $email)->first();
            if ($user && !$user->hasRole('Admin')) {
                $user->assignRole('Admin');
            }
        }

        // Clear permission cache again after all changes
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally left empty
    }
};
