<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopify;

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

class ShopifyConnector extends AbstractConnector
{
    private const API_VERSION = '2025-10';

    public function __construct(
        private readonly ShopifyAuthService $authService,
        private readonly ShopifyProductService $productService,
        private readonly ShopifyMediaService $mediaService,
        private readonly ShopifyCategoryService $categoryService,
        private readonly ShopifyMetafieldService $metafieldService,
        private readonly ShopifyChecksumService $checksumService,
    ) {}

    /**
     * Gibt einen authentifizierten HTTP-Client zurück (für Controller-Zugriff).
     */
    public function getAuthenticatedRequest(ConnectorConnection $connection): PendingRequest
    {
        return $this->authenticatedRequest($connection);
    }

    public function getType(): string
    {
        return 'shopify';
    }

    public function getName(): string
    {
        return 'Shopify';
    }

    public function getDescription(): string
    {
        return 'Shopify Admin API – Produkte und Medien in den Shop synchronisieren';
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
        // Shopify Custom App: kein OAuth-Redirect nötig
        return [
            'url'           => '',
            'state'         => '',
            'code_verifier' => null,
        ];
    }

    public function handleCallback(string $code, ?string $codeVerifier = null, string $shopUrl = ''): array
    {
        // code = access_token (Legacy) oder client_id (Client Credentials)
        // codeVerifier = client_secret (bei Client Credentials, leer bei Legacy)
        return $this->authService->authenticate($code, $codeVerifier ?? '', $shopUrl);
    }

    public function refreshToken(ConnectorConnection $connection): void
    {
        // Shopify Custom App Tokens laufen nicht ab — No-op
        $this->authService->refreshAccessToken($connection);
    }

