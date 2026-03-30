<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

class ApiTesterController extends Controller
{
    /**
     * Gruppen-Zuordnung: URI-Segment → Gruppe + Kategorie (read/write/delete/stream).
     */
    private const ROUTE_GROUPS = [
        // Produkte & Katalog
        'products' => 'Produkte',
        'product-types' => 'Produkte',
        'product-versions' => 'Produkte',
        'product-relations' => 'Produkte',

        // Attribute & Werte
        'attributes' => 'Attribute',
        'attribute-types' => 'Attribute',
        'attribute-groups' => 'Attribute',

        // Hierarchien & Klassifikation
        'hierarchies' => 'Hierarchien',
        'hierarchy-nodes' => 'Hierarchien',

        // Medien
        'media' => 'Medien',
        'media-usages' => 'Medien',

        // Preise
        'prices' => 'Preise',
        'price-types' => 'Preise',

        // Export & Import
        'export' => 'Export / Import',
        'bmecat-export' => 'Export / Import',
        'gaeb-export' => 'Export / Import',
        'dataset-export' => 'Export / Import',
        'publixx-datasets' => 'Export / Import',
        'import' => 'Export / Import',
        'excel-import' => 'Export / Import',

        // API Designer
        'api-templates' => 'API Designer',
        'api-streams' => 'API Designer',

        // Konnektoren
        'connectors' => 'Konnektoren',
        'shopify' => 'Konnektoren',
        'shopware' => 'Konnektoren',
        'pim-sync' => 'PimSync',

        // Verwaltung
        'users' => 'Verwaltung',
        'roles' => 'Verwaltung',
        'permissions' => 'Verwaltung',
        'admin' => 'Verwaltung',
        'settings' => 'Verwaltung',
        'modules' => 'Verwaltung',

        // Suche & Profile
        'search' => 'Suche',
        'search-profiles' => 'Suche',
        'pql' => 'Suche',

        // Sonstiges
        'units' => 'Stammdaten',
        'selection-lists' => 'Stammdaten',
        'relation-types' => 'Stammdaten',
        'collections' => 'Stammdaten',
        'tags' => 'Stammdaten',
        'value-lists' => 'Stammdaten',

        // Reports & Templates
        'reports' => 'Reports',
        'report-templates' => 'Reports',
        'pdf-templates' => 'Reports',
        'excel-templates' => 'Reports',
        'catalog-templates' => 'Reports',

        // Health & System
        'health' => 'System',
        'dashboard' => 'System',
    ];

    public function routes(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
            ->map(function ($route) {
                $methods = array_values(array_diff($route->methods(), ['HEAD']));
                $uri = '/' . $route->uri();

                return [
                    'methods' => $methods,
                    'uri' => $uri,
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                    'group' => $this->resolveGroup($uri),
                    'category' => $this->resolveCategory($methods),
                ];
            })
            ->sortBy('uri')
            ->values();

        return response()->json(['data' => $routes]);
    }

    /**
     * Bestimmt die Gruppe anhand des ersten URI-Segments nach /api/v1/.
     */
    private function resolveGroup(string $uri): string
    {
        // /api/v1/products/{id}/relations → "products"
        $segment = explode('/', trim($uri, '/'))[2] ?? '';

        return self::ROUTE_GROUPS[$segment] ?? 'Sonstiges';
    }

    /**
     * Bestimmt die Kategorie anhand der HTTP-Methoden.
     */
    private function resolveCategory(array $methods): string
    {
        if (in_array('DELETE', $methods)) {
            return 'delete';
        }
        if (array_intersect(['POST', 'PUT', 'PATCH'], $methods)) {
            return 'write';
        }

        return 'read';
    }
}
