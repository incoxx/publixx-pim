<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\ConnectorSyncLog;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopwareCategoryService
{
    /**
     * Synchronisiert eine PIM-Hierarchie als Shopware-Kategoriebaum.
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

        // Bestehende Shopware-Kategorie-IDs aus Sync-Logs laden (pro Connection!)
        $existingMap = ConnectorSyncLog::where('connector_connection_id', $connectionId)
            ->where('action', 'category_sync')
            ->where('entity_type', 'hierarchy_node')
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->whereIn('entity_id', $allNodes->pluck('id'))
            ->get()
            ->mapWithKeys(fn ($log) => [$log->entity_id => $log->external_id])
            ->toArray();

        // Kategorie-Payloads aufbauen (Reihenfolge: depth aufsteigend → Eltern zuerst)
        $categoryMap = $existingMap;
        $payload = [];
        $synced = 0;
        $errors = 0;
        $errorDetails = [];

        foreach ($allNodes as $node) {
            $shopwareCategoryId = $categoryMap[$node->id] ?? str_replace('-', '', Str::uuid()->toString());
            $categoryMap[$node->id] = $shopwareCategoryId;

            $name = $language === 'en' && $node->name_en ? $node->name_en : $node->name_de;

            $categoryData = [
                'id'     => $shopwareCategoryId,
                'name'   => $name ?: 'Kategorie',
                'active' => true,
            ];

            // Eltern-Kategorie zuordnen
            if ($node->parent_node_id && isset($categoryMap[$node->parent_node_id])) {
                $categoryData['parentId'] = $categoryMap[$node->parent_node_id];
            }

            $payload[] = $categoryData;
        }

        // In Chunks von 50 an Shopware senden
        $successNodeIds = [];
        $chunks = array_chunk($payload, 50);
        foreach ($chunks as $chunk) {
            try {
                $response = $http->post("{$shopUrl}/api/_action/sync", [
                    'write-categories' => [
                        'action'  => 'upsert',
                        'entity'  => 'category',
                        'payload' => $chunk,
                    ],
                ]);
                $response->throw();
                $synced += count($chunk);
                // Diese Knoten waren erfolgreich
                foreach ($chunk as $catData) {
                    $successNodeIds[] = $catData['id'];
                }
            } catch (\Throwable $e) {
                $errors += count($chunk);
                $errorMessage = $e instanceof \Illuminate\Http\Client\RequestException
                    ? $this->parseShopwareError($e)
                    : $e->getMessage();
                $errorDetails[] = $errorMessage;

                Log::channel('connectors')->error("Kategorie-Sync fehlgeschlagen", [
                    'chunk_size' => count($chunk),
                    'error'      => $errorMessage,
                ]);
            }
        }

        // Nur erfolgreich synchronisierte Knoten in der Map behalten
        $successMap = [];
        foreach ($categoryMap as $nodeId => $shopwareId) {
            if (in_array($shopwareId, $successNodeIds) || isset($existingMap[$nodeId])) {
                $successMap[$nodeId] = $shopwareId;
            }
        }

        return [
            'synced'         => $synced,
            'errors'         => $errors,
            'error_details'  => $errorDetails,
            'category_map'   => $successMap,
            'hierarchy_name' => $language === 'en' && $hierarchy->name_en ? $hierarchy->name_en : $hierarchy->name_de,
            'total_nodes'    => $allNodes->count(),
        ];
    }

    /**
     * Weist ein Produkt einer Shopware-Kategorie zu.
     */
    public function assignProductToCategory(
        PendingRequest $http,
        string $shopUrl,
        string $productId,
        string $categoryId,
    ): void {
        $shopUrl = rtrim($shopUrl, '/');

        $http->post("{$shopUrl}/api/_action/sync", [
            'write-product-category' => [
                'action'  => 'upsert',
                'entity'  => 'product_category',
                'payload' => [
                    [
                        'productId'  => $productId,
                        'categoryId' => $categoryId,
                    ],
                ],
            ],
        ])->throw();
    }

    /**
     * Parst Shopware API-Fehler in eine lesbare Meldung.
     */
    private function parseShopwareError(\Illuminate\Http\Client\RequestException $e): string
    {
        $body = $e->response?->json();

        if (isset($body['errors']) && is_array($body['errors'])) {
            $messages = [];
            foreach ($body['errors'] as $error) {
                $detail = $error['detail'] ?? '';
                $source = $error['source']['pointer'] ?? '';
                if ($source && $detail) {
                    $messages[] = "{$source}: {$detail}";
                } elseif ($detail) {
                    $messages[] = $detail;
                }
            }
            if (!empty($messages)) {
                return 'Shopware: ' . implode(' | ', $messages);
            }
        }

        return 'HTTP ' . ($e->response?->status() ?? '?') . ': ' . substr($e->getMessage(), 0, 500);
    }
}
