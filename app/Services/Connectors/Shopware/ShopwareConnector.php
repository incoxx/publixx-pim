<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

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
use Illuminate\Support\Facades\Log;

class ShopwareConnector extends AbstractConnector
{
    public function __construct(
        private readonly ShopwareAuthService $authService,
        private readonly ShopwareProductService $productService,
        private readonly ShopwareMediaService $mediaService,
        private readonly ShopwareCategoryService $categoryService,
        private readonly ShopwarePropertyService $propertyService,
        private readonly ShopwareChecksumService $checksumService,
    ) {}

    public function getType(): string
    {
        return 'shopware';
    }

    public function getName(): string
    {
        return 'Shopware 6';
    }

    public function getDescription(): string
    {
        return 'Shopware 6 API – Produkte und Medien in den Shop synchronisieren';
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
        // Shopware uses Integration (client_credentials) auth, no browser redirect needed
        return [
            'url'           => '',
            'state'         => '',
            'code_verifier' => null,
        ];
    }

    public function handleCallback(string $code, ?string $codeVerifier = null, string $shopUrl = ''): array
    {
        return $this->authService->authenticate($code, $codeVerifier ?? '', $shopUrl);
    }

    public function refreshToken(ConnectorConnection $connection): void
    {
        $this->authService->refreshAccessToken($connection);
    }

    public function uploadAsset(ConnectorConnection $connection, Media $media): string
    {
        $http = $this->authenticatedRequest($connection);
        $shopUrl = $connection->settings['shop_url'] ?? config('connectors.shopware.shop_url');

        return $this->mediaService->uploadMedia($http, $shopUrl, $media);
    }

