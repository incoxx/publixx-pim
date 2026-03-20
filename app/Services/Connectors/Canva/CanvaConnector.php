<?php

declare(strict_types=1);

namespace App\Services\Connectors\Canva;

use App\Models\ConnectorConnection;
use App\Models\Media;
use App\Models\Product;
use App\Services\Connectors\AbstractConnector;

class CanvaConnector extends AbstractConnector
{
    public function __construct(
        private readonly CanvaAuthService $authService,
        private readonly CanvaAssetService $assetService,
        private readonly CanvaDataService $dataService,
    ) {}

    public function getType(): string
    {
        return 'canva';
    }

    public function getName(): string
    {
        return 'Canva';
    }

    public function getDescription(): string
    {
        return 'Canva Connect API – Assets hochladen und Designs erstellen';
    }

    public function getCapabilities(): array
    {
        return ['asset_upload', 'product_data', 'design_create'];
    }

    public function isConfigured(): bool
    {
        return $this->authService->isConfigured();
    }

    public function getAuthorizationUrl(): array
    {
        return $this->authService->getAuthorizationUrl();
    }

    public function handleCallback(string $code, ?string $codeVerifier = null): array
    {
        return $this->authService->exchangeCode($code, $codeVerifier ?? '');
    }

    public function refreshToken(ConnectorConnection $connection): void
    {
        $this->authService->refreshAccessToken($connection);
    }

    public function uploadAsset(ConnectorConnection $connection, Media $media): string
    {
        $http = $this->authenticatedRequest($connection);
        $publicUrl = url("/api/v1/media/file/{$media->file_name}");

        $jobId = $this->assetService->uploadFromUrl($http, $media, $publicUrl);

        $this->logSync(
            $connection,
            'asset_upload',
            'media',
            $media->id,
            'success',
            $jobId,
        );

        return $jobId;
    }

    public function pushProductData(ConnectorConnection $connection, Product $product, array $options = []): array
    {
        $http = $this->authenticatedRequest($connection);
        $productData = $this->dataService->collectProductData($product, $options);

        $brandTemplateId = $options['brand_template_id'] ?? null;
        $result = $this->dataService->createDesignFromProduct($http, $productData, $brandTemplateId);

        $externalId = $result['design']['id'] ?? null;

        $this->logSync(
            $connection,
            'product_push',
            'product',
            $product->id,
            'success',
            $externalId,
            null,
            ['product_data_keys' => array_keys($productData)],
        );

        return $result;
    }

    /**
     * Prüft den Status eines Asset-Upload-Jobs.
     */
    public function getAssetUploadStatus(ConnectorConnection $connection, string $jobId): array
    {
        $http = $this->authenticatedRequest($connection);

        return $this->assetService->getUploadJobStatus($http, $jobId);
    }
}
