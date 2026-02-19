<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Cache zurücksetzen
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Alle Permissions ────────────────────────────────────────
        $permissions = [
            // Produkte
            'products.view', 'products.create', 'products.edit', 'products.delete',
            // Attribute
            'attributes.view', 'attributes.create', 'attributes.edit', 'attributes.delete',
            // Hierarchien
            'hierarchies.view', 'hierarchies.edit',
            'hierarchy-nodes.create', 'hierarchy-nodes.move',
            // Einheitengruppen
            'unit-groups.view', 'unit-groups.create', 'unit-groups.edit', 'unit-groups.delete',
            // Wertelisten
            'value-lists.view', 'value-lists.create', 'value-lists.edit', 'value-lists.delete',
            // Attributsichten
            'attribute-views.view', 'attribute-views.create', 'attribute-views.edit', 'attribute-views.delete',
            // Medien
            'media.view', 'media.create', 'media.edit', 'media.delete',
            // Preise
            'prices.view', 'prices.create', 'prices.edit', 'prices.delete',
            // Export
            'export.view', 'export.execute', 'export.mappings.edit',
            // Publixx-Mappings
            'publixx-mappings.view', 'publixx-mappings.create', 'publixx-mappings.edit', 'publixx-mappings.delete',
            // PXF-Templates
            'pxf-templates.view', 'pxf-templates.create', 'pxf-templates.edit', 'pxf-templates.delete',
            // Benutzerverwaltung
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.edit',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'id' => Str::uuid()->toString(),
            ]);
        }

        // ─── 1. Admin (alle Permissions) ─────────────────────────────
        $admin = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['id' => Str::uuid()->toString()],
        );
        $admin->syncPermissions(Permission::all());

        // ─── 2. Data Steward (Strukturverwaltung) ────────────────────
        $dataSteward = Role::firstOrCreate(
            ['name' => 'Data Steward', 'guard_name' => 'web'],
            ['id' => Str::uuid()->toString()],
        );
        $dataSteward->syncPermissions([
            'attributes.view', 'attributes.create', 'attributes.edit', 'attributes.delete',
            'hierarchies.view', 'hierarchies.edit',
            'hierarchy-nodes.create', 'hierarchy-nodes.move',
            'unit-groups.view', 'unit-groups.create', 'unit-groups.edit', 'unit-groups.delete',
            'value-lists.view', 'value-lists.create', 'value-lists.edit', 'value-lists.delete',
            'attribute-views.view', 'attribute-views.create', 'attribute-views.edit', 'attribute-views.delete',
            'products.view',
            'prices.view',
        ]);

        // ─── 3. Product Manager (Datenpflege) ────────────────────────
        $productManager = Role::firstOrCreate(
            ['name' => 'Product Manager', 'guard_name' => 'web'],
            ['id' => Str::uuid()->toString()],
        );
        $productManager->syncPermissions([
            'products.view', 'products.create', 'products.edit',
            'media.view', 'media.create', 'media.edit', 'media.delete',
            'prices.view',
            'attributes.view',
            'hierarchies.view',
            'unit-groups.view',
            'value-lists.view',
            'attribute-views.view',
        ]);

        // ─── 4. Viewer (Nur Lesen) ──────────────────────────────────
        $viewer = Role::firstOrCreate(
            ['name' => 'Viewer', 'guard_name' => 'web'],
            ['id' => Str::uuid()->toString()],
        );
        $viewer->syncPermissions(
            Permission::where('name', 'LIKE', '%.view')->get()
        );

        // ─── 5. Export Manager (Export + Publixx) ────────────────────
        $exportManager = Role::firstOrCreate(
            ['name' => 'Export Manager', 'guard_name' => 'web'],
            ['id' => Str::uuid()->toString()],
        );
        $exportManager->syncPermissions([
            'export.view', 'export.execute', 'export.mappings.edit',
            'publixx-mappings.view', 'publixx-mappings.create', 'publixx-mappings.edit', 'publixx-mappings.delete',
            'pxf-templates.view', 'pxf-templates.create', 'pxf-templates.edit', 'pxf-templates.delete',
            'products.view',
            'attributes.view',
            'hierarchies.view',
            'prices.view',
        ]);

        $this->command->info('Rollen und Permissions erfolgreich geseeded.');
        $this->command->table(
            ['Rolle', 'Permissions'],
            [
                ['Admin', (string) $admin->permissions()->count()],
                ['Data Steward', (string) $dataSteward->permissions()->count()],
                ['Product Manager', (string) $productManager->permissions()->count()],
                ['Viewer', (string) $viewer->permissions()->count()],
                ['Export Manager', (string) $exportManager->permissions()->count()],
            ]
        );
    }
}
