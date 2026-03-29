<?php

declare(strict_types=1);

namespace App\Services\Connectors\SalesforceCommerce;

use App\Models\ConnectorSyncLog;
use App\Models\Hierarchy;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;

class SalesforceCommerceCategoryService
{
    private const API_VERSION = 'v24_5';

    /**
     * Synchronisiert eine PIM-Hierarchie als SFCC-Kategoriebaum in einem Katalog.
     *
     * @return array{synced: int, errors: int, error_details: array, category_map: array<string, string>, hierarchy_name: string, total_nodes: int}
     */
    public function syncHierarchy(
        PendingRequest $http,
        string $instanceUrl,
        string $connectionId,
        string $hierarchyId,
        string $catalogId,
        string $language = 'de',
        array $excludedNodeIds = [],
    ): array {
        $instanceUrl = rtrim($instanceUrl, '/');

        $hierarchy = Hierarchy::find($hierarchyId);
        if (!$hierarchy) {
            return ['synced' => 0, 'errors' => 0, 'error_details' => ['Hierarchie nicht gefunden'], 'category_map' => [], 'hierarchy_name' => '', 'total_nodes' => 0];
        }

        // Alle aktiven Knoten laden (Tiefe aufsteigend → Eltern zuerst)
        $nodesQuery = $hierarchy->nodes()
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('sort_order');

        if (!empty($excludedNodeIds)) {
            $nodesQuery->whereNotIn('id', $excludedNodeIds);
        }

        $allNodes = $nodesQuery->get();
        $hierarchyName = $language === 'en' && $hierarchy->name_en ? $hierarchy->name_en : $hierarchy->name_de;

        if ($allNodes->isEmpty()) {
            return [
                'synced'         => 0,
                'errors'         => 0,
                'error_details'  => [],
                'category_map'   => [],
                'hierarchy_name' => $hierarchyName,
                'total_nodes'    => 0,
            ];
        }

        // Bestehende SFCC-Kategorie-IDs aus Sync-Logs laden
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
            // SFCC Kategorie-ID: technical_name oder slug-ified Name
            $sfccCategoryId = $categoryMap[$node->id]
                ?? $this->generateCategoryId($node);

            $name = $language === 'en' && $node->name_en ? $node->name_en : $node->name_de;

            $categoryData = [
                'name'        => ['default' => $name ?: 'Kategorie'],
                'online'      => true,
                'page_title'  => ['default' => $name ?: ''],
            ];

            // Eltern-Kategorie zuordnen
            if ($node->parent_node_id && isset($categoryMap[$node->parent_node_id])) {
                $categoryData['parent_category_id'] = $categoryMap[$node->parent_node_id];
            } else {
                $categoryData['parent_category_id'] = 'root';
            }

            try {
                $response = $http->put(
                    "{$instanceUrl}/s/-/dw/data/" . self::API_VERSION
                    . "/catalogs/{$catalogId}/categories/{$sfccCategoryId}",
                    $categoryData,
                );

                $response->throw();

                $categoryMap[$node->id] = $sfccCategoryId;
                $synced++;
            } catch (\Throwable $e) {
                $errors++;
                $errorMsg = "Kategorie '{$name}' ({$sfccCategoryId}): " . $e->getMessage();
                $errorDetails[] = $errorMsg;

                Log::channel('connectors')->warning('SFCC Kategorie-Sync fehlgeschlagen', [
                    'node_id'     => $node->id,
                    'category_id' => $sfccCategoryId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return [
            'synced'         => $synced,
            'errors'         => $errors,
            'error_details'  => $errorDetails,
            'category_map'   => $categoryMap,
            'hierarchy_name' => $hierarchyName,
            'total_nodes'    => $allNodes->count(),
        ];
    }

    /**
     * Loescht eine Kategorie aus dem SFCC-Katalog.
     */
    public function deleteCategory(
        PendingRequest $http,
        string $instanceUrl,
        string $catalogId,
        string $categoryId,
    ): bool {
        $instanceUrl = rtrim($instanceUrl, '/');

        try {
            $http->delete(
                "{$instanceUrl}/s/-/dw/data/" . self::API_VERSION
                . "/catalogs/{$catalogId}/categories/{$categoryId}",
            )->throw();
            return true;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response?->status() === 404) {
                return true;
            }
            throw $e;
        }
    }

    /**
     * Generiert eine SFCC-kompatible Kategorie-ID aus dem Knoten.
     */
    private function generateCategoryId($node): string
    {
        // Bevorzuge technical_name/slug falls vorhanden
        if (!empty($node->technical_name)) {
            return $this->sanitizeCategoryId($node->technical_name);
        }

        // Fallback: Name sanitizen
        $name = $node->name_de ?: $node->name_en ?: 'cat';
        return $this->sanitizeCategoryId($name) . '-' . substr($node->id, 0, 8);
    }

    /**
     * Sanitized einen String fuer die Verwendung als SFCC Kategorie-ID.
     */
    private function sanitizeCategoryId(string $input): string
    {
        $id = mb_strtolower($input);
        $id = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $id);
        $id = preg_replace('/[^a-z0-9_-]/', '-', $id);
        $id = preg_replace('/-+/', '-', $id);
        return trim($id, '-');
    }
}
