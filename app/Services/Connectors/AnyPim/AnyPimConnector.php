<?php

declare(strict_types=1);

namespace App\Services\Connectors\AnyPim;

use App\Models\ConnectorConnection;
use App\Models\Media;
use App\Models\Product;
use App\Services\Connectors\AbstractConnector;
use Illuminate\Support\Facades\Log;

class AnyPimConnector extends AbstractConnector
{
    public function __construct(
        private readonly AnyPimAuthService $authService,
        private readonly AnyPimProductService $productService,
        private readonly AnyPimMediaService $mediaService,
        private readonly AnyPimCategoryService $categoryService,
        private readonly AnyPimChecksumService $checksumService,
    ) {}

    public function getType(): string
    {
        return 'anypim';
    }

    public function getName(): string
    {
        return 'anyPIM';
    }

    public function getDescription(): string
    {
        return 'Bidirektionaler Sync zwischen anyPIM-Instanzen';
    }

    public function getCapabilities(): array
    {
        return ['product_data', 'asset_upload', 'profile_sync', 'bidirectional_sync', 'pull_data'];
    }

    public function isConfigured(): bool
    {
        return $this->authService->isConfigured();
    }

    public function getAuthorizationUrl(): array
    {
        // anyPIM nutzt Client Credentials, keine Browser-Redirect-basierte OAuth-Flow
        return [
            'url'           => '',
            'state'         => '',
            'code_verifier' => null,
        ];
    }

    public function handleCallback(string $code, ?string $codeVerifier = null, string $shopUrl = ''): array
    {
        // Bei anyPIM: "code" = API-Key (Bearer Token), "shopUrl" = remote_url
        return $this->authService->authenticate($code, $codeVerifier ?? '', $shopUrl);
    }

    public function refreshToken(ConnectorConnection $connection): void
    {
        $this->authService->refreshAccessToken($connection);
    }

    public function uploadAsset(ConnectorConnection $connection, Media $media): string
    {
        $http = $this->authenticatedRequest($connection);
        $remoteUrl = $this->authService->getRemoteUrl($connection);

        return $this->mediaService->pushMedia($http, $remoteUrl, $media);
    }

    public function pushProductData(ConnectorConnection $connection, Product $product, array $options = []): array
    {
        $http = $this->authenticatedRequest($connection);
        $remoteUrl = $this->authService->getRemoteUrl($connection);

        return $this->productService->pushProducts($http, $remoteUrl, ['skus' => [$product->sku]], $options);
    }

    // =========================================================================
    // anyPIM-spezifische Methoden (erweitert das Standard-ConnectorInterface)
    // =========================================================================

    /**
     * Vollständiger Push: Alle Produkte zur Remote-Instanz senden.
     */
    public function pushAll(ConnectorConnection $connection, array $filters = [], array $options = []): array
    {
        $http = $this->authenticatedRequest($connection);
        $remoteUrl = $this->authService->getRemoteUrl($connection);
        $settings = $connection->settings ?? [];

        $mergedOptions = array_merge([
            'conflict_resolution' => $settings['conflict_resolution'] ?? 'remote_wins',
            'sync_attributes'     => true,
            'sync_prices'         => true,
            'sync_media'          => true,
        ], $options);

        $result = $this->productService->pushProducts($http, $remoteUrl, $filters, $mergedOptions);

        $status = ($result['errors'] ?? 0) > 0 ? 'partial' : 'success';
        $this->logSync($connection, 'push_all', 'products', '*', $status, null, null, $result);

        return $result;
    }

    /**
     * Vollständiger Pull: Alle Produkte von Remote-Instanz holen.
     */
    public function pullAll(ConnectorConnection $connection, array $filters = [], array $options = []): array
    {
        $http = $this->authenticatedRequest($connection);
        $remoteUrl = $this->authService->getRemoteUrl($connection);
        $settings = $connection->settings ?? [];

        $mergedOptions = array_merge([
            'conflict_resolution' => $settings['conflict_resolution'] ?? 'remote_wins',
            'sync_attributes'     => true,
            'sync_prices'         => true,
            'sync_media'          => true,
        ], $options);

        $result = $this->productService->pullProducts($http, $remoteUrl, $filters, $mergedOptions);

        $status = ($result['errors'] ?? 0) > 0 ? 'partial' : 'success';
        $this->logSync($connection, 'pull_all', 'products', '*', $status, null, null, $result);

        return $result;
    }

