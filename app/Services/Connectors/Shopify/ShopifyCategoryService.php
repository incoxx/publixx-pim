<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopify;

use App\Models\ConnectorSyncLog;
use App\Models\Hierarchy;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;

class ShopifyCategoryService
{
    private const API_VERSION = '2025-01';

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
