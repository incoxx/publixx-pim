<?php

declare(strict_types=1);

namespace App\Services\Connectors\Cloudinary;

use App\Models\ConnectorConnection;
use App\Models\Media;
use App\Models\Product;
use App\Services\Connectors\AbstractConnector;

class CloudinaryConnector extends AbstractConnector
{
    public function __construct(
        private readonly CloudinaryAssetService $assetService,
    ) {}

    public function getType(): string
    {
        return 'cloudinary';
    }

    public function getName(): string
    {
        return 'Cloudinary';
    }

    public function getDescription(): string
    {
        return 'Cloudinary – Bildoptimierung, Transformation und CDN-Delivery';
    }

    public function getCapabilities(): array
    {
        return ['asset_upload', 'image_transform'];
    }

    public function isConfigured(): bool
    {
        return ! empty(config('connectors.cloudinary.cloud_name'))
            && ! empty(config('connectors.cloudinary.api_key'))
            && ! empty(config('connectors.cloudinary.api_secret'));
    }

    public function getAuthorizationUrl(): array
    {
        // Cloudinary uses API key/secret, no OAuth
        return [
            'url'           => '',
            'state'         => '',
            'code_verifier' => null,
        ];
    }

    public function handleCallback(string $code, ?string $codeVerifier = null, string $shopUrl = ''): array
    {
        // API key-based: credentials stored in settings
        return [
            'access_token'  => $code,
            'refresh_token' => null,
            'expires_in'    => null,
        ];
    }

    public function refreshToken(ConnectorConnection $connection): void
    {
        // API key/secret does not expire
    }

    public function uploadAsset(ConnectorConnection $connection, Media $media): string
    {
        $cloudName = $connection->settings['cloud_name'] ?? config('connectors.cloudinary.cloud_name');
        $apiKey = $connection->settings['api_key'] ?? config('connectors.cloudinary.api_key');
        $apiSecret = $connection->settings['api_secret'] ?? config('connectors.cloudinary.api_secret');

        $result = $this->assetService->upload($cloudName, $apiKey, $apiSecret, $media);

        $this->logSync(
            $connection,
            'asset_upload',
            'media',
            $media->id,
            'success',
            $result['public_id'],
            null,
            [
                'secure_url' => $result['secure_url'] ?? null,
                'format'     => $result['format'] ?? null,
                'bytes'      => $result['bytes'] ?? null,
            ],
        );

        return $result['public_id'];
    }

    public function pushProductData(ConnectorConnection $connection, Product $product, array $options = []): array
    {
        // Cloudinary: upload all product media with product-specific folder/tags
        $cloudName = $connection->settings['cloud_name'] ?? config('connectors.cloudinary.cloud_name');
        $apiKey = $connection->settings['api_key'] ?? config('connectors.cloudinary.api_key');
        $apiSecret = $connection->settings['api_secret'] ?? config('connectors.cloudinary.api_secret');

        $mediaItems = $product->media()->orderByPivot('sort_order')->get();
        $results = [];

        foreach ($mediaItems as $media) {
            $result = $this->assetService->upload(
                $cloudName,
                $apiKey,
                $apiSecret,
                $media,
                "pim/products/{$product->sku}",
                [$product->sku, $product->name],
            );

            $results[] = [
                'media_id'  => $media->id,
                'public_id' => $result['public_id'],
                'url'       => $result['secure_url'] ?? null,
            ];

            $this->logSync(
                $connection,
                'product_push',
                'media',
                $media->id,
                'success',
                $result['public_id'],
            );
        }

        return [
            'product_sku' => $product->sku,
            'uploaded'     => count($results),
            'assets'       => $results,
        ];
    }
}
