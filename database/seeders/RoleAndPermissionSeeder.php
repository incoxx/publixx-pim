<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
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
            // Produkttypen
            'product-types.view', 'product-types.create', 'product-types.edit', 'product-types.delete',
            // Attribute
            'attributes.view', 'attributes.create', 'attributes.edit', 'attributes.delete',
            // Attributtypen
            'attribute-types.view', 'attribute-types.create', 'attribute-types.edit', 'attribute-types.delete',
            // Hierarchien
            'hierarchies.view', 'hierarchies.create', 'hierarchies.edit', 'hierarchies.delete',
            'hierarchy-nodes.view', 'hierarchy-nodes.create', 'hierarchy-nodes.edit', 'hierarchy-nodes.delete', 'hierarchy-nodes.move',
            // Einheitengruppen & Einheiten
            'unit-groups.view', 'unit-groups.create', 'unit-groups.edit', 'unit-groups.delete',
            'units.view', 'units.create', 'units.edit', 'units.delete',
            // Wertelisten
            'value-lists.view', 'value-lists.create', 'value-lists.edit', 'value-lists.delete',
            // Attributsichten
            'attribute-views.view', 'attribute-views.create', 'attribute-views.edit', 'attribute-views.delete',
            // Medien
            'media.view', 'media.create', 'media.edit', 'media.delete',
            // Preise & Preistypen
            'prices.view', 'prices.create', 'prices.edit', 'prices.delete',
            'price-types.view', 'price-types.create', 'price-types.edit', 'price-types.delete',
            // Relationstypen
            'relation-types.view', 'relation-types.create', 'relation-types.edit', 'relation-types.delete',
            // Import
            'imports.view', 'imports.create', 'imports.execute', 'imports.delete',
            // Export
            'export.view', 'export.execute', 'export.mappings.edit',
            // Publixx-Mappings
            'publixx-mappings.view', 'publixx-mappings.create', 'publixx-mappings.edit', 'publixx-mappings.delete',
            // PXF-Templates
            'pxf-templates.view', 'pxf-templates.create', 'pxf-templates.edit', 'pxf-templates.delete',
            // Benutzerverwaltung
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'sanctum',
            ]);
        }

        // ─── 1. Admin (alle Permissions) ─────────────────────────────
        $admin = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'sanctum'],
        );
        $admin->syncPermissions(Permission::all());

        // ─── 2. Data Steward (Strukturverwaltung) ────────────────────
        $dataSteward = Role::firstOrCreate(
            ['name' => 'Data Steward', 'guard_name' => 'sanctum'],
        );
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
            'products.view',
            'prices.view',
            'imports.view', 'imports.create', 'imports.execute', 'imports.delete',
        ]);

        // ─── 3. Product Manager (Datenpflege) ────────────────────────
        $productManager = Role::firstOrCreate(
            ['name' => 'Product Manager', 'guard_name' => 'sanctum'],
        );
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
            'imports.view', 'imports.create', 'imports.execute',
        ]);

        // ─── 4. Viewer (Nur Lesen) ──────────────────────────────────
        $viewer = Role::firstOrCreate(
            ['name' => 'Viewer', 'guard_name' => 'sanctum'],
        );
        $viewer->syncPermissions(
            Permission::where('name', 'LIKE', '%.view')->get()
        );

        // ─── 5. Export Manager (Export + Publixx) ────────────────────
        $exportManager = Role::firstOrCreate(
            ['name' => 'Export Manager', 'guard_name' => 'sanctum'],
        );
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
