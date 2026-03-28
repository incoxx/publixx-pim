<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopify;

use App\Models\Media;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Storage;

class ShopifyMediaService
{
    private const API_VERSION = '2025-01';

    /**
     * Erstellt ein Produktbild in Shopify.
     *
     * Shopify-Bilder gehören direkt zum Produkt (kein separates Media-Entity).
     * Versucht zuerst base64-Upload, Fallback auf URL-basiertes Upload.
     *
     * @return string Shopify Image-ID
     */
    public function createProductImage(
        PendingRequest $http,
        string $shopUrl,
        string $shopifyProductId,
        Media $media,
        int $position = 1,
    ): string {
        $shopUrl = rtrim($shopUrl, '/');

        $imagePayload = [
            'position' => $position,
            'alt'      => $media->alt_text ?: pathinfo($media->file_name, PATHINFO_FILENAME),
        ];

        // Versuche lokale Datei als base64 zu senden
        $localPath = Storage::disk('public')->path($media->file_path);

        if (file_exists($localPath)) {
            $fileContent = file_get_contents($localPath);
            $imagePayload['attachment'] = base64_encode($fileContent);
            $imagePayload['filename'] = $media->file_name;
        } else {
            // Fallback: URL-basierter Upload
            $baseUrl = rtrim(config('app.url', url('/')), '/');
            $imagePayload['src'] = "{$baseUrl}/api/v1/media/file/{$media->file_name}";
        }

        $response = $http->post(
            "{$shopUrl}/admin/api/" . self::API_VERSION . "/products/{$shopifyProductId}/images.json",
            ['image' => $imagePayload],
        );

        $response->throw();
        $data = $response->json();

        return (string) ($data['image']['id'] ?? '');
    }

    /**
     * Löscht alle Bilder eines Shopify-Produkts.
     */
    public function deleteProductImages(
        PendingRequest $http,
        string $shopUrl,
        string $shopifyProductId,
    ): int {
        $shopUrl = rtrim($shopUrl, '/');
        $deleted = 0;

        // Alle Bilder des Produkts laden
        try {
            $response = $http->get(
                "{$shopUrl}/admin/api/" . self::API_VERSION . "/products/{$shopifyProductId}/images.json",
            );
            $response->throw();
            $images = $response->json()['images'] ?? [];
        } catch (\Throwable) {
            return 0;
        }

        // Bilder einzeln löschen
        foreach ($images as $image) {
            try {
                $http->delete(
                    "{$shopUrl}/admin/api/" . self::API_VERSION . "/products/{$shopifyProductId}/images/{$image['id']}.json",
                )->throw();
                $deleted++;
            } catch (\Throwable) {
                // Einzelne Löschfehler ignorieren
            }
        }

        return $deleted;
    }

    /**
     * Setzt das Cover-Bild eines Shopify-Produkts (position = 1).
     */
    public function setProductCover(
        PendingRequest $http,
        string $shopUrl,
        string $shopifyProductId,
        string $imageId,
    ): void {
        $shopUrl = rtrim($shopUrl, '/');

        $http->put(
            "{$shopUrl}/admin/api/" . self::API_VERSION . "/products/{$shopifyProductId}/images/{$imageId}.json",
            ['image' => ['id' => (int) $imageId, 'position' => 1]],
        )->throw();
    }

}
