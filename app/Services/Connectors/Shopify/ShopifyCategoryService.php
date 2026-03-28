<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopify;

use App\Models\ConnectorSyncLog;
use App\Models\Hierarchy;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;

class ShopifyCategoryService
{
    private const API_VERSION = '2025-10';

    /**
     * Synchronisiert eine PIM-Hierarchie als Shopify Custom Collections.
     *
     * Shopify hat keine hierarchischen Kategorien — jeder Knoten wird als
     * flache Custom Collection angelegt. Die Sortierung basiert auf depth + sort_order.
     *
     * @return array{synced: int, errors: int, error_details: array, category_map: array<string, string>}
     */
    public function syncHierarchy(
        PendingRequest $http,
        string $shopUrl,
        string $connectionId,
        string $hierarchyId,
        string $language = 'de',
        array $excludedNodeIds = [],
    ): array {
        $shopUrl = rtrim($shopUrl, '/');

        $hierarchy = Hierarchy::find($hierarchyId);
        if (!$hierarchy) {
            return ['synced' => 0, 'errors' => 0, 'error_details' => ['Hierarchie nicht gefunden'], 'category_map' => [], 'hierarchy_name' => '', 'total_nodes' => 0];
        }

        // Alle aktiven Knoten laden
        $nodesQuery = $hierarchy->nodes()
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('sort_order');

        if (!empty($excludedNodeIds)) {
            $nodesQuery->whereNotIn('id', $excludedNodeIds);
        }

        $allNodes = $nodesQuery->get();

        if ($allNodes->isEmpty()) {
            return [
                'synced'         => 0,
                'errors'         => 0,
                'error_details'  => [],
                'category_map'   => [],
                'hierarchy_name' => $language === 'en' && $hierarchy->name_en ? $hierarchy->name_en : $hierarchy->name_de,
                'total_nodes'    => 0,
            ];
        }

        // Bestehende Shopify-Collection-IDs aus Sync-Logs laden
        $existingMap = ConnectorSyncLog::where('connector_connection_id', $connectionId)
            ->where('action', 'category_sync')
            ->where('entity_type', 'hierarchy_node')
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->whereIn('entity_id', $allNodes->pluck('id'))
            ->get()
            ->mapWithKeys(fn ($log) => [$log->entity_id => $log->external_id])
            ->toArray();

        $categoryMap = $existingMap;
        $synced = 0;
        $errors = 0;
        $errorDetails = [];

        foreach ($allNodes as $node) {
            $name = $language === 'en' && $node->name_en ? $node->name_en : $node->name_de;
            $name = $name ?: 'Kategorie';

            $existingCollectionId = $existingMap[$node->id] ?? null;

            try {
                if ($existingCollectionId) {
                    // Update bestehende Collection
                    $response = $http->put(
                        "{$shopUrl}/admin/api/" . self::API_VERSION . "/custom_collections/{$existingCollectionId}.json",
                        [
                            'custom_collection' => [
                                'id'        => (int) $existingCollectionId,
                                'title'     => $name,
                                'published' => true,
                            ],
                        ],
                    );
                    $response->throw();
                    $shopifyCollectionId = $existingCollectionId;
                } else {
                    // Neue Collection erstellen
                    $response = $http->post(
                        "{$shopUrl}/admin/api/" . self::API_VERSION . '/custom_collections.json',
                        [
                            'custom_collection' => [
                                'title'     => $name,
                                'published' => true,
                            ],
                        ],
                    );
                    $response->throw();
                    $data = $response->json();
                    $shopifyCollectionId = (string) ($data['custom_collection']['id'] ?? '');
                }

                $categoryMap[$node->id] = $shopifyCollectionId;
                $synced++;
            } catch (\Throwable $e) {
                $errors++;
                $errorMessage = $e instanceof \Illuminate\Http\Client\RequestException
                    ? $this->parseShopifyError($e)
                    : $e->getMessage();
                $errorDetails[] = $errorMessage;

                Log::channel('connectors')->error('Collection-Sync fehlgeschlagen', [
                    'node_id' => $node->id,
                    'error'   => $errorMessage,
                ]);
            }
        }

        return [
            'synced'         => $synced,
            'errors'         => $errors,
            'error_details'  => $errorDetails,
            'category_map'   => $categoryMap,
            'hierarchy_name' => $language === 'en' && $hierarchy->name_en ? $hierarchy->name_en : $hierarchy->name_de,
            'total_nodes'    => $allNodes->count(),
        ];
    }

