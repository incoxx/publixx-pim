<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\ConnectorConnection;
use App\Models\Media;
use App\Models\Product;
use App\Services\Connectors\AbstractConnector;

class ShopwareConnector extends AbstractConnector
{
    public function __construct(
        private readonly ShopwareAuthService $authService,
        private readonly ShopwareProductService $productService,
        private readonly ShopwareMediaService $mediaService,
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
        return ['asset_upload', 'product_data'];
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

    public function handleCallback(string $code, ?string $codeVerifier = null): array
    {
        return $this->authService->authenticate($code, $codeVerifier ?? '');
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
}
