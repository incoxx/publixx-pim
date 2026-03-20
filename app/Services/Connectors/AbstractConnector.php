<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\ConnectorConnection;
use App\Models\ConnectorSyncLog;
use App\Models\Media;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractConnector implements ConnectorInterface
{
    /**
     * HTTP-Request mit automatischem Token-Refresh.
     */
    protected function authenticatedRequest(ConnectorConnection $connection): \Illuminate\Http\Client\PendingRequest
    {
        if ($connection->isTokenExpired()) {
            $this->refreshToken($connection);
            $connection->refresh();
        }

        return Http::withToken($connection->access_token)
            ->acceptJson();
    }

    /**
     * Erstellt einen Sync-Log-Eintrag.
     */
    protected function logSync(
        ConnectorConnection $connection,
        string $action,
        string $entityType,
        string $entityId,
        string $status,
        ?string $externalId = null,
        ?string $errorMessage = null,
        ?array $meta = null,
    ): ConnectorSyncLog {
        return ConnectorSyncLog::create([
            'connector_connection_id' => $connection->id,
            'action'                  => $action,
            'entity_type'             => $entityType,
            'entity_id'               => $entityId,
            'external_id'             => $externalId,
            'status'                  => $status,
            'error_message'           => $errorMessage,
            'meta'                    => $meta,
            'created_at'              => now(),
        ]);
    }

    /**
     * Bulk-Upload von Media-Assets mit Logging.
     *
     * @param  Media[]  $mediaItems
     * @return array<string, array{status: string, external_id: string|null, error: string|null}>
     */
    public function uploadAssetsBulk(ConnectorConnection $connection, array $mediaItems): array
    {
        $results = [];

        foreach ($mediaItems as $media) {
            try {
                $externalId = $this->uploadAsset($connection, $media);
                $results[$media->id] = [
                    'status'      => 'success',
                    'external_id' => $externalId,
                    'error'       => null,
                ];
            } catch (\Throwable $e) {
                Log::channel('connectors')->error("Asset-Upload fehlgeschlagen: {$media->id}", [
                    'connector' => $this->getType(),
                    'error'     => $e->getMessage(),
                ]);

                $this->logSync($connection, 'asset_upload', 'media', $media->id, 'failed', null, $e->getMessage());

                $results[$media->id] = [
                    'status'      => 'failed',
                    'external_id' => null,
                    'error'       => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk-Push von Produktdaten mit Logging.
     *
     * @param  Product[]  $products
     * @return array<string, array{status: string, result: array|null, error: string|null}>
     */
    public function pushProductDataBulk(ConnectorConnection $connection, array $products, array $options = []): array
    {
        $results = [];

        foreach ($products as $product) {
            try {
                $result = $this->pushProductData($connection, $product, $options);
                $results[$product->id] = [
                    'status' => 'success',
                    'result' => $result,
                    'error'  => null,
                ];
            } catch (\Throwable $e) {
                Log::channel('connectors')->error("Produkt-Push fehlgeschlagen: {$product->id}", [
                    'connector' => $this->getType(),
                    'error'     => $e->getMessage(),
                ]);

                $this->logSync($connection, 'product_push', 'product', $product->id, 'failed', null, $e->getMessage());

                $results[$product->id] = [
                    'status' => 'failed',
                    'result' => null,
                    'error'  => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
