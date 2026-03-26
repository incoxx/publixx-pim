<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\ConnectorConnection;
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

                $result = $this->productService->syncProductFromProfile(
                    $http, $shopUrl, $product, $profilePayload, $shopwareFields, $language, $properties,
                );

                // Medien synchronisieren (wenn aktiviert)
                $syncMedia = $shopwareFields['_sync_media']['enabled'] ?? true;
                if ($syncMedia && $product->media && $product->media->isNotEmpty()) {
                    $externalProductId = $result['product_id'] ?? $product->sku;
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
     * Vollständiger Sync basierend auf einem WebsiteProfile.
     *
     * Synchronisiert Kategorien, Produkte und Medien anhand der Profil-Einstellungen.
     *
     * @return array{categories: array, products: array{success: int, failed: int}, media: array{success: int, failed: int}}
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
        $hierarchyId = $payload['hierarchy_id'] ?? null;
        $language = $payload['default_locale'] ?? 'de';
        $excludedNodeIds = $payload['catalog_excluded_node_ids'] ?? [];

        $http = $this->authenticatedRequest($connection);
        $shopUrl = $settings['shop_url'] ?? config('connectors.shopware.shop_url');

        // Tax-ID aus Shopware holen für "default"-Modus (einmal pro Sync)
        $taxIdMapping = $shopwareFields['tax_id'] ?? [];
        if (empty($taxIdMapping) || ($taxIdMapping['mode'] ?? '') === 'default') {
            $defaultTaxId = $this->productService->fetchDefaultTaxId($http, $shopUrl);
            if ($defaultTaxId) {
                $shopwareFields['tax_id'] = ['mode' => 'fixed', 'value' => $defaultTaxId];
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
            'categories'  => ['synced' => 0, 'errors' => 0],
            'products'    => ['success' => 0, 'failed' => 0],
            'media'       => ['success' => 0, 'failed' => 0],
            'properties'  => ['groups' => count($propertyMap), 'options' => collect($propertyMap)->sum(fn ($m) => count($m['options']))],
        ];

        // 1. Kategorien synchronisieren
        $categoryMap = [];
        if ($hierarchyId) {
            $catResult = $this->categoryService->syncHierarchy(
                $http, $shopUrl, $hierarchyId, $language, $excludedNodeIds,
            );
            $result['categories'] = [
                'synced' => $catResult['synced'],
                'errors' => $catResult['errors'],
            ];
            $categoryMap = $catResult['category_map'];

            // Sync-Logs für Kategorien
            foreach ($categoryMap as $nodeId => $shopwareCategoryId) {
                $this->logSync(
                    $connection, 'category_sync', 'hierarchy_node',
                    $nodeId, 'success', $shopwareCategoryId,
                );
            }
        }

        // 2. Produkte aus Hierarchie laden
        $products = $this->loadProductsFromProfile($payload);

        // 3. Produkte synchronisieren (in Chunks)
        $products->chunk(50, function ($chunk) use (
            $http, $shopUrl, $connection, $payload, $shopwareFields,
            $language, $categoryMap, $propertyMap, &$result,
        ) {
            foreach ($chunk as $product) {
                try {
                    // Properties pro Produkt auflösen (nur Lookup, kein API-Call)
                    $properties = $this->propertyService->resolveProductProperties($product, $propertyMap, $language);

                    // Produkt-Daten synchronisieren
                    $productResult = $this->productService->syncProductFromProfile(
                        $http, $shopUrl, $product, $payload, $shopwareFields, $language, $properties,
                    );

                    $externalId = $productResult['product_id'] ?? null;

                    // Produkt-Kategorie-Zuordnung
                    if ($product->masterHierarchyNode && isset($categoryMap[$product->master_hierarchy_node_id])) {
                        try {
                            $this->categoryService->assignProductToCategory(
                                $http, $shopUrl,
                                $externalId,
                                $categoryMap[$product->master_hierarchy_node_id],
                            );
                        } catch (\Throwable $e) {
                            Log::channel('connectors')->warning("Kategorie-Zuordnung fehlgeschlagen: {$product->id}", [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Medien synchronisieren
                    if (!empty($product->media)) {
                        $position = 0;
                        foreach ($product->media as $media) {
                            try {
                                $mediaId = $this->mediaService->uploadMedia($http, $shopUrl, $media);
                                $this->mediaService->assignMediaToProduct($http, $shopUrl, $externalId, $mediaId, $position);
                                $result['media']['success']++;
                                $position++;
                            } catch (\Throwable $e) {
                                $result['media']['failed']++;
                                Log::channel('connectors')->warning("Media-Upload fehlgeschlagen: {$media->id}", [
                                    'error' => $e->getMessage(),
                                ]);
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