    /**
     * HTTP-Request mit Shopify Access Token Header.
     *
     * Override von AbstractConnector: Shopify nutzt X-Shopify-Access-Token statt Bearer Token.
     * Unterstützt sowohl statische Tokens (Legacy) als auch kurzlebige Tokens (ab 2026).
     */
    protected function authenticatedRequest(ConnectorConnection $connection): PendingRequest
    {
        // Bei kurzlebigen Tokens: automatisch erneuern wenn abgelaufen
        $hasClientCredentials = !empty($connection->settings['client_secret'] ?? '');
        if ($hasClientCredentials && $connection->isTokenExpired()) {
            $this->refreshToken($connection);
            $connection->refresh();
        }

        // Token aus Connection (kurzlebig) oder Settings (statisch)
        $token = $connection->access_token
            ?: ($connection->settings['access_token'] ?? config('connectors.shopify.access_token'));

        return Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
        ])->acceptJson()->timeout(30);
    }

    public function uploadAsset(ConnectorConnection $connection, Media $media): string
    {
        // Shopify-Bilder gehören zum Produkt — standalone Upload nicht unterstützt
        // Wird über syncProductMedia() orchestriert
        throw new \RuntimeException('Shopify unterstützt keinen standalone Media-Upload. Medien werden über den Produkt-Sync hochgeladen.');
    }

    public function pushProductData(ConnectorConnection $connection, Product $product, array $options = []): array
    {
        $http = $this->authenticatedRequest($connection);
        $shopUrl = $connection->settings['shop_url'] ?? config('connectors.shopify.shop_url');
        $language = $options['language'] ?? 'de';
        $settings = $connection->settings ?? [];
        $shopifyFields = $settings['shopify_fields'] ?? [];
        $profileId = $settings['website_profile_id'] ?? null;

        $useProfileSync = !empty($shopifyFields) || $profileId;

        try {
            if ($useProfileSync) {
                $profilePayload = [];
                if ($profileId) {
                    $profile = WebsiteProfile::find($profileId);
                    $profilePayload = $profile?->payload ?? [];
                }

                // Metafields synchronisieren (Selection-Attribute → Shopify Metafields)
                $metafields = [];
                $metafieldAttrIds = $shopifyFields['_metafield_attribute_ids'] ?? [];
                if (empty($metafieldAttrIds)) {
                    $metafieldAttrIds = $this->productService->resolveMetafieldAttributeIds($product, $profilePayload);
                }
                if (!empty($metafieldAttrIds)) {
                    $definitionMap = $this->metafieldService->syncMetafieldDefinitions(
                        $http, $shopUrl, $connection->id, $metafieldAttrIds, $language,
                    );
                    $metafields = $this->metafieldService->resolveProductMetafields($product, $definitionMap, $language);
                }

                // Kategorie-Zuordnung (aus vorherigem Hierarchie-Sync)
                $collectionId = null;
                if ($product->master_hierarchy_node_id) {
                    $collectionId = $this->getCategoryIdForNode($connection->id, $product->master_hierarchy_node_id);
                }

                $result = $this->productService->syncProductFromProfile(
                    $http, $shopUrl, $product, $connection->id,
                    $profilePayload, $shopifyFields, $language, $metafields, $collectionId,
                );

                $externalProductId = $result['product_id'] ?? null;

                // Medien synchronisieren (wenn aktiviert)
                $syncMedia = $shopifyFields['_sync_media']['enabled'] ?? true;
                if ($syncMedia && $externalProductId) {
                    $this->syncProductMedia($http, $shopUrl, $connection, $product, $externalProductId);
                }
            } else {
                $result = $this->productService->syncProduct(
                    $http, $shopUrl, $product, $connection->id, $language, $options,
                );
            }

            $externalId = $result['product_id'] ?? null;

            $this->logSync(
                $connection,
                'product_push',
                'product',
                $product->id,
                'success',
                $externalId,
                null,
                [
                    'language'      => $language,
                    'sku'           => $product->sku,
                    'product_name'  => $product->name,
                    'synced_fields' => $result['synced_data'] ?? array_keys($result),
                ],
            );

            return $result;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $errorDetail = $this->parseShopifyError($e);

            $this->logSync(
                $connection,
                'product_push',
                'product',
                $product->id,
                'failed',
                null,
                $errorDetail,
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

    /**
     * Synchronisiert den Hierarchie-Baum als Shopify Custom Collections.
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
        $shopUrl = $settings['shop_url'] ?? config('connectors.shopify.shop_url');

        $catResult = $this->categoryService->syncHierarchy(
            $http, $shopUrl, $connection->id, $hierarchyId, $language, $excludedNodeIds,
        );

        // Sync-Logs für erfolgreich synchronisierte Collections
        foreach ($catResult['category_map'] as $nodeId => $shopifyCollectionId) {
            $this->logSync(
                $connection, 'category_sync', 'hierarchy_node',
                $nodeId, 'success', $shopifyCollectionId,
            );
        }

        foreach ($catResult['error_details'] ?? [] as $errorDetail) {
            $this->logSync(
                $connection, 'category_sync', 'hierarchy',
                $hierarchyId, 'failed', null, $errorDetail,
            );
        }

        // Navigation Menu erstellen (hierarchischer Baum via GraphQL)
        $menuResult = ['success' => false, 'menu_id' => null, 'error' => null];
        if (!empty($catResult['category_map'])) {
            $menuResult = $this->categoryService->syncNavigationMenu(
                $http, $shopUrl, $hierarchyId, $catResult['category_map'], $language,
            );

            if ($menuResult['success']) {
                $this->logSync(
                    $connection, 'menu_sync', 'hierarchy',
                    $hierarchyId, 'success', $menuResult['menu_id'],
                );
            } elseif ($menuResult['error']) {
                $catResult['error_details'][] = 'Navigation Menu: ' . $menuResult['error'];
                $catResult['errors']++;
            }
        }

        return [
            'synced'         => $catResult['synced'],
            'errors'         => $catResult['errors'],
            'error_details'  => $catResult['error_details'] ?? [],
            'total_nodes'    => $catResult['total_nodes'],
            'hierarchy_name' => $catResult['hierarchy_name'],
            'menu'           => $menuResult,
        ];
    }

    /**
     * Ermittelt die Shopify-Collection-ID für einen PIM-Hierarchie-Knoten.
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
     * Produkt-Sync basierend auf einem WebsiteProfile.
     *
     * @return array{products: array{success: int, failed: int}, media: array{success: int, failed: int}, metafields: array}
     */
    public function syncFromProfile(ConnectorConnection $connection): array
    {
        $settings = $connection->settings ?? [];
        $profileId = $settings['website_profile_id'] ?? null;
        $shopifyFields = $settings['shopify_fields'] ?? [];

        if (!$profileId) {
            throw new \RuntimeException('Kein Vorschau-Profil konfiguriert. Bitte ein Profil in den Verbindungseinstellungen auswählen.');
        }

        $profile = WebsiteProfile::findOrFail($profileId);
        $payload = $profile->payload ?? [];
        $language = $payload['default_locale'] ?? 'de';

        $http = $this->authenticatedRequest($connection);
        $shopUrl = $settings['shop_url'] ?? config('connectors.shopify.shop_url');

        // Metafield Definitions einmal vorab synchronisieren
        $definitionMap = [];
        $metafieldAttrIds = $shopifyFields['_metafield_attribute_ids'] ?? [];
        if (empty($metafieldAttrIds)) {
            $metafieldAttrIds = $this->productService->resolveMetafieldAttributeIds(new Product(), $payload);
        }
        if (!empty($metafieldAttrIds)) {
            $definitionMap = $this->metafieldService->syncMetafieldDefinitions(
                $http, $shopUrl, $connection->id, $metafieldAttrIds, $language,
            );
        }

        $result = [
            'products'   => ['success' => 0, 'failed' => 0],
            'media'      => ['success' => 0, 'failed' => 0],
            'metafields' => ['definitions' => count($definitionMap)],
        ];

        // Produkte aus Hierarchie laden (mit Medien)
        $products = $this->loadProductsFromProfile($payload);

        // Produkte synchronisieren (in Chunks)
        $products->chunk(50, function ($chunk) use (
            $http, $shopUrl, $connection, $payload, $shopifyFields,
            $language, $definitionMap, &$result,
        ) {
            foreach ($chunk as $product) {
                try {
                    // Metafields pro Produkt auflösen
                    $metafields = $this->metafieldService->resolveProductMetafields($product, $definitionMap, $language);

                    // Collection-Zuordnung
                    $collectionId = null;
                    if ($product->master_hierarchy_node_id) {
                        $collectionId = $this->getCategoryIdForNode($connection->id, $product->master_hierarchy_node_id);
                    }

                    $productResult = $this->productService->syncProductFromProfile(
                        $http, $shopUrl, $product, $connection->id,
                        $payload, $shopifyFields, $language, $metafields, $collectionId,
                    );

                    $externalId = $productResult['product_id'] ?? null;

                    // Medien synchronisieren
                    $syncMedia = $shopifyFields['_sync_media']['enabled'] ?? true;
                    if ($syncMedia && $externalId) {
                        $mediaResult = $this->syncProductMedia($http, $shopUrl, $connection, $product, $externalId);
                        $result['media']['success'] += $mediaResult['success'];
                        $result['media']['failed'] += $mediaResult['failed'];
                    }

                    $this->logSync(
                        $connection, 'profile_sync', 'product',
                        $product->id, 'success', $externalId, null,
                        [
                            'language'         => $language,
                            'sku'              => $product->sku,
                            'product_name'     => $product->name,
                            'synced_fields'    => $productResult['synced_data'] ?? [],
                            'metafields_count' => count($metafields),
                        ],
                    );

                    $result['products']['success']++;
                } catch (\Throwable $e) {
                    $errorDetail = $e instanceof \Illuminate\Http\Client\RequestException
                        ? $this->parseShopifyError($e)
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

                    Log::channel('stack')->error("Shopify Profil-Sync fehlgeschlagen: {$product->sku}", [
                        'error' => $errorDetail,
                    ]);
                }
            }
        });

        return $result;
    }

    /**
     * Delta-Sync: Nur neue und geänderte Produkte synchronisieren.
     *
     * @return array{products: array{new: int, updated: int, unchanged: int, failed: int}, media: array{success: int, failed: int}, orphans: array, metafields: array}
     */
    public function deltaSyncFromProfile(ConnectorConnection $connection): array
    {
        $settings = $connection->settings ?? [];
        $profileId = $settings['website_profile_id'] ?? null;
        $shopifyFields = $settings['shopify_fields'] ?? [];

        if (!$profileId) {
            throw new \RuntimeException('Kein Vorschau-Profil konfiguriert. Bitte ein Profil in den Verbindungseinstellungen auswählen.');
        }

        $profile = WebsiteProfile::findOrFail($profileId);
        $payload = $profile->payload ?? [];
        $language = $payload['default_locale'] ?? 'de';

        $http = $this->authenticatedRequest($connection);
        $shopUrl = $settings['shop_url'] ?? config('connectors.shopify.shop_url');

        // Metafield Definitions vorab synchronisieren
        $definitionMap = [];
        $metafieldAttrIds = $shopifyFields['_metafield_attribute_ids'] ?? [];
        if (empty($metafieldAttrIds)) {
            $metafieldAttrIds = $this->productService->resolveMetafieldAttributeIds(new Product(), $payload);
        }
        if (!empty($metafieldAttrIds)) {
            $definitionMap = $this->metafieldService->syncMetafieldDefinitions(
                $http, $shopUrl, $connection->id, $metafieldAttrIds, $language,
            );
        }

        // Erlaubte Attribut-IDs aus Profil auflösen
        $allowedAttributeIds = $this->productService->resolveAllowedAttributeIds(new Product(), $payload);

        $result = [
            'products'   => ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0],
            'media'      => ['success' => 0, 'failed' => 0],
            'orphans'    => [],
            'metafields' => ['definitions' => count($definitionMap)],
        ];

        // Bestehende Checksums laden
        $existingChecksums = ConnectorProductChecksum::where('connection_id', $connection->id)
            ->pluck('checksum', 'product_id')
            ->toArray();

        // Produkte aus Profil laden
        $productQuery = $this->loadProductsFromProfile($payload);
        $currentProductIds = [];

        // Produkte synchronisieren (in Chunks)
        $productQuery->chunk(50, function ($chunk) use (
            $http, $shopUrl, $connection, $payload, $shopifyFields,
            $language, $definitionMap, $allowedAttributeIds, $metafieldAttrIds,
            &$existingChecksums, &$currentProductIds, &$result,
        ) {
            // Batch: Checksums für den gesamten Chunk berechnen
            $chunkProductIds = $chunk->pluck('id')->toArray();
            $currentProductIds = array_merge($currentProductIds, $chunkProductIds);

            try {
                $chunkChecksums = $this->checksumService->computeChecksumsBatch(
                    $chunk, $payload, $shopifyFields, $language, $allowedAttributeIds, $metafieldAttrIds,
                );
            } catch (\Throwable $e) {
                Log::channel('stack')->error('Checksum-Berechnung fehlgeschlagen', [
                    'error'      => $e->getMessage(),
                    'chunk_size' => $chunk->count(),
                ]);
                $result['products']['failed'] += $chunk->count();
                return; // Nächster Chunk
            }

            // Batch: Metafields für den gesamten Chunk auflösen
            $bulkMetafields = $this->metafieldService->resolveProductMetafieldsBulk($chunkProductIds, $definitionMap, $language);

            foreach ($chunk as $product) {
                $newChecksum = $chunkChecksums[$product->id] ?? '';
                $oldChecksum = $existingChecksums[$product->id] ?? null;

                // Delta-Vergleich: überspringen wenn Checksum identisch
                if ($oldChecksum !== null && $oldChecksum === $newChecksum) {
                    $result['products']['unchanged']++;
                    continue;
                }

                $isNew = $oldChecksum === null;

                try {
                    $metafields = $bulkMetafields[$product->id] ?? [];

                    // Collection-Zuordnung
                    $collectionId = null;
                    if ($product->master_hierarchy_node_id) {
                        $collectionId = $this->getCategoryIdForNode($connection->id, $product->master_hierarchy_node_id);
                    }

                    // Produkt synchronisieren
                    $productResult = $this->productService->syncProductFromProfile(
                        $http, $shopUrl, $product, $connection->id,
                        $payload, $shopifyFields, $language, $metafields, $collectionId,
                    );

                    $externalId = $productResult['product_id'] ?? null;

                    // Medien synchronisieren
                    $syncMedia = $shopifyFields['_sync_media']['enabled'] ?? true;
                    if ($syncMedia && $externalId) {
                        $mediaResult = $this->syncProductMedia($http, $shopUrl, $connection, $product, $externalId);
                        $result['media']['success'] += $mediaResult['success'];
                        $result['media']['failed'] += $mediaResult['failed'];
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
                        ? $this->parseShopifyError($e)
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

                    Log::channel('stack')->error("Shopify Delta-Sync fehlgeschlagen: {$product->sku}", [
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
                    'sku'         => '(gelöscht)',
                    'name'        => '(Produkt gelöscht)',
                    'deleted'     => true,
                    'external_id' => null,
                ];
            }

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

    /**
     * Löscht alle vom PIM synchronisierten Produkte und Collections aus Shopify.
     *
     * @return array{products: array{deleted: int, failed: int}, categories: array{deleted: int, failed: int}}
     */
    public function resetShop(ConnectorConnection $connection): array
    {
        $http = $this->authenticatedRequest($connection);
        $shopUrl = rtrim($connection->settings['shop_url'] ?? config('connectors.shopify.shop_url'), '/');

        $result = [
            'products'   => ['deleted' => 0, 'failed' => 0, 'total' => 0],
            'categories' => ['deleted' => 0, 'failed' => 0, 'total' => 0],
        ];

        // 1. Collections ZUERST löschen (Produkte können nicht gelöscht werden solange sie Collections zugeordnet sind)
        $categoryLogs = ConnectorSyncLog::where('connector_connection_id', $connection->id)
            ->where('action', 'category_sync')
            ->where('entity_type', 'hierarchy_node')
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->unique()
            ->values()
            ->toArray();

        $result['categories']['total'] = count($categoryLogs);

        foreach ($categoryLogs as $collectionId) {
            try {
                $http->delete(
                    "{$shopUrl}/admin/api/" . self::API_VERSION . "/custom_collections/{$collectionId}.json",
                )->throw();
                $result['categories']['deleted']++;
            } catch (\Throwable) {
                $result['categories']['failed']++;
            }
        }

        // 2. Produkte löschen — aus Checksums (zuverlässigste Quelle)
        $productExternalIds = ConnectorProductChecksum::where('connection_id', $connection->id)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->unique()
            ->values()
            ->toArray();

        // Zusätzlich aus Sync-Logs
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
                $http->delete(
                    "{$shopUrl}/admin/api/" . self::API_VERSION . "/products/{$productId}.json",
                )->throw();
                $result['products']['deleted']++;
            } catch (\Throwable) {
                $result['products']['failed']++;
            }
        }

        // 3. Lokale Checksums bereinigen (Sync-Logs erst nach erfolgreichem Löschen)
        ConnectorProductChecksum::where('connection_id', $connection->id)->delete();

        // Sync-Logs nur bereinigen wenn keine Fehler aufgetreten sind
        // Bei Fehlern bleiben die Logs erhalten für einen erneuten Reset-Versuch
        if ($result['products']['failed'] === 0 && $result['categories']['failed'] === 0) {
            $connection->syncLogs()->delete();
        }

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
     * Löscht alle Collections aus Shopify.
     */
    public function purgeCategories(ConnectorConnection $connection): array
    {
        $http = $this->authenticatedRequest($connection);
        $shopUrl = rtrim($connection->settings['shop_url'] ?? config('connectors.shopify.shop_url'), '/');

        $result = ['deleted' => 0, 'failed' => 0, 'total' => 0];

        // Alle Custom Collections laden
        $allCollections = [];
        $sinceId = 0;
        $collections = [];

        do {
            $query = ['limit' => 250];
            if ($sinceId > 0) {
                $query['since_id'] = $sinceId;
            }

            try {
                $response = $http->get(
                    "{$shopUrl}/admin/api/" . self::API_VERSION . '/custom_collections.json',
                    $query,
                );
                $response->throw();
                $collections = $response->json()['custom_collections'] ?? [];

                foreach ($collections as $col) {
                    $allCollections[] = $col['id'];
                    $sinceId = max($sinceId, $col['id']);
                }
            } catch (\Throwable) {
                break;
            }
        } while (count($collections) >= 250);

        $result['total'] = count($allCollections);

        foreach ($allCollections as $collectionId) {
            try {
                $http->delete(
                    "{$shopUrl}/admin/api/" . self::API_VERSION . "/custom_collections/{$collectionId}.json",
                )->throw();
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
            ['deleted' => $result['deleted'], 'failed' => $result['failed']],
        );

        return $result;
    }

    /**
     * Lädt Produkte basierend auf den Profil-Einstellungen (Hierarchie + Filter).
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
     * Synchronisiert Produktbilder nach Shopify.
     *
     * Löscht bestehende Bilder und lädt alle neu hoch (Shopify hat keine
     * deterministischen Bild-IDs wie Shopware).
     *
     * @return array{success: int, failed: int}
     */
    private function syncProductMedia(
        PendingRequest $http,
        string $shopUrl,
        ConnectorConnection $connection,
        Product $product,
        string $externalProductId,
    ): array {
        $result = ['success' => 0, 'failed' => 0];

        $product->loadMissing('media');

        // Nur Bilder synchronisieren
        $images = $product->media->filter(fn ($m) => $m->media_type === 'image');
        if ($images->isEmpty()) {
            return $result;
        }

        // Bestehende Bilder löschen (Shopify hat keine deterministischen IDs)
        $this->mediaService->deleteProductImages($http, $shopUrl, $externalProductId);

        // Hauptbild (is_primary) zuerst, dann nach sort_order
        $sorted = $images->sortBy(function ($media) {
            $isPrimary = $media->pivot->is_primary ?? false;
            $sortOrder = $media->pivot->sort_order ?? 999;
            return ($isPrimary ? 0 : 1) . '-' . str_pad((string) $sortOrder, 5, '0', STR_PAD_LEFT);
        });

        $position = 1;
        foreach ($sorted as $media) {
            try {
                $imageId = $this->mediaService->createProductImage(
                    $http, $shopUrl, $externalProductId, $media, $position,
                );

                $this->logSync($connection, 'media_sync', 'media', $media->id, 'success', $imageId);
                $result['success']++;
                $position++;
            } catch (\Throwable $e) {
                $this->logSync($connection, 'media_sync', 'media', $media->id, 'failed', null, $e->getMessage());
                $result['failed']++;
            }
        }

        return $result;
    }
}
