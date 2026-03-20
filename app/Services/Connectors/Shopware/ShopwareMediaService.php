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
     * @return string Shopware Media-ID
     */
    public function uploadMedia(PendingRequest $http, string $shopUrl, Media $media): string
    {
        $shopUrl = rtrim($shopUrl, '/');

        // 1. Media-Eintrag in Shopware erstellen
        $mediaId = Str::uuid()->toString();
        $http->post("{$shopUrl}/api/media", [
            'id'      => $mediaId,
            'name'    => pathinfo($media->file_name, PATHINFO_FILENAME),
        ])->throw();

        // 2. Datei per URL hochladen (extension + fileName als Query-Parameter erforderlich)
        $publicUrl = url("/api/v1/media/file/{$media->file_name}");
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';
        $fileName = pathinfo($media->file_name, PATHINFO_FILENAME);

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
     */
    public function assignMediaToProduct(
        PendingRequest $http,
        string $shopUrl,
        string $productId,
        string $mediaId,
        int $position = 0,
    ): void {
        $shopUrl = rtrim($shopUrl, '/');

        $http->post("{$shopUrl}/api/product-media", [
            'productId' => $productId,
            'mediaId'   => $mediaId,
            'position'  => $position,
        ])->throw();
    }
}
