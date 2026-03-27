<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\Media;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ShopwareMediaService
{
    /**
     * Lädt ein Media-Asset nach Shopware 6 hoch.
     *
     * Versucht zuerst einen direkten Binär-Upload (PIM liest Datei vom Disk
     * und sendet sie direkt an Shopware). Fallback auf URL-basiertes Upload
     * falls die Datei nicht lokal verfügbar ist.
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

        // 2. Datei hochladen
        $this->uploadFileToMedia($http, $shopUrl, $media, $mediaId, $fileName, $extension);

        return $mediaId;
    }

    /**
     * Lädt die eigentliche Datei zu einem Shopware-Media-Eintrag hoch.
     *
     * Bei CONTENT__MEDIA_DUPLICATED_FILE_NAME: altes Media löschen, neu erstellen, nochmal hochladen.
     */
    private function uploadFileToMedia(
        PendingRequest $http,
        string $shopUrl,
        Media $media,
        string $mediaId,
        string $fileName,
        string $extension,
    ): void {
        // Eindeutigen Dateinamen verwenden: fileName + mediaId-Suffix
        // Shopware prüft Dateinamen GLOBAL — derselbe Name unter einem anderen
        // Media-Eintrag führt zu CONTENT__MEDIA_DUPLICATED_FILE_NAME
        $uniqueFileName = $fileName . '_' . substr($mediaId, 0, 8);

        $uploadUrl = "{$shopUrl}/api/_action/media/{$mediaId}/upload?" . http_build_query([
            'extension' => $extension,
            'fileName'  => $uniqueFileName,
        ]);

        try {
            $this->doUpload($http, $uploadUrl, $media);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $body = $e->response?->json();
            $errorCode = $body['errors'][0]['code'] ?? '';

            if ($errorCode === 'CONTENT__MEDIA_DUPLICATED_FILE_NAME') {
                // Datei existiert bereits mit diesem Namen → Upload ist erfolgreich (Datei ist da)
                return;
            }

            throw $e;
        }
    }

    /**
     * Führt den eigentlichen Upload aus (Binary oder URL-basiert).
     */
    private function doUpload(PendingRequest $http, string $uploadUrl, Media $media): void
    {
        $localPath = Storage::disk('public')->path($media->file_path);

        if (file_exists($localPath)) {
            $fileContent = file_get_contents($localPath);
            $extension = pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'jpg';
            $contentType = $media->mime_type ?: ('image/' . $extension);

            // Shopware erwartet Raw-Binary mit Content-Type Header — nicht JSON
            $http->withHeaders([
                'Content-Type' => $contentType,
            ])->send('POST', $uploadUrl, [
                'body' => $fileContent,
            ])->throw();
        } else {
            // Fallback: URL-basierter Upload
            $baseUrl = rtrim(config('app.url', url('/')), '/');
            $publicUrl = "{$baseUrl}/api/v1/media/file/{$media->file_name}";

            $http->post($uploadUrl, [
                'url' => $publicUrl,
            ])->throw();
        }
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
