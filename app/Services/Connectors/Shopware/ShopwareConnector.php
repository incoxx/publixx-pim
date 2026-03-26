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

        // Tax-ID automatisch von Shopware holen, wenn nicht konfiguriert
        if (empty($options['tax_id'])) {
            $options['_default_tax_id'] = $this->productService->fetchDefaultTaxId($http, $shopUrl);
        }

        $result = $this->productService->syncProduct($http, $shopUrl, $product, $language, $options);

        $externalId = $result['product_id'] ?? null;

        $this->logSync(
            $connection,
            'product_push',
            'product',
            $product->id,
            'success',
            $externalId,
            null,
            ['language' => $language, 'synced_fields' => array_keys($result)],
        );

        return $result;
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

        $result = [
            'categories' => ['synced' => 0, 'errors' => 0],
            'products'   => ['success' => 0, 'failed' => 0],
            'media'      => ['success' => 0, 'failed' => 0],
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
            $language, $categoryMap, &$result,
        ) {
            foreach ($chunk as $product) {
                try {
                    // Produkt-Daten synchronisieren
                    $productResult = $this->productService->syncProductFromProfile(
                        $http, $shopUrl, $product, $payload, $shopwareFields, $language,
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
                        ['language' => $language, 'synced_fields' => $productResult['synced_data'] ?? []],
                    );

                    $result['products']['success']++;
                } catch (\Throwable $e) {
                    $this->logSync(
                        $connection, 'profile_sync', 'product',
                        $product->id, 'failed', null, $e->getMessage(),
                    );
                    $result['products']['failed']++;

                    Log::channel('connectors')->error("Profil-Sync fehlgeschlagen: {$product->id}", [
                        'error' => $e->getMessage(),
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
