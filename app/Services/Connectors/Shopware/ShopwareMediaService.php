<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\Media;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

class ShopwareMediaService
{
    /**
     * Lädt ein Media-Asset nach Shopware 6 hoch.
     *
     * @param  string|null $deterministicId  Wenn gesetzt, wird diese ID verwendet (Upsert, kein Duplikat bei Re-Sync)
     * @return string Shopware Media-ID
     */
    public function uploadMedia(PendingRequest $http, string $shopUrl, Media $media, ?string $deterministicId = null): string
    {
        $shopUrl = rtrim($shopUrl, '/');

        $mediaId = $deterministicId ?? str_replace('-', '', Str::uuid()->toString());
        $fileName = pathinfo($media->file_name, PATHINFO_FILENAME);
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';

        // 1. Media-Eintrag in Shopware erstellen/aktualisieren (Upsert via Sync-API)
        $http->post("{$shopUrl}/api/_action/sync", [
            'write-media' => [
                'action'  => 'upsert',
                'entity'  => 'media',
                'payload' => [[
                    'id'   => $mediaId,
                    'name' => $fileName,
                ]],
            ],
        ])->throw();

        // 2. Datei per URL hochladen
        // config('app.url') muss die öffentliche URL sein die Shopware erreichen kann
        $baseUrl = rtrim(config('app.url', url('/')), '/');
        $publicUrl = "{$baseUrl}/api/v1/media/file/{$media->file_name}";

        $http->post("{$shopUrl}/api/_action/media/{$mediaId}/upload?" . http_build_query([
            'extension' => $extension,
            'fileName'  => $fileName,
        ]), [
            'url' => $publicUrl,
        ])->throw();

        return $mediaId;
    }

    /**
     * Verknüpft ein Shopware-Media mit einem Shopware-Produkt.
     *
     * @return string Die Assignment-ID (für Cover-Zuweisung)
     */
    public function assignMediaToProduct(
        PendingRequest $http,
        string $shopUrl,
        string $productId,
        string $mediaId,
        int $position = 0,
    ): string {
        $shopUrl = rtrim($shopUrl, '/');

        // Deterministische ID: Produkt + Media → immer gleiche Assignment-ID (kein Duplikat bei Re-Sync)
        $assignmentId = substr(md5($productId . $mediaId . 'product-media'), 0, 32);

        $http->post("{$shopUrl}/api/_action/sync", [
            'write-product-media' => [
                'action'  => 'upsert',
                'entity'  => 'product_media',
                'payload' => [[
                    'id'        => $assignmentId,
                    'productId' => $productId,
                    'mediaId'   => $mediaId,
                    'position'  => $position,
                ]],
            ],
        ])->throw();

        return $assignmentId;
    }

    /**
     * Setzt das Cover-Bild eines Shopware-Produkts.
     */
    public function setProductCover(
        PendingRequest $http,
        string $shopUrl,
        string $productId,
        string $coverMediaAssignmentId,
    ): void {
        $shopUrl = rtrim($shopUrl, '/');

        $http->patch("{$shopUrl}/api/product/{$productId}", [
            'coverId' => $coverMediaAssignmentId,
        ])->throw();
    }
}