    public function pushProductData(ConnectorConnection $connection, Product $product, array $options = []): array
    {
        $http = $this->authenticatedRequest($connection);
        $shopUrl = $connection->settings['shop_url'] ?? config('connectors.shopware.shop_url');
        $language = $options['language'] ?? 'de';
        $settings = $connection->settings ?? [];
        $shopwareFields = $settings['shopware_fields'] ?? [];
        $profileId = $settings['website_profile_id'] ?? null;

        // Wenn Export-Profil konfiguriert ist → profil-basierten Pfad nutzen
        $useProfileSync = !empty($shopwareFields) || $profileId;

        try {
            if ($useProfileSync) {
                // Tax-ID für default-Modus vorab holen
                $taxIdMapping = $shopwareFields['tax_id'] ?? [];
                if (empty($taxIdMapping) || ($taxIdMapping['mode'] ?? '') === 'default') {
                    $defaultTaxId = $this->productService->fetchDefaultTaxId($http, $shopUrl);
                    if ($defaultTaxId) {
                        $shopwareFields['tax_id'] = ['mode' => 'fixed', 'value' => $defaultTaxId];
                    }
                }

                // Sales Channel ID automatisch holen wenn nicht konfiguriert
                $salesChannelMapping = $shopwareFields['_sales_channel_id'] ?? [];
                if (empty($salesChannelMapping) || empty($salesChannelMapping['value'])) {
                    $defaultSalesChannelId = $this->productService->fetchDefaultSalesChannelId($http, $shopUrl);
                    if ($defaultSalesChannelId) {
                        $shopwareFields['_sales_channel_id'] = ['value' => $defaultSalesChannelId];
                    }
                }

                $profilePayload = [];
                if ($profileId) {
                    $profile = \App\Models\WebsiteProfile::find($profileId);
                    $profilePayload = $profile?->payload ?? [];
                }

                // Properties synchronisieren (Selection-Attribute → Shopware Property Groups)
                // Manuell konfiguriert oder automatisch aus Profil-Attributsichten
                $properties = [];
                $propertyAttrIds = $shopwareFields['_property_attribute_ids'] ?? [];
                if (empty($propertyAttrIds)) {
                    // Automatisch: alle Selection-Attribute aus den Profil-Attributsichten
                    $propertyAttrIds = $this->productService->resolvePropertyAttributeIds($product, $profilePayload);
                }
                if (!empty($propertyAttrIds)) {
                    $propertyMap = $this->propertyService->syncPropertyGroups(
                        $http, $shopUrl, $connection->id, $propertyAttrIds, $language,
                    );
                    // Sync-Logs für Property Groups + Options schreiben
                    foreach ($propertyMap as $attrId => $mapping) {
                        $this->logSync($connection, 'property_group_sync', 'attribute', $attrId, 'success', $mapping['group_id']);
                        foreach ($mapping['options'] as $entryId => $optionId) {
                            $this->logSync($connection, 'property_option_sync', 'value_list_entry', $entryId, 'success', $optionId);
                        }
                    }
                    $properties = $this->propertyService->resolveProductProperties($product, $propertyMap, $language);
                }

                // Kategorie-Zuordnung (aus vorherigem Hierarchie-Sync)
                $categoryId = null;
                if ($product->master_hierarchy_node_id) {
                    $categoryId = $this->getCategoryIdForNode($connection->id, $product->master_hierarchy_node_id);
                }

                $result = $this->productService->syncProductFromProfile(
                    $http, $shopUrl, $product, $profilePayload, $shopwareFields, $language, $properties, $categoryId,
                );

                $externalProductId = $result['product_id'] ?? $product->sku;

                // Medien synchronisieren (wenn aktiviert)
                $syncMedia = $shopwareFields['_sync_media']['enabled'] ?? true;
                if ($syncMedia) {
                    $product->loadMissing('media');
                    $position = 0;
                    foreach ($product->media as $media) {
                        try {
                            $mediaId = $this->mediaService->uploadMedia($http, $shopUrl, $media);
                            $this->mediaService->assignMediaToProduct($http, $shopUrl, $externalProductId, $mediaId, $position);
                            $this->logSync($connection, 'media_sync', 'media', $media->id, 'success', $mediaId);
                            $position++;
                        } catch (\Throwable $e) {
                            $this->logSync($connection, 'media_sync', 'media', $media->id, 'failed', null, $e->getMessage());
                        }
                    }
                }
            } else {
                // Legacy-Pfad ohne Profil
                if (empty($options['tax_id'])) {
                    $options['_default_tax_id'] = $this->productService->fetchDefaultTaxId($http, $shopUrl);
                }
                $result = $this->productService->syncProduct($http, $shopUrl, $product, $language, $options);
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
            $errorDetail = $this->parseShopwareError($e);

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
                $code = $error['code'] ?? '';

                if ($source && $detail) {
                    $messages[] = "{$source}: {$detail}";
                } elseif ($detail) {
                    $messages[] = $detail;
                } elseif ($code) {
                    $messages[] = $code;
                }
            }

            if (!empty($messages)) {
                return 'Shopware: ' . implode(' | ', $messages);
            }
        }

        return 'Shopware HTTP ' . ($e->response?->status() ?? '?') . ': ' . $e->getMessage();
    }

