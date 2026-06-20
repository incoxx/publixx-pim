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
            // Preisregionen
            'price-regions.view', 'price-regions.create', 'price-regions.edit', 'price-regions.delete',
            // Medien-Bildtypen
            'media-usage-types.view', 'media-usage-types.create', 'media-usage-types.edit', 'media-usage-types.delete',
            // Hersteller
            'manufacturers.view', 'manufacturers.create', 'manufacturers.edit', 'manufacturers.delete',
            // Relationstypen
            'relation-types.view', 'relation-types.create', 'relation-types.edit', 'relation-types.delete',
            // Import
            'imports.view', 'imports.create', 'imports.execute', 'imports.delete',
            // Export
            'export.view', 'export.execute', 'export.mappings.edit',
            // Publixx-Mappings
            'publixx-mappings.view', 'publixx-mappings.create', 'publixx-mappings.edit', 'publixx-mappings.delete',
            // Attribut-Mappings (Klassifikations-Zuordnung)
            'attribute-mappings.view', 'attribute-mappings.create', 'attribute-mappings.edit', 'attribute-mappings.delete',
            // Benutzerverwaltung
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            // Zugangslinks
            'access-links.view', 'access-links.create', 'access-links.delete',
            // Berichte
            'reports.view', 'reports.create', 'reports.edit', 'reports.delete',
            // PDF-Vorlagen
            'pdf-templates.view', 'pdf-templates.create', 'pdf-templates.edit', 'pdf-templates.delete',
            // Planungskalender
            'calendar.view', 'calendar.create', 'calendar.edit', 'calendar.delete',
            // Wörterbuch
            'dictionary.view', 'dictionary.create', 'dictionary.edit', 'dictionary.delete',
            // Übersetzungen
            'translations.view', 'translations.edit',
            // Journal
            'journal.view',
            // Workflow
            'workflow.view',
            // Workflow-Status
            'workflow-statuses.view', 'workflow-statuses.create', 'workflow-statuses.edit', 'workflow-statuses.delete',
            // Workflows
            'workflows.view', 'workflows.create', 'workflows.edit', 'workflows.delete',
            // Teams
            'teams.view', 'teams.create', 'teams.edit', 'teams.delete',
            // Projekte
            'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
            // Einstellungen
            'settings.view', 'settings.edit',
            // Merkliste
            'watchlist.view', 'watchlist.edit',
            // Suche
            'search.view',
            // JSON Export/Import
            'json-export-import.view', 'json-export-import.execute',
            // BMEcat Import/Export
            'bmecat.view', 'bmecat.execute',
            // Export-Jobs
            'export-jobs.view', 'export-jobs.create', 'export-jobs.delete',
            // API-Designer / API-Templates
            'api-templates.view', 'api-templates.create', 'api-templates.edit', 'api-templates.delete',
            // Catalog Templates
            'catalog-templates.view', 'catalog-templates.create', 'catalog-templates.edit', 'catalog-templates.delete',
            // Portale
            'portals.view', 'portals.create', 'portals.edit', 'portals.delete',
            // Connectoren
            'connectors.view', 'connectors.manage',
            // Dashboard
            'dashboard.view',
            // Fehlerklassifikation (Sysadmin)
            'error-classifications.view', 'error-classifications.triage', 'error-classifications.delete',
            // Vergleichsoperator-Gruppen
            'comparison-operator-groups.view', 'comparison-operator-groups.create',
            'comparison-operator-groups.edit', 'comparison-operator-groups.delete',
            // Referenz-Profile (virtuelle Referenzprodukte)
            'reference-profiles.view', 'reference-profiles.create', 'reference-profiles.edit', 'reference-profiles.delete',
            // Konformität (Prüfung + KI-Erklärung; ai-explain gesondert wegen Kosten)
            'conformance.view', 'conformance.run', 'conformance.ai-explain',
            // KI-Assistent (Copilot) — Chat lesend, execute = bestätigte Schreib-Aktion
            'copilot.use', 'copilot.execute',
            // Semantische Schnellsuche (Meilisearch Hybrid Search)
            'semantic-search.view',
            // Excel-Designer (Sheet Designer für .xlsx-Produktlisten)
            'excel-templates.view', 'excel-templates.create', 'excel-templates.edit', 'excel-templates.delete',
            // Meilisearch-Verwaltung (Diagnose + Wartungsaktionen, Administration)
            'meilisearch-admin.view', 'meilisearch-admin.manage',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'sanctum',
            ]);
        }

        // ─── 0. Sysadmin (System-Superuser, unveränderlich) ──────────
        $sysadmin = Role::firstOrCreate(
            ['name' => 'Sysadmin', 'guard_name' => 'sanctum'],
        );
        $sysadmin->syncPermissions(Permission::all());

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
            'manufacturers.view', 'manufacturers.create', 'manufacturers.edit', 'manufacturers.delete',
            'price-types.view', 'price-types.create', 'price-types.edit', 'price-types.delete',
            'price-regions.view', 'price-regions.create', 'price-regions.edit', 'price-regions.delete',
            'media-usage-types.view', 'media-usage-types.create', 'media-usage-types.edit', 'media-usage-types.delete',
            'products.view',
            'prices.view',
            'imports.view', 'imports.create', 'imports.execute', 'imports.delete',
            'workflow.view',
            'workflow-statuses.view', 'workflows.view', 'teams.view', 'projects.view',
            // Referenz-Profile + Konformität (Strukturverwaltung)
            'reference-profiles.view', 'reference-profiles.create', 'reference-profiles.edit', 'reference-profiles.delete',
            'conformance.view', 'conformance.run', 'conformance.ai-explain',
            // KI-Assistent, semantische Suche, Excel-Designer (voll)
            'copilot.use', 'copilot.execute',
            'semantic-search.view',
            'excel-templates.view', 'excel-templates.create', 'excel-templates.edit', 'excel-templates.delete',
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
            'price-regions.view',
            'attributes.view',
            'attribute-types.view',
            'hierarchies.view',
            'hierarchy-nodes.view',
            'unit-groups.view',
            'units.view',
            'value-lists.view',
            'attribute-views.view',
            'relation-types.view',
            'manufacturers.view',
            'media-usage-types.view',
            'imports.view', 'imports.create', 'imports.execute',
            'workflow.view',
            'workflows.view', 'teams.view', 'projects.view', 'projects.edit',
            // Konformität: Produkte pflegen & prüfen (inkl. KI), Profile nur lesen
            'reference-profiles.view',
            'conformance.view', 'conformance.run', 'conformance.ai-explain',
            // KI-Assistent (inkl. bestätigte Schreib-Aktionen), semantische Suche, Excel-Designer (lesen)
            'copilot.use', 'copilot.execute',
            'semantic-search.view',
            'excel-templates.view',
        ]);

        // ─── 4. Viewer (Nur Lesen, ohne Benutzerverwaltung) ─────────
        $viewer = Role::firstOrCreate(
            ['name' => 'Viewer', 'guard_name' => 'sanctum'],
        );
        $viewer->syncPermissions(
            Permission::where('name', 'LIKE', '%.view')
                // Benutzer-/Rollen-Verwaltung und Meilisearch-Wartung sind nicht "nur lesen"
                ->whereNotIn('name', ['users.view', 'roles.view', 'meilisearch-admin.view'])
                ->get()
        );
        // KI-Assistent (Chat) zusätzlich erlauben — copilot.use ist keine .view-Permission
        $viewer->givePermissionTo('copilot.use');

        // ─── 5. Export Manager (Export + Publixx) ────────────────────
        $exportManager = Role::firstOrCreate(
            ['name' => 'Export Manager', 'guard_name' => 'sanctum'],
        );
        $exportManager->syncPermissions([
            'export.view', 'export.execute', 'export.mappings.edit',
            'publixx-mappings.view', 'publixx-mappings.create', 'publixx-mappings.edit', 'publixx-mappings.delete',
            'attribute-mappings.view', 'attribute-mappings.create', 'attribute-mappings.edit', 'attribute-mappings.delete',
            'products.view',
            'product-types.view',
            'attributes.view',
            'attribute-types.view',
            'hierarchies.view',
            'hierarchy-nodes.view',
            'prices.view',
            'price-types.view',
            'price-regions.view',
            'relation-types.view',
            'units.view',
            'unit-groups.view',
            'value-lists.view',
            // KI-Assistent, semantische Suche, Excel-Designer (voll — Export-Werkzeug)
            'copilot.use',
            'semantic-search.view',
            'excel-templates.view', 'excel-templates.create', 'excel-templates.edit', 'excel-templates.delete',
        ]);

        // ─── 6. API Designer (API-Templates) ──────────────────────────
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
            // KI-Assistent + semantische Suche
            'copilot.use',
            'semantic-search.view',
        ]);

        // ─── 7. Project Management (Dashboard, Workflows, Teams, Projects) ─
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
            // KI-Assistent + semantische Suche
            'copilot.use',
            'semantic-search.view',
        ]);

        // ─── 8. Marketing (Content, Medien, Übersetzungen, Ausspielung) ─
        $marketing = Role::firstOrCreate(
            ['name' => 'Marketing', 'guard_name' => 'sanctum'],
        );
        $marketing->syncPermissions([
            // Medien/Content: Vollzugriff
            'media.view', 'media.create', 'media.edit', 'media.delete',
            'media-usage-types.view',
            // Produkte & Struktur: nur lesend (Kontext)
            'products.view',
            'product-types.view',
            'attributes.view',
            'attribute-types.view',
            'hierarchies.view',
            'hierarchy-nodes.view',
            'relation-types.view',
            'value-lists.view',
            // Übersetzungen
            'translations.view', 'translations.edit',
            // Ausspielung
            'portals.view',
            'catalog-templates.view',
            'reports.view',
            // Arbeitsauswahl / Suche / Export sichten
            'watchlist.view', 'watchlist.edit',
            'search.view',
            'export.view',
            // Basis
            'dashboard.view',
            'copilot.use',
            'semantic-search.view',
        ]);
        // Standard-Ansicht: Cockpit (Fokus-Modus)
        $marketing->default_view_mode = 'cockpit';
        $marketing->save();
        // Tab-Rechte: Medien voll, Rest reduziert
        $marketingTabs = [
            'base-data' => 'read',
            'attributes' => 'read',
            'variant-attributes' => 'read',
            'variants' => 'read',
            'prices' => 'hidden',
            'relations' => 'read',
            'output-hierarchies' => 'read',
        ];
        foreach ($marketingTabs as $tabKey => $level) {
            \App\Models\RoleTabPermission::updateOrCreate(
                ['role_id' => $marketing->id, 'tab_key' => $tabKey],
                ['access_level' => $level],
            );
        }

        $this->command->info('Rollen und Permissions erfolgreich geseeded.');
        $this->command->table(
            ['Rolle', 'Permissions'],
            [
                ['Sysadmin', (string) $sysadmin->permissions()->count()],
                ['Admin', (string) $admin->permissions()->count()],
                ['Data Steward', (string) $dataSteward->permissions()->count()],
                ['Product Manager', (string) $productManager->permissions()->count()],
                ['Viewer', (string) $viewer->permissions()->count()],
                ['Export Manager', (string) $exportManager->permissions()->count()],
                ['API Designer', (string) $apiDesigner->permissions()->count()],
                ['Project Management', (string) $projectManagement->permissions()->count()],
                ['Marketing', (string) $marketing->permissions()->count()],
            ]
        );
    }
}
