<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\ConnectorSyncLog;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

class ShopwareCategoryService
{
    /**
     * Synchronisiert eine PIM-Hierarchie als Shopware-Kategoriebaum.
     *
     * @return array{synced: int, errors: int, category_map: array<string, string>}
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
            return ['synced' => 0, 'errors' => 0, 'category_map' => [], 'hierarchy_name' => ''];
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

        foreach ($allNodes as $node) {
            $shopwareCategoryId = $categoryMap[$node->id] ?? Str::uuid()->toString();
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
            } catch (\Throwable $e) {
                $errors += count($chunk);
            }
        }

        return [
            'synced'         => $synced,
            'errors'         => $errors,
            'category_map'   => $categoryMap,
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
}