    /**
     * Synchronisiert den Hierarchie-Baum aus dem Vorschau-Profil als Shopware-Kategorien.
     *
     * @return array{synced: int, errors: int, total_nodes: int, hierarchy_name: string}
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
        $shopUrl = $settings['shop_url'] ?? config('connectors.shopware.shop_url');

        $catResult = $this->categoryService->syncHierarchy(
            $http, $shopUrl, $connection->id, $hierarchyId, $language, $excludedNodeIds,
        );

        // Sync-Logs nur für erfolgreich synchronisierte Kategorien
        foreach ($catResult['category_map'] as $nodeId => $shopwareCategoryId) {
            $this->logSync(
                $connection, 'category_sync', 'hierarchy_node',
                $nodeId, 'success', $shopwareCategoryId,
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

    /**
     * Ermittelt die Shopware-Kategorie-ID für einen PIM-Hierarchie-Knoten.
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
     * Synchronisiert Produkte und Medien anhand der Profil-Einstellungen.
     * Hierarchie-Sync wird separat über syncHierarchy() ausgeführt.
     *
     * @return array{products: array{success: int, failed: int}, media: array{success: int, failed: int}}
     */
    public function syncFromProfile(ConnectorConnection $connection): array
    {
        $settings = $connection->settings ?? [];
        $profileId = $settings['website_profile_id'] ?? null;
        $shopwareFields = $settings['shopware_fields'] ?? [];

        if (!$profileId) {
            throw new \RuntimeException('Kein Vorschau-Profil konfiguriert. Bitte ein Profil in den Verbindungseinstellungen auswählen.');
        }

        $profile = WebsiteProfile::findOrFail($profileId);
        $payload = $profile->payload ?? [];
        $language = $payload['default_locale'] ?? 'de';

        $http = $this->authenticatedRequest($connection);
        $shopUrl = $settings['shop_url'] ?? config('connectors.shopware.shop_url');

        // Tax-ID + Sales Channel ID aus Shopware holen (einmal pro Sync)
        $taxIdMapping = $shopwareFields['tax_id'] ?? [];
        if (empty($taxIdMapping) || ($taxIdMapping['mode'] ?? '') === 'default') {
            $defaultTaxId = $this->productService->fetchDefaultTaxId($http, $shopUrl);
            if ($defaultTaxId) {
                $shopwareFields['tax_id'] = ['mode' => 'fixed', 'value' => $defaultTaxId];
            }
        }

        $salesChannelMapping = $shopwareFields['_sales_channel_id'] ?? [];
        if (empty($salesChannelMapping) || empty($salesChannelMapping['value'])) {
            $defaultSalesChannelId = $this->productService->fetchDefaultSalesChannelId($http, $shopUrl);
            if ($defaultSalesChannelId) {
                $shopwareFields['_sales_channel_id'] = ['value' => $defaultSalesChannelId];
            }
        }

        // Property Groups einmal vorab synchronisieren (Performance: nicht pro Produkt)
        // Manuell konfiguriert oder automatisch aus Profil-Attributsichten
        $propertyMap = [];
        $propertyAttrIds = $shopwareFields['_property_attribute_ids'] ?? [];
        if (empty($propertyAttrIds)) {
            // Automatisch: alle Selection-Attribute aus den Profil-Attributsichten
            // Dummy-Product für die Auflösung (wir brauchen nur die Profil-Logik)
            $propertyAttrIds = $this->productService->resolvePropertyAttributeIds(new \App\Models\Product(), $payload);
        }
        if (!empty($propertyAttrIds)) {
            $propertyMap = $this->propertyService->syncPropertyGroups(
                $http, $shopUrl, $connection->id, $propertyAttrIds, $language,
            );
            foreach ($propertyMap as $attrId => $mapping) {
                $this->logSync($connection, 'property_group_sync', 'attribute', $attrId, 'success', $mapping['group_id']);
                foreach ($mapping['options'] as $entryId => $optionId) {
                    $this->logSync($connection, 'property_option_sync', 'value_list_entry', $entryId, 'success', $optionId);
                }
            }
        }

        $result = [
            'products'    => ['success' => 0, 'failed' => 0],
            'media'       => ['success' => 0, 'failed' => 0],
            'properties'  => ['groups' => count($propertyMap), 'options' => collect($propertyMap)->sum(fn ($m) => count($m['options']))],
        ];

        // Produkte aus Hierarchie laden (mit Medien!)
        $products = $this->loadProductsFromProfile($payload);

        // Produkte synchronisieren (in Chunks)
        $products->chunk(50, function ($chunk) use (
            $http, $shopUrl, $connection, $payload, $shopwareFields,
            $language, $propertyMap, &$result,
        ) {
            foreach ($chunk as $product) {
                try {
                    // Properties pro Produkt auflösen (nur Lookup, kein API-Call)
                    $properties = $this->propertyService->resolveProductProperties($product, $propertyMap, $language);

                    // Kategorie-Zuordnung (aus vorherigem Hierarchie-Sync)
                    $categoryId = null;
                    if ($product->master_hierarchy_node_id) {
                        $categoryId = $this->getCategoryIdForNode($connection->id, $product->master_hierarchy_node_id);
                    }

                    // Produkt-Daten synchronisieren (inkl. Kategorie im Payload)
                    $productResult = $this->productService->syncProductFromProfile(
                        $http, $shopUrl, $product, $payload, $shopwareFields, $language, $properties, $categoryId,
                    );

                    $externalId = $productResult['product_id'] ?? null;

                    // Medien synchronisieren (wenn aktiviert)
                    $syncMedia = $shopwareFields['_sync_media']['enabled'] ?? true;
                    if ($syncMedia) {
                        $product->loadMissing('media');
                        if ($product->media->isNotEmpty()) {
                            $position = 0;
                            foreach ($product->media as $media) {
                                try {
                                    $mediaId = $this->mediaService->uploadMedia($http, $shopUrl, $media);
                                    $this->mediaService->assignMediaToProduct($http, $shopUrl, $externalId, $mediaId, $position);
                                    $result['media']['success']++;
                                    $this->logSync($connection, 'media_sync', 'media', $media->id, 'success', $mediaId);
                                    $position++;
                                } catch (\Throwable $e) {
                                    $result['media']['failed']++;
                                    $this->logSync($connection, 'media_sync', 'media', $media->id, 'failed', null, $e->getMessage());
                                }
                            }
                        }
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
                        ? $this->parseShopwareError($e)
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

                    Log::channel('connectors')->error("Profil-Sync fehlgeschlagen: {$product->sku}", [
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
     * Vergleicht Checksums der aktuellen Produktdaten mit den gespeicherten Checksums
     * aus dem letzten Sync. Erkennt auch Orphans (Produkte im Shop, nicht mehr im PIM).
     *
     * @return array{products: array{new: int, updated: int, unchanged: int, failed: int}, media: array{success: int, failed: int}, orphans: array, properties: array}
     */
    public function deltaSyncFromProfile(ConnectorConnection $connection): array
    {
        $settings = $connection->settings ?? [];
        $profileId = $settings['website_profile_id'] ?? null;
        $shopwareFields = $settings['shopware_fields'] ?? [];

        if (!$profileId) {
            throw new \RuntimeException('Kein Vorschau-Profil konfiguriert. Bitte ein Profil in den Verbindungseinstellungen auswählen.');
        }

        $profile = WebsiteProfile::findOrFail($profileId);
        $payload = $profile->payload ?? [];
        $language = $payload['default_locale'] ?? 'de';

        $http = $this->authenticatedRequest($connection);
        $shopUrl = $settings['shop_url'] ?? config('connectors.shopware.shop_url');

        // Setup: Tax-ID + Sales Channel ID (einmal pro Sync)
        $shopwareFields = $this->resolveShopwareDefaults($http, $shopUrl, $shopwareFields);

        // Property Groups einmal vorab synchronisieren
        $propertyMap = [];
        $propertyAttrIds = $shopwareFields['_property_attribute_ids'] ?? [];
        if (empty($propertyAttrIds)) {
            $propertyAttrIds = $this->productService->resolvePropertyAttributeIds(new Product(), $payload);
        }
        if (!empty($propertyAttrIds)) {
            $propertyMap = $this->propertyService->syncPropertyGroups(
                $http, $shopUrl, $connection->id, $propertyAttrIds, $language,
            );
            foreach ($propertyMap as $attrId => $mapping) {
                $this->logSync($connection, 'property_group_sync', 'attribute', $attrId, 'success', $mapping['group_id']);
                foreach ($mapping['options'] as $entryId => $optionId) {
                    $this->logSync($connection, 'property_option_sync', 'value_list_entry', $entryId, 'success', $optionId);
                }
            }
        }

        // Erlaubte Attribut-IDs aus Profil auflösen (für Checksum-Berechnung)
        $allowedAttributeIds = $this->productService->resolveAllowedAttributeIds(new Product(), $payload);

        $result = [
            'products'   => ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0],
            'media'      => ['success' => 0, 'failed' => 0],
            'orphans'    => [],
            'properties' => ['groups' => count($propertyMap), 'options' => collect($propertyMap)->sum(fn ($m) => count($m['options']))],
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
            $http, $shopUrl, $connection, $payload, $shopwareFields,
            $language, $propertyMap, $allowedAttributeIds, $propertyAttrIds,
            &$existingChecksums, &$currentProductIds, &$result,
        ) {
            // Batch: Checksums für den gesamten Chunk berechnen
            $chunkChecksums = $this->checksumService->computeChecksumsBatch(
                $chunk, $payload, $shopwareFields, $language, $allowedAttributeIds, $propertyAttrIds,
            );

            // Batch: Properties für den gesamten Chunk auflösen
            $chunkProductIds = $chunk->pluck('id')->toArray();
            $currentProductIds = array_merge($currentProductIds, $chunkProductIds);
            $bulkProperties = $this->propertyService->resolveProductPropertiesBulk($chunkProductIds, $propertyMap);

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
                    $properties = $bulkProperties[$product->id] ?? [];

                    // Kategorie-Zuordnung
                    $categoryId = null;
                    if ($product->master_hierarchy_node_id) {
                        $categoryId = $this->getCategoryIdForNode($connection->id, $product->master_hierarchy_node_id);
                    }

                    // Produkt synchronisieren
                    $productResult = $this->productService->syncProductFromProfile(
                        $http, $shopUrl, $product, $payload, $shopwareFields, $language, $properties, $categoryId,
                    );

                    $externalId = $productResult['product_id'] ?? null;

                    // Medien synchronisieren
                    $syncMedia = $shopwareFields['_sync_media']['enabled'] ?? true;
                    if ($syncMedia) {
                        $product->loadMissing('media');
                        if ($product->media->isNotEmpty()) {
                            $position = 0;
                            foreach ($product->media as $media) {
                                try {
                                    $mediaId = $this->mediaService->uploadMedia($http, $shopUrl, $media);
                                    $this->mediaService->assignMediaToProduct($http, $shopUrl, $externalId, $mediaId, $position);
                                    $result['media']['success']++;
                                    $this->logSync($connection, 'media_sync', 'media', $media->id, 'success', $mediaId);
                                    $position++;
                                } catch (\Throwable $e) {
                                    $result['media']['failed']++;
                                    $this->logSync($connection, 'media_sync', 'media', $media->id, 'failed', null, $e->getMessage());
                                }
                            }
                        }
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
                        ? $this->parseShopwareError($e)
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

                    Log::channel('connectors')->error("Delta-Sync fehlgeschlagen: {$product->sku}", [
                        'error' => $errorDetail,
                    ]);
                }
            }
        });

        // Orphan-Erkennung: Produkte mit Checksum aber nicht mehr im Profil
        $syncedProductIds = array_keys($existingChecksums);
        $orphanIds = array_diff($syncedProductIds, $currentProductIds);

        if (!empty($orphanIds)) {
            // Produkte laden die noch existieren (gelöschte Produkte fehlen → als 'deleted' markieren)
            $orphanProducts = Product::whereIn('id', $orphanIds)->get(['id', 'sku', 'name']);
            $foundIds = $orphanProducts->pluck('id')->toArray();
            $deletedIds = array_diff($orphanIds, $foundIds);

            $result['orphans'] = $orphanProducts->map(fn ($p) => [
                'id'      => $p->id,
                'sku'     => $p->sku,
                'name'    => $p->name,
                'deleted' => false,
                'external_id' => null,
            ])->values()->toArray();

            // Gelöschte Produkte hinzufügen (nur ID bekannt)
            foreach ($deletedIds as $deletedId) {
                $result['orphans'][] = [
                    'id'          => $deletedId,
                    'sku'         => '(gelöscht)',
                    'name'        => '(Produkt gelöscht)',
                    'deleted'     => true,
                    'external_id' => null,
                ];
            }

            // External IDs für Orphans aus der Checksum-Tabelle holen
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
     * Löscht alle vom PIM synchronisierten Produkte und Kategorien aus Shopware.
     *
     * Verwendet die Sync-Logs und Checksums um nur PIM-eigene Entitäten zu entfernen.
     *
     * @return array{products: array{deleted: int, failed: int}, categories: array{deleted: int, failed: int}}
     */
    public function resetShop(ConnectorConnection $connection): array
    {
        $http = $this->authenticatedRequest($connection);
        $shopUrl = rtrim($connection->settings['shop_url'] ?? config('connectors.shopware.shop_url'), '/');

        $result = [
            'products'   => ['deleted' => 0, 'failed' => 0, 'total' => 0],
            'categories' => ['deleted' => 0, 'failed' => 0, 'total' => 0],
        ];

        // 1. Produkte löschen — aus Checksums (zuverlässigste Quelle)
        $productExternalIds = ConnectorProductChecksum::where('connection_id', $connection->id)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->unique()
            ->values()
            ->toArray();

        // Zusätzlich aus Sync-Logs (für Produkte ohne Checksum)
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

        // In Chunks von 50 löschen
        foreach (array_chunk($allProductIds, 50) as $chunk) {
            $payload = array_map(fn (string $id) => ['id' => $id], $chunk);

            try {
                $response = $http->post("{$shopUrl}/api/_action/sync", [
                    'delete-products' => [
                        'action'  => 'delete',
                        'entity'  => 'product',
                        'payload' => $payload,
                    ],
                ]);
                $response->throw();
                $result['products']['deleted'] += count($chunk);
            } catch (\Throwable $e) {
                // Einzeln versuchen falls Batch fehlschlägt (manche IDs existieren ggf. nicht mehr)
                foreach ($chunk as $productId) {
                    try {
                        $response = $http->post("{$shopUrl}/api/_action/sync", [
                            'delete-products' => [
                                'action'  => 'delete',
                                'entity'  => 'product',
                                'payload' => [['id' => $productId]],
                            ],
                        ]);
                        $response->throw();
                        $result['products']['deleted']++;
                    } catch (\Throwable) {
                        $result['products']['failed']++;
                    }
                }
            }
        }

        // 2. Kategorien löschen — aus Sync-Logs (Blätter zuerst → umgekehrte Reihenfolge)
        $categoryExternalIds = ConnectorSyncLog::where('connector_connection_id', $connection->id)
            ->where('action', 'category_sync')
            ->where('entity_type', 'hierarchy_node')
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->orderByDesc('created_at')
            ->pluck('external_id')
            ->unique()
            ->values()
            ->toArray();

        $result['categories']['total'] = count($categoryExternalIds);

        // Kategorien einzeln löschen (Reihenfolge wichtig wegen Eltern-Kind-Beziehungen)
        foreach (array_chunk($categoryExternalIds, 50) as $chunk) {
            $payload = array_map(fn (string $id) => ['id' => $id], $chunk);

            try {
                $response = $http->post("{$shopUrl}/api/_action/sync", [
                    'delete-categories' => [
                        'action'  => 'delete',
                        'entity'  => 'category',
                        'payload' => $payload,
                    ],
                ]);
                $response->throw();
                $result['categories']['deleted'] += count($chunk);
            } catch (\Throwable) {
                // Einzeln versuchen
                foreach ($chunk as $categoryId) {
                    try {
                        $response = $http->post("{$shopUrl}/api/_action/sync", [
                            'delete-categories' => [
                                'action'  => 'delete',
                                'entity'  => 'category',
                                'payload' => [['id' => $categoryId]],
                            ],
                        ]);
                        $response->throw();
                        $result['categories']['deleted']++;
                    } catch (\Throwable) {
                        $result['categories']['failed']++;
                    }
                }
            }
        }

        // 3. Lokale Checksums und Sync-Logs bereinigen
        ConnectorProductChecksum::where('connection_id', $connection->id)->delete();
        $connection->syncLogs()->delete();

        // Reset-Aktion loggen
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
     * Löst Shopware-Defaults auf (Tax-ID, Sales Channel ID) — einmal pro Sync.
     */
    private function resolveShopwareDefaults(
        \Illuminate\Http\Client\PendingRequest $http,
        string $shopUrl,
        array $shopwareFields,
    ): array {
        $taxIdMapping = $shopwareFields['tax_id'] ?? [];
        if (empty($taxIdMapping) || ($taxIdMapping['mode'] ?? '') === 'default') {
            $defaultTaxId = $this->productService->fetchDefaultTaxId($http, $shopUrl);
            if ($defaultTaxId) {
                $shopwareFields['tax_id'] = ['mode' => 'fixed', 'value' => $defaultTaxId];
            }
        }

        $salesChannelMapping = $shopwareFields['_sales_channel_id'] ?? [];
        if (empty($salesChannelMapping) || empty($salesChannelMapping['value'])) {
            $defaultSalesChannelId = $this->productService->fetchDefaultSalesChannelId($http, $shopUrl);
            if ($defaultSalesChannelId) {
                $shopwareFields['_sales_channel_id'] = ['value' => $defaultSalesChannelId];
            }
        }

        return $shopwareFields;
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
}