    /**
     * Delta-Push: Nur geänderte Produkte zur Remote-Instanz senden.
     */
    public function deltaPush(ConnectorConnection $connection, array $filters = [], array $options = []): array
    {
        $http = $this->authenticatedRequest($connection);
        $remoteUrl = $this->authService->getRemoteUrl($connection);

        $result = $this->productService->deltaPush($http, $remoteUrl, $filters, $options);

        $status = ($result['errors'] ?? 0) > 0 ? 'partial' : 'success';
        $this->logSync($connection, 'delta_push', 'products', '*', $status, null, null, $result);

        return $result;
    }

    /**
     * Delta-Pull: Nur geänderte Produkte von Remote-Instanz holen.
     */
    public function deltaPull(ConnectorConnection $connection, array $filters = [], array $options = []): array
    {
        $http = $this->authenticatedRequest($connection);
        $remoteUrl = $this->authService->getRemoteUrl($connection);

        $result = $this->productService->deltaPull($http, $remoteUrl, $filters, $options);

        $status = ($result['errors'] ?? 0) > 0 ? 'partial' : 'success';
        $this->logSync($connection, 'delta_pull', 'products', '*', $status, null, null, $result);

        return $result;
    }

    /**
     * Bidirektionaler Sync: Push + Pull in einem Durchlauf.
     */
    public function syncBidirectional(ConnectorConnection $connection, array $filters = [], array $options = []): array
    {
        $settings = $connection->settings ?? [];
        $syncDirection = $settings['sync_direction'] ?? 'bidirectional';

        $pushResult = ['pushed' => 0, 'skipped' => 0, 'errors' => 0];
        $pullResult = ['pulled' => 0, 'skipped' => 0, 'errors' => 0];

        // Push: Lokale Änderungen zur Remote senden
        if (in_array($syncDirection, ['push', 'bidirectional'])) {
            try {
                $pushResult = $this->deltaPush($connection, $filters, $options);
            } catch (\Throwable $e) {
                Log::channel('connectors')->error("anyPIM bidirektionaler Push fehlgeschlagen: {$e->getMessage()}");
                $pushResult['errors'] = 1;
            }
        }

        // Pull: Remote-Änderungen lokal holen
        if (in_array($syncDirection, ['pull', 'bidirectional'])) {
            try {
                $pullResult = $this->deltaPull($connection, $filters, $options);
            } catch (\Throwable $e) {
                Log::channel('connectors')->error("anyPIM bidirektionaler Pull fehlgeschlagen: {$e->getMessage()}");
                $pullResult['errors'] = 1;
            }
        }

        $result = [
            'push' => $pushResult,
            'pull' => $pullResult,
            'direction' => $syncDirection,
        ];

        $totalErrors = ($pushResult['errors'] ?? 0) + ($pullResult['errors'] ?? 0);
        $status = $totalErrors > 0 ? 'partial' : 'success';
        $this->logSync($connection, 'sync_bidirectional', 'products', '*', $status, null, null, $result);

        return $result;
    }

    /**
     * Schema-Info der Remote-Instanz abrufen.
     */
    public function fetchRemoteSchema(ConnectorConnection $connection): array
    {
        $http = $this->authenticatedRequest($connection);
        $remoteUrl = $this->authService->getRemoteUrl($connection);

        return $this->categoryService->fetchRemoteSchema($http, $remoteUrl);
    }

    /**
     * Verbindung zur Remote-Instanz testen.
     */
    public function testConnection(ConnectorConnection $connection): array
    {
        try {
            $http = $this->authenticatedRequest($connection);
            $remoteUrl = $this->authService->getRemoteUrl($connection);

            $schema = $this->categoryService->fetchRemoteSchema($http, $remoteUrl);

            return [
                'status'  => 'ok',
                'message' => 'Verbindung erfolgreich.',
                'schema'  => $schema,
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => 'error',
                'message' => 'Verbindung fehlgeschlagen: ' . $e->getMessage(),
            ];
        }
    }
}
