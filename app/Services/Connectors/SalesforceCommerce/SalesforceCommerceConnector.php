<?php

declare(strict_types=1);

namespace App\Services\Connectors\SalesforceCommerce;

use App\Models\ConnectorConnection;
use App\Models\ConnectorProductChecksum;
use App\Models\ConnectorSyncLog;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\WebsiteProfile;
use App\Services\Connectors\AbstractConnector;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SalesforceCommerceConnector extends AbstractConnector
{
    private const API_VERSION = 'v24_5';

    public function __construct(
        private readonly SalesforceCommerceAuthService $authService,
        private readonly SalesforceCommerceProductService $productService,
        private readonly SalesforceCommerceMediaService $mediaService,
        private readonly SalesforceCommerceCategoryService $categoryService,
        private readonly SalesforceCommerceChecksumService $checksumService,
    ) {}

    /**
     * Gibt einen authentifizierten HTTP-Client zurueck (fuer Controller-Zugriff).
     */
    public function getAuthenticatedRequest(ConnectorConnection $connection): PendingRequest
    {
        return $this->authenticatedRequest($connection);
    }

    public function getType(): string
    {
        return 'salesforce_commerce';
    }

    public function getName(): string
    {
        return 'Salesforce Commerce Cloud';
    }

    public function getDescription(): string
    {
        return 'Salesforce Commerce Cloud (SFCC) OCAPI – Produkte und Kataloge synchronisieren';
    }

    public function getCapabilities(): array
    {
        return ['asset_upload', 'product_data', 'profile_sync'];
    }

    public function isConfigured(): bool
    {
        return $this->authService->isConfigured();
    }

    public function getAuthorizationUrl(): array
    {
        // SFCC nutzt Client Credentials, kein Browser-Redirect noetig
        return [
            'url'           => '',
            'state'         => '',
            'code_verifier' => null,
        ];
    }

    public function handleCallback(string $code, ?string $codeVerifier = null, string $shopUrl = ''): array
    {
        // code = client_id, codeVerifier = client_secret, shopUrl = instance_url
        return $this->authService->authenticate($code, $codeVerifier ?? '', $shopUrl);
    }

    public function refreshToken(ConnectorConnection $connection): void
    {
        $this->authService->refreshAccessToken($connection);
    }

    /**
     * HTTP-Request mit SFCC Bearer Token.
     */
    protected function authenticatedRequest(ConnectorConnection $connection): PendingRequest
    {
        if ($connection->isTokenExpired()) {
            $this->refreshToken($connection);
            $connection->refresh();
        }

        return Http::withToken($connection->access_token)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(30)
            ->retry(3, 1000, fn ($e) => $e instanceof \Illuminate\Http\Client\RequestException && $e->response?->status() === 429);
    }

    public function uploadAsset(ConnectorConnection $connection, Media $media): string
    {
        // SFCC Bilder werden per URL-Referenz in image_groups gesetzt, kein standalone Upload
        throw new \RuntimeException('SFCC unterstuetzt keinen standalone Media-Upload. Medien werden per URL-Referenz im Produkt-Sync gesetzt.');
    }

    // ─── Produkt-Sync ────────────────────────────────────────────

    public function pushProductData(ConnectorConnection $connection, Product $product, array $options = []): array
    {
        $http = $this->authenticatedRequest($connection);
        $instanceUrl = $this->getInstanceUrl($connection);
        $language = $options['language'] ?? 'de';
        $settings = $connection->settings ?? [];
        $sfccFields = $settings['sfcc_fields'] ?? [];
        $profileId = $settings['website_profile_id'] ?? null;

        $useProfileSync = !empty($sfccFields) || $profileId;

        try {
            if ($useProfileSync) {
                $profilePayload = [];
                if ($profileId) {
                    $profile = WebsiteProfile::find($profileId);
                    $profilePayload = $profile?->payload ?? [];
                }

                // Kategorie-Zuordnung (aus vorherigem Hierarchie-Sync)
                $categoryId = null;
                if ($product->master_hierarchy_node_id) {
                    $categoryId = $this->getCategoryIdForNode($connection->id, $product->master_hierarchy_node_id);
                }

                $result = $this->productService->syncProductFromProfile(
                    $http, $instanceUrl, $product, $profilePayload, $sfccFields, $language, $categoryId,
                );

                // Medien synchronisieren (URL-Referenz)
                $syncMedia = $sfccFields['_sync_media']['enabled'] ?? true;
                if ($syncMedia) {
                    $this->syncProductMedia($http, $instanceUrl, $connection, $product, $result['product_id'] ?? $product->sku);
                }
            } else {
                $result = $this->productService->syncProduct(
                    $http, $instanceUrl, $product, $language, $options,
                );
            }

            $externalId = $result['product_id'] ?? null;

            $this->logSync(
                $connection, 'product_push', 'product',
                $product->id, 'success', $externalId, null,
                [
                    'language'      => $language,
                    'sku'           => $product->sku,
                    'product_name'  => $product->name,
                    'synced_fields' => $result['synced_data'] ?? array_keys($result),
                ],
            );

            return $result;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $errorDetail = $this->parseSfccError($e);

            $this->logSync(
                $connection, 'product_push', 'product',
                $product->id, 'failed', null, $errorDetail,
                [
                    'language'     => $language,
                    'sku'          => $product->sku,
                    'product_name' => $product->name,
                    'http_status'  => $e->response?->status(),
                ],
            );

            throw $e;
        }
    }

    // ─── Hierarchie-Sync ─────────────────────────────────────────

    /**
     * Synchronisiert den Hierarchie-Baum als SFCC-Kategorien.
     */
    public function syncHierarchy(ConnectorConnection $connection): array
    {
        $settings = $connection->settings ?? [];
        $profileId = $settings['website_profile_id'] ?? null;

        if (!$profileId) {
            throw new \RuntimeException('Kein Vorschau-Profil konfiguriert.');
        }

        $profile = WebsiteProfile::findOrFail($profileId);
        $payload = $profile->payload ?? [];
        $hierarchyId = $payload['hierarchy_id'] ?? null;

        if (!$hierarchyId) {
            throw new \RuntimeException('Keine Hierarchie im Vorschau-Profil konfiguriert.');
        }

        $language = $payload['default_locale'] ?? 'de';
        $excludedNodeIds = $payload['catalog_excluded_node_ids'] ?? [];

        $http = $this->authenticatedRequest($connection);
        $instanceUrl = $this->getInstanceUrl($connection);
        $catalogId = $this->getCatalogId($connection);

        $catResult = $this->categoryService->syncHierarchy(
            $http, $instanceUrl, $connection->id, $hierarchyId, $catalogId, $language, $excludedNodeIds,
        );

        // Sync-Logs fuer erfolgreiche Kategorien
        foreach ($catResult['category_map'] as $nodeId => $sfccCategoryId) {
            $this->logSync(
                $connection, 'category_sync', 'hierarchy_node',
                $nodeId, 'success', $sfccCategoryId,
            );
        }

        // Fehler loggen
        foreach ($catResult['error_details'] ?? [] as $errorDetail) {
            $this->logSync(
                $connection, 'category_sync', 'hierarchy',
                $hierarchyId, 'failed', null, $errorDetail,
            );
        }

        return [
            'synced'         => $catResult['synced'],
            'errors'         => $catResult['errors'],
            'error_details'  => $catResult['error_details'] ?? [],
            'total_nodes'    => $catResult['total_nodes'],
            'hierarchy_name' => $catResult['hierarchy_name'],
        ];
    }

    // ─── Profil-Sync ─────────────────────────────────────────────

    /**
     * Produkt-Sync basierend auf einem WebsiteProfile.
     *
     * @return array{products: array{success: int, failed: int}, media: array{success: int, failed: int}}
     */
    public function syncFromProfile(ConnectorConnection $connection): array
    {
        $settings = $connection->settings ?? [];
        $profileId = $settings['website_profile_id'] ?? null;
        $sfccFields = $settings['sfcc_fields'] ?? [];

        if (!$profileId) {
            throw new \RuntimeException('Kein Vorschau-Profil konfiguriert. Bitte ein Profil in den Verbindungseinstellungen auswaehlen.');
        }

        $profile = WebsiteProfile::findOrFail($profileId);
        $payload = $profile->payload ?? [];
        $language = $payload['default_locale'] ?? 'de';

        $http = $this->authenticatedRequest($connection);
        $instanceUrl = $this->getInstanceUrl($connection);

        $result = [
            'products' => ['success' => 0, 'failed' => 0],
            'media'    => ['success' => 0, 'failed' => 0],
        ];

        // Produkte aus Hierarchie laden
        $products = $this->loadProductsFromProfile($payload);

        // In Chunks synchronisieren
        $products->chunk(50, function ($chunk) use (
            $http, $instanceUrl, $connection, $payload, $sfccFields, $language, &$result,
        ) {
            // Batch: Checksums berechnen (fuer spaeteres Delta-Sync)
            $chunkChecksums = $this->checksumService->computeChecksumsBatch(
                $chunk, $payload, $sfccFields, $language,
            );

            foreach ($chunk as $product) {
                try {
                    // Kategorie-Zuordnung
                    $categoryId = null;
                    if ($product->master_hierarchy_node_id) {
                        $categoryId = $this->getCategoryIdForNode($connection->id, $product->master_hierarchy_node_id);
                    }

                    $productResult = $this->productService->syncProductFromProfile(
                        $http, $instanceUrl, $product, $payload, $sfccFields, $language, $categoryId,
                    );

                    $externalId = $productResult['product_id'] ?? $product->sku;

                    // Medien synchronisieren (URL-Referenz)
                    $syncMedia = $sfccFields['_sync_media']['enabled'] ?? true;
                    if ($syncMedia) {
                        $mediaResult = $this->syncProductMedia($http, $instanceUrl, $connection, $product, $externalId);
                        $result['media']['success'] += $mediaResult['synced'] ?? 0;
                    }

                    // Checksum speichern
                    $checksum = $chunkChecksums[$product->id] ?? null;
                    if ($checksum) {
                        ConnectorProductChecksum::updateOrCreate(
                            ['connection_id' => $connection->id, 'product_id' => $product->id],
                            ['checksum' => $checksum, 'synced_at' => now(), 'external_id' => $externalId],
                        );
                    }

                    $this->logSync(
                        $connection, 'profile_sync', 'product',
                        $product->id, 'success', $externalId, null,
                        [
                            'language'      => $language,
                            'sku'           => $product->sku,
                            'product_name'  => $product->name,
                            'synced_fields' => $productResult['synced_data'] ?? [],
                        ],
                    );

                    $result['products']['success']++;
                } catch (\Throwable $e) {
                    $errorDetail = $e instanceof \Illuminate\Http\Client\RequestException
                        ? $this->parseSfccError($e)
                        : $e->getMessage();

                    $this->logSync(
                        $connection, 'profile_sync', 'product',
                        $product->id, 'failed', null, $errorDetail,
                        [
                            'sku'          => $product->sku,
                            'product_name' => $product->name,
                            'http_status'  => $e instanceof \Illuminate\Http\Client\RequestException
                                ? $e->response?->status()
                                : null,
                        ],
                    );
                    $result['products']['failed']++;

                    Log::channel('connectors')->error("SFCC Profil-Sync fehlgeschlagen: {$product->sku}", [
                        'error' => $errorDetail,
                    ]);
                }
            }
        });

        return $result;
    }

    // ─── Delta-Sync ──────────────────────────────────────────────

    /**
     * Delta-Sync: Nur neue und geaenderte Produkte synchronisieren.
     *
     * @return array{products: array{new: int, updated: int, unchanged: int, failed: int}, media: array{success: int, failed: int}, orphans: array}
     */
    public function deltaSyncFromProfile(ConnectorConnection $connection): array
    {
        $settings = $connection->settings ?? [];
        $profileId = $settings['website_profile_id'] ?? null;
        $sfccFields = $settings['sfcc_fields'] ?? [];

        if (!$profileId) {
            throw new \RuntimeException('Kein Vorschau-Profil konfiguriert. Bitte ein Profil in den Verbindungseinstellungen auswaehlen.');
        }

        $profile = WebsiteProfile::findOrFail($profileId);
        $payload = $profile->payload ?? [];
        $language = $payload['default_locale'] ?? 'de';

        $http = $this->authenticatedRequest($connection);
        $instanceUrl = $this->getInstanceUrl($connection);

        $result = [
            'products' => ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0],
            'media'    => ['success' => 0, 'failed' => 0],
            'orphans'  => [],
        ];

        // Bestehende Checksums laden
        $existingChecksums = ConnectorProductChecksum::where('connection_id', $connection->id)
            ->pluck('checksum', 'product_id')
            ->toArray();

        // Produkte aus Profil laden
        $productQuery = $this->loadProductsFromProfile($payload);
        $currentProductIds = [];

        $productQuery->chunk(50, function ($chunk) use (
            $http, $instanceUrl, $connection, $payload, $sfccFields, $language,
            &$existingChecksums, &$currentProductIds, &$result,
        ) {
            // Batch: Checksums berechnen
            $chunkChecksums = $this->checksumService->computeChecksumsBatch(
                $chunk, $payload, $sfccFields, $language,
            );

            $chunkProductIds = $chunk->pluck('id')->toArray();
            $currentProductIds = array_merge($currentProductIds, $chunkProductIds);

            foreach ($chunk as $product) {
                $newChecksum = $chunkChecksums[$product->id] ?? '';
                $oldChecksum = $existingChecksums[$product->id] ?? null;

                // Delta-Vergleich: ueberspringen wenn Checksum identisch
                if ($oldChecksum !== null && $oldChecksum === $newChecksum) {
                    $result['products']['unchanged']++;
                    continue;
                }

                $isNew = $oldChecksum === null;

                try {
                    // Kategorie-Zuordnung
                    $categoryId = null;
                    if ($product->master_hierarchy_node_id) {
                        $categoryId = $this->getCategoryIdForNode($connection->id, $product->master_hierarchy_node_id);
                    }

                    // Produkt synchronisieren
                    $productResult = $this->productService->syncProductFromProfile(
                        $http, $instanceUrl, $product, $payload, $sfccFields, $language, $categoryId,
                    );

                    $externalId = $productResult['product_id'] ?? $product->sku;

                    // Medien synchronisieren
                    $syncMedia = $sfccFields['_sync_media']['enabled'] ?? true;
                    if ($syncMedia) {
                        $mediaResult = $this->syncProductMedia($http, $instanceUrl, $connection, $product, $externalId);
                        $result['media']['success'] += $mediaResult['synced'] ?? 0;
                    }

                    // Checksum speichern/aktualisieren
                    ConnectorProductChecksum::updateOrCreate(
                        ['connection_id' => $connection->id, 'product_id' => $product->id],
                        ['checksum' => $newChecksum, 'synced_at' => now(), 'external_id' => $externalId],
                    );

                    $this->logSync(
                        $connection, 'delta_sync', 'product',
                        $product->id, 'success', $externalId, null,
                        [
                            'language'      => $language,
                            'sku'           => $product->sku,
                            'product_name'  => $product->name,
                            'delta_status'  => $isNew ? 'new' : 'updated',
                            'synced_fields' => $productResult['synced_data'] ?? [],
                        ],
                    );

                    $result['products'][$isNew ? 'new' : 'updated']++;
                } catch (\Throwable $e) {
                    $errorDetail = $e instanceof \Illuminate\Http\Client\RequestException
                        ? $this->parseSfccError($e)
                        : $e->getMessage();

                    $this->logSync(
                        $connection, 'delta_sync', 'product',
                        $product->id, 'failed', null, $errorDetail,
                        [
                            'sku'          => $product->sku,
                            'product_name' => $product->name,
                            'delta_status' => $isNew ? 'new' : 'updated',
                            'http_status'  => $e instanceof \Illuminate\Http\Client\RequestException
                                ? $e->response?->status()
                                : null,
                        ],
                    );
                    $result['products']['failed']++;

                    Log::channel('connectors')->error("SFCC Delta-Sync fehlgeschlagen: {$product->sku}", [
                        'error' => $errorDetail,
                    ]);
                }
            }
        });

        // Orphan-Erkennung: Produkte mit Checksum aber nicht mehr im Profil
        $syncedProductIds = array_keys($existingChecksums);
        $orphanIds = array_diff($syncedProductIds, $currentProductIds);

        if (!empty($orphanIds)) {
            $orphanProducts = Product::whereIn('id', $orphanIds)->get(['id', 'sku', 'name']);
            $foundIds = $orphanProducts->pluck('id')->toArray();
            $deletedIds = array_diff($orphanIds, $foundIds);

            $result['orphans'] = $orphanProducts->map(fn ($p) => [
                'id'          => $p->id,
                'sku'         => $p->sku,
                'name'        => $p->name,
                'deleted'     => false,
                'external_id' => null,
            ])->values()->toArray();

            foreach ($deletedIds as $deletedId) {
                $result['orphans'][] = [
                    'id'          => $deletedId,
                    'sku'         => '(geloescht)',
                    'name'        => '(Produkt geloescht)',
                    'deleted'     => true,
                    'external_id' => null,
                ];
            }

            // External IDs fuer Orphans aus der Checksum-Tabelle holen
            $orphanExternalIds = ConnectorProductChecksum::where('connection_id', $connection->id)
                ->whereIn('product_id', $orphanIds)
                ->pluck('external_id', 'product_id')
                ->toArray();

            foreach ($result['orphans'] as &$orphan) {
                $orphan['external_id'] = $orphanExternalIds[$orphan['id']] ?? null;
            }
            unset($orphan);
        }

        return $result;
    }

    // ─── Reset ───────────────────────────────────────────────────

    /**
     * Loescht alle vom PIM synchronisierten Produkte und Kategorien aus SFCC.
     *
     * @return array{products: array{deleted: int, failed: int, total: int}, categories: array{deleted: int, failed: int, total: int}}
     */
    public function resetShop(ConnectorConnection $connection): array
    {
        $http = $this->authenticatedRequest($connection);
        $instanceUrl = $this->getInstanceUrl($connection);
        $catalogId = $this->getCatalogId($connection);

        $result = [
            'products'   => ['deleted' => 0, 'failed' => 0, 'total' => 0],
            'categories' => ['deleted' => 0, 'failed' => 0, 'total' => 0],
        ];

        // 1. Produkte loeschen — aus Checksums (zuverlaessigste Quelle)
        $productExternalIds = ConnectorProductChecksum::where('connection_id', $connection->id)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->unique()
            ->values()
            ->toArray();

        // Zusaetzlich aus Sync-Logs
        $logProductIds = ConnectorSyncLog::where('connector_connection_id', $connection->id)
            ->whereIn('action', ['product_push', 'profile_sync', 'delta_sync'])
            ->where('entity_type', 'product')
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->unique()
            ->values()
            ->toArray();

        $allProductIds = array_values(array_unique(array_merge($productExternalIds, $logProductIds)));
        $result['products']['total'] = count($allProductIds);

        foreach ($allProductIds as $productId) {
            try {
                $this->productService->deleteProduct($http, $instanceUrl, $productId);
                $result['products']['deleted']++;
            } catch (\Throwable) {
                $result['products']['failed']++;
            }
        }

        // 2. Kategorien loeschen (Blaetter zuerst)
        $categoryLogs = ConnectorSyncLog::where('connector_connection_id', $connection->id)
            ->where('action', 'category_sync')
            ->where('entity_type', 'hierarchy_node')
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->select('entity_id', 'external_id')
            ->get()
            ->unique('external_id');

        $nodeDepths = HierarchyNode::whereIn('id', $categoryLogs->pluck('entity_id'))
            ->pluck('depth', 'id')
            ->toArray();

        $categoryExternalIds = $categoryLogs
            ->sortByDesc(fn ($log) => $nodeDepths[$log->entity_id] ?? 0)
            ->pluck('external_id')
            ->values()
            ->toArray();

        $result['categories']['total'] = count($categoryExternalIds);

        foreach ($categoryExternalIds as $sfccCategoryId) {
            try {
                $this->categoryService->deleteCategory($http, $instanceUrl, $catalogId, $sfccCategoryId);
                $result['categories']['deleted']++;
            } catch (\Throwable) {
                $result['categories']['failed']++;
            }
        }

        // 3. Lokale Checksums und Sync-Logs bereinigen
        ConnectorProductChecksum::where('connection_id', $connection->id)->delete();
        $connection->syncLogs()->delete();

        $this->logSync(
            $connection, 'reset', 'connection',
            $connection->id, 'success', null, null,
            [
                'products_deleted'   => $result['products']['deleted'],
                'products_failed'    => $result['products']['failed'],
                'categories_deleted' => $result['categories']['deleted'],
                'categories_failed'  => $result['categories']['failed'],
            ],
        );

        return $result;
    }

    /**
     * Loescht alle Kategorien aus dem SFCC-Katalog.
     */
    public function purgeCategories(ConnectorConnection $connection): array
    {
        $http = $this->authenticatedRequest($connection);
        $instanceUrl = $this->getInstanceUrl($connection);
        $catalogId = $this->getCatalogId($connection);

        $categoryLogs = ConnectorSyncLog::where('connector_connection_id', $connection->id)
            ->where('action', 'category_sync')
            ->where('entity_type', 'hierarchy_node')
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->select('entity_id', 'external_id')
            ->get()
            ->unique('external_id');

        $nodeDepths = HierarchyNode::whereIn('id', $categoryLogs->pluck('entity_id'))
            ->pluck('depth', 'id')
            ->toArray();

        $categoryExternalIds = $categoryLogs
            ->sortByDesc(fn ($log) => $nodeDepths[$log->entity_id] ?? 0)
            ->pluck('external_id')
            ->values()
            ->toArray();

        $result = ['deleted' => 0, 'failed' => 0, 'total' => count($categoryExternalIds)];

        foreach ($categoryExternalIds as $sfccCategoryId) {
            try {
                $this->categoryService->deleteCategory($http, $instanceUrl, $catalogId, $sfccCategoryId);
                $result['deleted']++;
            } catch (\Throwable) {
                $result['failed']++;
            }
        }

        // Kategorie-Sync-Logs bereinigen
        ConnectorSyncLog::where('connector_connection_id', $connection->id)
            ->where('action', 'category_sync')
            ->delete();

        $this->logSync(
            $connection, 'purge_categories', 'connection',
            $connection->id, 'success', null, null,
            $result,
        );

        return $result;
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────

    /**
     * Parst SFCC API-Fehler in eine lesbare Meldung.
     */
    private function parseSfccError(\Illuminate\Http\Client\RequestException $e): string
    {
        $body = $e->response?->json();

        if (isset($body['fault'])) {
            $type = $body['fault']['type'] ?? '';
            $message = $body['fault']['message'] ?? '';
            return "SFCC: {$type} – {$message}";
        }

        if (isset($body['error_description'])) {
            return 'SFCC: ' . $body['error_description'];
        }

        return 'SFCC HTTP ' . ($e->response?->status() ?? '?') . ': ' . $e->getMessage();
    }

    /**
     * Ermittelt die SFCC-Kategorie-ID fuer einen PIM-Hierarchie-Knoten.
     */
    public function getCategoryIdForNode(string $connectionId, string $nodeId): ?string
    {
        return ConnectorSyncLog::where('connector_connection_id', $connectionId)
            ->where('action', 'category_sync')
            ->where('entity_type', 'hierarchy_node')
            ->where('entity_id', $nodeId)
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->latest()
            ->value('external_id');
    }

    /**
     * Laedt Produkte basierend auf den Profil-Einstellungen (Hierarchie + Filter).
     */
    private function loadProductsFromProfile(array $payload): \Illuminate\Database\Eloquent\Builder
    {
        $hierarchyId = $payload['hierarchy_id'] ?? null;
        $linkedOnly = !empty($payload['catalog_linked_products_only']);
        $excludedNodeIds = $payload['catalog_excluded_node_ids'] ?? [];

        $query = Product::with(['media', 'masterHierarchyNode']);

        if ($hierarchyId && $linkedOnly) {
            $hierarchy = Hierarchy::find($hierarchyId);
            if ($hierarchy) {
                $nodeQuery = HierarchyNode::where('hierarchy_id', $hierarchy->id)
                    ->where('is_active', true);

                if (!empty($excludedNodeIds)) {
                    $nodeQuery->whereNotIn('id', $excludedNodeIds);
                }

                $nodeIds = $nodeQuery->pluck('id');

                if ($hierarchy->hierarchy_type === 'output') {
                    $productIds = OutputHierarchyProductAssignment::whereIn('hierarchy_node_id', $nodeIds)
                        ->pluck('product_id');
                    $query->whereIn('id', $productIds);
                } else {
                    $query->whereIn('master_hierarchy_node_id', $nodeIds);
                }
            }
        }

        return $query;
    }

    /**
     * Synchronisiert Produktbilder als SFCC image_groups (URL-Referenz).
     */
    private function syncProductMedia(
        PendingRequest $http,
        string $instanceUrl,
        ConnectorConnection $connection,
        Product $product,
        string $productId,
    ): array {
        $sfccFields = $connection->settings['sfcc_fields'] ?? [];

        try {
            $result = $this->mediaService->syncProductImages(
                $http, $instanceUrl, $product, $productId, $sfccFields,
            );

            if ($result['synced'] > 0) {
                $this->logSync($connection, 'media_sync', 'product', $product->id, 'success', $productId, null, [
                    'images_count' => $result['synced'],
                    'view_types'   => $result['view_types'],
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logSync($connection, 'media_sync', 'product', $product->id, 'failed', $productId, $e->getMessage());

            Log::channel('connectors')->warning("SFCC Medien-Sync fehlgeschlagen: {$product->sku}", [
                'error' => $e->getMessage(),
            ]);

            return ['synced' => 0, 'view_types' => []];
        }
    }

    /**
     * Gibt die Instance-URL fuer die Verbindung zurueck.
     */
    private function getInstanceUrl(ConnectorConnection $connection): string
    {
        return rtrim(
            $connection->settings['instance_url'] ?? config('connectors.salesforce_commerce.instance_url', ''),
            '/',
        );
    }

    /**
     * Gibt die Katalog-ID fuer die Verbindung zurueck.
     */
    private function getCatalogId(ConnectorConnection $connection): string
    {
        return $connection->settings['catalog_id']
            ?? config('connectors.salesforce_commerce.catalog_id', 'storefront-catalog');
    }
}