    /**
     * Weist ein Produkt einer Shopify Custom Collection zu via Collect.
     */
    public function assignProductToCategory(
        PendingRequest $http,
        string $shopUrl,
        string $productId,
        string $collectionId,
    ): void {
        $shopUrl = rtrim($shopUrl, '/');

        try {
            $http->post(
                "{$shopUrl}/admin/api/" . self::API_VERSION . '/collects.json',
                [
                    'collect' => [
                        'product_id'    => (int) $productId,
                        'collection_id' => (int) $collectionId,
                    ],
                ],
            )->throw();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // 422 = bereits zugeordnet — ignorieren
            if ($e->response?->status() !== 422) {
                throw $e;
            }
        }
    }

    /**
     * Erstellt ein hierarchisches Navigation Menu in Shopify via GraphQL.
     *
     * Bildet die PIM-Hierarchie als verschachteltes Shopify-Menu ab (max 3 Ebenen).
     * Jedes MenuItem verlinkt auf die entsprechende Custom Collection.
     *
     * @param  array<string, string>  $categoryMap  PIM-Node-ID → Shopify-Collection-ID
     * @return array{success: bool, menu_id: string|null, error: string|null}
     */
    public function syncNavigationMenu(
        PendingRequest $http,
        string $shopUrl,
        string $hierarchyId,
        array $categoryMap,
        string $language = 'de',
    ): array {
        $shopUrl = rtrim($shopUrl, '/');

        $hierarchy = Hierarchy::find($hierarchyId);
        if (!$hierarchy) {
            return ['success' => false, 'menu_id' => null, 'error' => 'Hierarchie nicht gefunden'];
        }

        $hierarchyName = $language === 'en' && $hierarchy->name_en
            ? $hierarchy->name_en
            : ($hierarchy->name_de ?: 'PIM Katalog');

        // Alle aktiven Knoten laden (nach depth + sort_order)
        $allNodes = $hierarchy->nodes()
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->get();

        if ($allNodes->isEmpty()) {
            return ['success' => false, 'menu_id' => null, 'error' => 'Keine aktiven Knoten'];
        }

        // Verschachtelte MenuItem-Struktur aufbauen
        $menuItems = $this->buildMenuItemTree($allNodes, $categoryMap, $language);

        if (empty($menuItems)) {
            return ['success' => false, 'menu_id' => null, 'error' => 'Keine Menu-Items erstellt'];
        }

        // Handle fuer das Menu (deterministisch, damit Update statt Create moeglich)
        $menuHandle = 'pim-' . substr(md5($hierarchyId), 0, 8);

        // Zuerst versuchen das bestehende Menu zu loeschen (GraphQL menuDelete)
        $this->deleteExistingMenu($http, $shopUrl, $menuHandle);

        // GraphQL Mutation: menuCreate
        $graphqlQuery = <<<'GRAPHQL'
mutation menuCreate($title: String!, $handle: String!, $items: [MenuItemCreateInput!]!) {
  menuCreate(title: $title, handle: $handle, items: $items) {
    menu {
      id
      title
      handle
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        try {
            $response = $http->post(
                "{$shopUrl}/admin/api/" . self::API_VERSION . '/graphql.json',
                [
                    'query'     => $graphqlQuery,
                    'variables' => [
                        'title'  => $hierarchyName,
                        'handle' => $menuHandle,
                        'items'  => $menuItems,
                    ],
                ],
            );
            $response->throw();
            $data = $response->json();

            // GraphQL-Fehler pruefen
            $userErrors = $data['data']['menuCreate']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                $errorMsg = collect($userErrors)->pluck('message')->implode(', ');
                Log::channel('connectors')->warning('Navigation Menu userErrors', ['errors' => $userErrors]);
                return ['success' => false, 'menu_id' => null, 'error' => $errorMsg];
            }

            $menuId = $data['data']['menuCreate']['menu']['id'] ?? null;

            return ['success' => true, 'menu_id' => $menuId, 'error' => null];
        } catch (\Throwable $e) {
            $error = $e instanceof \Illuminate\Http\Client\RequestException
                ? $this->parseShopifyError($e)
                : $e->getMessage();

            Log::channel('connectors')->error('Navigation Menu Sync fehlgeschlagen', ['error' => $error]);

            return ['success' => false, 'menu_id' => null, 'error' => $error];
        }
    }

    /**
     * Baut eine verschachtelte MenuItem-Struktur aus PIM-Hierarchie-Knoten.
     *
     * Shopify unterstuetzt max 3 Ebenen Verschachtelung.
     */
    private function buildMenuItemTree($nodes, array $categoryMap, string $language, int $maxDepth = 3): array
    {
        // Knoten nach Parent gruppieren
        $childrenMap = [];
        foreach ($nodes as $node) {
            $parentId = $node->parent_node_id ?: '__root__';
            $childrenMap[$parentId][] = $node;
        }

        // Rekursiv aufbauen
        return $this->buildMenuItems($childrenMap, '__root__', $categoryMap, $language, 0, $maxDepth);
    }

    private function buildMenuItems(array $childrenMap, string $parentId, array $categoryMap, string $language, int $depth, int $maxDepth): array
    {
        if ($depth >= $maxDepth || !isset($childrenMap[$parentId])) {
            return [];
        }

        $items = [];
        foreach ($childrenMap[$parentId] as $node) {
            $name = $language === 'en' && $node->name_en ? $node->name_en : $node->name_de;
            $name = $name ?: 'Kategorie';

            $collectionId = $categoryMap[$node->id] ?? null;

            $menuItem = [
                'title' => $name,
            ];

            // Wenn Collection vorhanden: als Collection-Link
            if ($collectionId) {
                $menuItem['type'] = 'COLLECTION';
                $menuItem['resourceId'] = "gid://shopify/Collection/{$collectionId}";
            } else {
                // Kein Collection-Link: als HTTP-Link oder ohne Link
                $menuItem['type'] = 'HTTP';
                $menuItem['url'] = '#';
            }

            // Kinder rekursiv
            $children = $this->buildMenuItems($childrenMap, $node->id, $categoryMap, $language, $depth + 1, $maxDepth);
            if (!empty($children)) {
                $menuItem['items'] = $children;
            }

            $items[] = $menuItem;
        }

        return $items;
    }

    /**
     * Loescht ein bestehendes Navigation Menu via GraphQL (fuer Update via Delete+Create).
     */
    private function deleteExistingMenu(PendingRequest $http, string $shopUrl, string $handle): void
    {
        // Zuerst Menu-ID per Handle finden
        $findQuery = <<<'GRAPHQL'
query findMenu($handle: String!) {
  menu(handle: $handle) {
    id
  }
}
GRAPHQL;

        try {
            $response = $http->post(
                "{$shopUrl}/admin/api/" . self::API_VERSION . '/graphql.json',
                [
                    'query'     => $findQuery,
                    'variables' => ['handle' => $handle],
                ],
            );
            $response->throw();
            $data = $response->json();

            $menuId = $data['data']['menu']['id'] ?? null;
            if (!$menuId) {
                return; // Kein bestehendes Menu
            }

            // Menu loeschen
            $deleteQuery = <<<'GRAPHQL'
mutation menuDelete($id: ID!) {
  menuDelete(id: $id) {
    deletedMenuId
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

            $http->post(
                "{$shopUrl}/admin/api/" . self::API_VERSION . '/graphql.json',
                [
                    'query'     => $deleteQuery,
                    'variables' => ['id' => $menuId],
                ],
            )->throw();
        } catch (\Throwable) {
            // Nicht kritisch — Menu wird einfach neu erstellt
        }
    }

    /**
     * Parst Shopify API-Fehler in eine lesbare Meldung.
     */
    private function parseShopifyError(\Illuminate\Http\Client\RequestException $e): string
    {
        $body = $e->response?->json();

        if (isset($body['errors']) && is_string($body['errors'])) {
            return 'Shopify: ' . $body['errors'];
        }

        if (isset($body['errors']) && is_array($body['errors'])) {
            $messages = [];
            foreach ($body['errors'] as $field => $fieldErrors) {
                if (is_array($fieldErrors)) {
                    foreach ($fieldErrors as $err) {
                        $messages[] = "{$field}: {$err}";
                    }
                } else {
                    $messages[] = is_string($fieldErrors) ? $fieldErrors : json_encode($fieldErrors);
                }
            }
            if (!empty($messages)) {
                return 'Shopify: ' . implode(' | ', $messages);
            }
        }

        return 'Shopify HTTP ' . ($e->response?->status() ?? '?') . ': ' . $e->getMessage();
    }
}
