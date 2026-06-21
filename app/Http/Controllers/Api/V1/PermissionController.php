<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * Anzeige-Labels fuer Permission-Entities.
     * Single Source of Truth — Frontend liest diese statt eigener Map.
     */
    private const ENTITY_LABELS = [
        'products' => 'Produkte',
        'product-types' => 'Produkttypen',
        'attributes' => 'Attribute',
        'attribute-types' => 'Attributgruppen',
        'attribute-views' => 'Attribut-Sichten',
        'hierarchies' => 'Hierarchien',
        'hierarchy-nodes' => 'Hierarchie-Knoten',
        'unit-groups' => 'Einheitengruppen',
        'units' => 'Einheiten',
        'value-lists' => 'Wertelisten',
        'media' => 'Medien',
        'media-usage-types' => 'Bildtypen',
        'prices' => 'Preise',
        'price-types' => 'Preistypen',
        'manufacturers' => 'Hersteller',
        'relation-types' => 'Produktbeziehungen',
        'imports' => 'Import',
        'export' => 'Export',
        'publixx-mappings' => 'Publixx-Mappings',
        'users' => 'Benutzer',
        'roles' => 'Rollen',
        'access-links' => 'Zugangslinks',
        'reports' => 'Berichte',
        'pdf-templates' => 'PDF-Vorlagen',
        'calendar' => 'Planungskalender',
        'dictionary' => 'Wörterbuch',
        'translations' => 'Übersetzungen',
        'journal' => 'Journal',
        'settings' => 'Einstellungen',
        'watchlist' => 'Merkliste',
        'search' => 'Suche',
        'json-export-import' => 'JSON Export/Import',
        'bmecat' => 'BMEcat',
        'export-jobs' => 'Export-Jobs',
        'api-templates' => 'API-Templates',
        'dashboard' => 'Dashboard',
        'catalog-templates' => 'Katalog-Vorlagen',
        'portals' => 'Portale',
        'connectors' => 'Connectoren',
        'workflow' => 'Workflow',
        'workflows' => 'Workflows',
        'workflow-statuses' => 'Workflow-Status',
        'copilot' => 'KI-Assistent (Copilot)',
        'semantic-search' => 'Semantische Suche',
        'excel-templates' => 'Excel-Designer',
        'meilisearch-admin' => 'Meilisearch-Verwaltung',
        'preview' => 'Katalog-Vorschau',
        'cockpit-layouts' => 'Cockpit-Layouts',
    ];

    /**
     * Gruppierung der Entities fuer die Rollen-UI.
     */
    private const ENTITY_GROUPS = [
        [
            'label' => 'Tagesgeschäft',
            'entities' => [
                'dashboard', 'products', 'hierarchies', 'hierarchy-nodes',
                'media', 'media-usage-types', 'prices', 'price-types',
                'search', 'semantic-search', 'copilot', 'watchlist',
                'reports', 'pdf-templates', 'calendar', 'preview',
            ],
        ],
        [
            'label' => 'Konfiguration',
            'entities' => [
                'manufacturers', 'product-types', 'relation-types',
                'attribute-views', 'attribute-types', 'attributes',
                'value-lists', 'dictionary', 'units', 'unit-groups', 'translations',
            ],
        ],
        [
            'label' => 'Daten & Export',
            'entities' => [
                'imports', 'export', 'json-export-import', 'bmecat',
                'export-jobs', 'publixx-mappings', 'api-templates', 'excel-templates',
            ],
        ],
        [
            'label' => 'Enterprise',
            'entities' => [
                'catalog-templates', 'portals', 'connectors',
                'workflow', 'workflows', 'workflow-statuses',
            ],
        ],
        [
            'label' => 'Administration',
            'entities' => [
                'settings', 'users', 'roles', 'cockpit-layouts', 'access-links', 'journal', 'meilisearch-admin',
            ],
        ],
    ];

    /**
     * GET /api/v1/permissions
     *
     * List all available permissions grouped by entity,
     * including labels and category groups for the role editor UI.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Role::class);

        $permissions = Permission::where('guard_name', 'sanctum')
            ->orderBy('name')
            ->pluck('name');

        $grouped = [];
        foreach ($permissions as $permission) {
            $dotPos = strpos($permission, '.');
            if ($dotPos === false) {
                $grouped[$permission] = [];
                continue;
            }
            $entity = substr($permission, 0, $dotPos);
            $action = substr($permission, $dotPos + 1);
            $grouped[$entity][] = $action;
        }

        return response()->json([
            'data' => $grouped,
            'labels' => self::ENTITY_LABELS,
            'groups' => self::ENTITY_GROUPS,
        ]);
    }
}
