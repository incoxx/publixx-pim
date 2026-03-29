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

                Log::channel('stack')->error('Collection-Sync fehlgeschlagen', [
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
     * Fuegt PIM-Kategorien als Unterpunkte in das bestehende Shopify Main menu ein.
     *
     * Workflow:
     * 1. Main menu laden (bestehende Items mit IDs)
     * 2. Alte PIM-Items entfernen (erkennbar an pim-marker Prefix)
     * 3. Neue PIM-Items hinzufuegen (verschachtelt, max 3 Ebenen)
     * 4. menuUpdate mit allen Items (bestehende + neue)
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
        $graphqlUrl = "{$shopUrl}/admin/api/" . self::API_VERSION . '/graphql.json';

        $hierarchy = Hierarchy::find($hierarchyId);
        if (!$hierarchy) {
            return ['success' => false, 'menu_id' => null, 'error' => 'Hierarchie nicht gefunden'];
        }

        $hierarchyName = $language === 'en' && $hierarchy->name_en
            ? $hierarchy->name_en
            : ($hierarchy->name_de ?: 'Katalog');

        // PIM-Knoten laden
        $allNodes = $hierarchy->nodes()
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->get();

        if ($allNodes->isEmpty()) {
            return ['success' => false, 'menu_id' => null, 'error' => 'Keine aktiven Knoten'];
        }

        // PIM-Items als verschachtelte Struktur aufbauen
        $pimMenuItems = $this->buildMenuItemTree($allNodes, $categoryMap, $language);

        if (empty($pimMenuItems)) {
            return ['success' => false, 'menu_id' => null, 'error' => 'Keine Menu-Items erstellt'];
        }

        // 1. Main menu laden
        $mainMenu = $this->fetchMainMenu($http, $graphqlUrl);
        if (!$mainMenu) {
            // Fallback: separates Menu erstellen
            return $this->createSeparateMenu($http, $graphqlUrl, $hierarchyName, $pimMenuItems, $hierarchyId);
        }

        $menuId = $mainMenu['id'];
        $existingItems = $mainMenu['items'] ?? [];

        // 2. Bestehende Items behalten (ohne alte PIM-Items)
        // PIM-Items sind erkennbar: title startet mit "[PIM]" oder url enthaelt pim-collection
        $keepItems = [];
        foreach ($existingItems as $item) {
            // Alte PIM-Items ueberspringen (werden durch neue ersetzt)
            if (str_starts_with($item['title'] ?? '', '[PIM] ')) {
                continue;
            }
            $keepItems[] = $this->convertExistingItemForUpdate($item);
        }

        // 3. PIM-Items als Dach-Element mit Unterpunkten hinzufuegen
        $pimRootItem = [
            'title' => "[PIM] {$hierarchyName}",
            'type'  => 'HTTP',
            'url'   => '#',
            'items' => $pimMenuItems,
        ];

        $allItems = array_merge($keepItems, [$pimRootItem]);

        // 4. Menu aktualisieren
        $updateQuery = <<<'GRAPHQL'
mutation menuUpdate($id: ID!, $title: String!, $handle: String!, $items: [MenuItemUpdateInput!]!) {
  menuUpdate(id: $id, title: $title, handle: $handle, items: $items) {
    menu {
      id
      title
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        try {
            $response = $http->post($graphqlUrl, [
                'query'     => $updateQuery,
                'variables' => [
                    'id'     => $menuId,
                    'title'  => $mainMenu['title'] ?? 'Main menu',
                    'handle' => $mainMenu['handle'] ?? 'main-menu',
                    'items'  => $allItems,
                ],
            ]);
            $response->throw();
            $data = $response->json();

            $userErrors = $data['data']['menuUpdate']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                $errorMsg = collect($userErrors)->pluck('message')->implode(', ');
                Log::channel('stack')->warning('Menu Update userErrors', ['errors' => $userErrors]);
                return ['success' => false, 'menu_id' => $menuId, 'error' => $errorMsg];
            }

            return ['success' => true, 'menu_id' => $menuId, 'error' => null];
        } catch (\Throwable $e) {
            $error = $e instanceof \Illuminate\Http\Client\RequestException
                ? $this->parseShopifyError($e)
                : $e->getMessage();

            Log::channel('stack')->error('Main Menu Update fehlgeschlagen', ['error' => $error]);

            return ['success' => false, 'menu_id' => $menuId, 'error' => $error];
        }
    }

    /**
     * Laedt das Main menu mit allen bestehenden Items via GraphQL.
     * Laedt alle Menus und sucht nach Handle oder Titel.
     */
    private function fetchMainMenu(PendingRequest $http, string $graphqlUrl): ?array
    {
        // Admin API: menu() braucht id, nicht handle.
        // Daher menus() laden und per handle filtern.
        // Menu.items ist plain Array [MenuItem!]! — kein first, kein nodes.
        $query = <<<'GRAPHQL'
{
  menus(first: 10) {
    edges {
      node {
        id
        title
        handle
        items {
          id
          title
          type
          url
          resourceId
          items {
            id
            title
            type
            url
            resourceId
            items {
              id
              title
              type
              url
              resourceId
            }
          }
        }
      }
    }
  }
}
GRAPHQL;

        try {
            $response = $http->post($graphqlUrl, ['query' => $query]);
            $response->throw();
            $data = $response->json();

            if (!empty($data['errors'])) {
                Log::channel('stack')->error('GraphQL Fehler beim Menu-Laden', [
                    'errors' => array_map(fn ($e) => $e['message'] ?? '', $data['errors']),
                ]);
                return null;
            }

            // edges/node Pattern auflösen
            $menus = [];
            foreach ($data['data']['menus']['edges'] ?? [] as $edge) {
                if (!empty($edge['node'])) {
                    $menus[] = $edge['node'];
                }
            }

            if (empty($menus)) {
                Log::channel('stack')->warning('Keine Menus gefunden');
                return null;
            }

            Log::channel('stack')->info('Menus geladen: ' . count($menus), [
                'handles' => array_map(fn ($m) => $m['handle'] ?? '?', $menus),
            ]);

            // Nach Handle suchen
            foreach ($menus as $menu) {
                if (in_array($menu['handle'] ?? '', ['main-menu', 'main', 'header'])) {
                    Log::channel('stack')->info("Main menu gefunden: handle={$menu['handle']}, id={$menu['id']}");
                    return $menu;
                }
            }

            // Nach Titel suchen
            foreach ($menus as $menu) {
                if (str_contains(strtolower($menu['title'] ?? ''), 'main')) {
                    return $menu;
                }
            }

            // Erstes Menu
            return $menus[0];
        } catch (\Throwable $e) {
            Log::channel('stack')->error('Menus laden fehlgeschlagen: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Konvertiert ein bestehendes MenuItem in das MenuItemUpdateInput-Format.
     * Bestehende Items muessen ihre ID behalten damit sie nicht geloescht werden.
     */
    private function convertExistingItemForUpdate(array $item): array
    {
        $result = [
            'id'    => $item['id'],
            'title' => $item['title'],
        ];

        // Typ und Ressource beibehalten
        if (!empty($item['type'])) {
            $result['type'] = $item['type'];
        }
        if (!empty($item['url'])) {
            $result['url'] = $item['url'];
        }
        if (!empty($item['resourceId'])) {
            $result['resourceId'] = $item['resourceId'];
        }

        // Kinder rekursiv (MenuItem.items ist ein plain Array, kein Connection)
        $children = $item['items'] ?? [];
        if (!empty($children)) {
            $result['items'] = array_map(
                fn ($child) => $this->convertExistingItemForUpdate($child),
                $children,
            );
        }

        return $result;
    }

    /**
     * Fallback: Erstellt ein separates Menu wenn Main menu nicht gefunden wird.
     */
    private function createSeparateMenu(PendingRequest $http, string $graphqlUrl, string $title, array $items, string $hierarchyId): array
    {
        $menuHandle = 'pim-' . substr(md5($hierarchyId), 0, 8);

        // Bestehendes PIM-Menu loeschen
        $this->deleteExistingMenu($http, $graphqlUrl, $menuHandle);

        $createQuery = <<<'GRAPHQL'
mutation menuCreate($title: String!, $handle: String!, $items: [MenuItemCreateInput!]!) {
  menuCreate(title: $title, handle: $handle, items: $items) {
    menu { id title handle }
    userErrors { field message }
  }
}
GRAPHQL;

        try {
            $response = $http->post($graphqlUrl, [
                'query'     => $createQuery,
                'variables' => [
                    'title'  => $title,
                    'handle' => $menuHandle,
                    'items'  => $items,
                ],
            ]);
            $response->throw();
            $data = $response->json();

            $userErrors = $data['data']['menuCreate']['userErrors'] ?? [];
            if (!empty($userErrors)) {
                return ['success' => false, 'menu_id' => null, 'error' => collect($userErrors)->pluck('message')->implode(', ')];
            }

            $menuId = $data['data']['menuCreate']['menu']['id'] ?? null;
            return ['success' => true, 'menu_id' => $menuId, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'menu_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Loescht ein bestehendes Menu via GraphQL (fuer Fallback-Menu).
     */
    private function deleteExistingMenu(PendingRequest $http, string $graphqlUrl, string $handle): void
    {
        $findQuery = <<<'GRAPHQL'
query findMenu($handle: String!) {
  menu(handle: $handle) {
    id
  }
}
GRAPHQL;

        try {
            $response = $http->post($graphqlUrl, [
                'query'     => $findQuery,
                'variables' => ['handle' => $handle],
            ]);
            $response->throw();
            $data = $response->json();

            $menuId = $data['data']['menu']['id'] ?? null;
            if (!$menuId) {
                return;
            }

            $deleteQuery = <<<'GRAPHQL'
mutation menuDelete($id: ID!) {
  menuDelete(id: $id) {
    deletedMenuId
    userErrors { field message }
  }
}
GRAPHQL;

            $http->post($graphqlUrl, [
                'query'     => $deleteQuery,
                'variables' => ['id' => $menuId],
            ])->throw();
        } catch (\Throwable) {
            // Nicht kritisch
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
