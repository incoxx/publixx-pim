<?php

declare(strict_types=1);

namespace App\Services\Connectors\SalesforceCommerce;

use App\Models\Product;
use Illuminate\Http\Client\PendingRequest;

/**
 * Verwaltet Produkt-Bilder in SFCC via URL-Referenz (image_groups).
 *
 * Bilder werden nicht hochgeladen, sondern per abs_url aus dem PIM referenziert.
 */
class SalesforceCommerceMediaService
{
    private const API_VERSION = 'v24_5';

    /**
     * Synchronisiert die Produktbilder als SFCC image_groups mit URL-Referenzen.
     *
     * @return array{synced: int, view_types: array}
     */
    public function syncProductImages(
        PendingRequest $http,
        string $instanceUrl,
        Product $product,
        string $productId,
        array $sfccFields = [],
    ): array {
        $instanceUrl = rtrim($instanceUrl, '/');
        $product->loadMissing('media');

        // Nur Bilder (keine Dokumente)
        $images = $product->media
            ->filter(fn ($m) => str_starts_with($m->mime_type ?? '', 'image/'))
            ->sortBy('sort_order')
            ->values();

        if ($images->isEmpty()) {
            return ['synced' => 0, 'view_types' => []];
        }

        $baseUrl = rtrim(config('app.url', ''), '/');
        $viewTypes = $sfccFields['_image_view_types'] ?? ['large', 'medium', 'small'];

        // SFCC image_groups: ein Eintrag pro view_type, jeder mit allen Bildern
        $imageGroups = [];

        foreach ($viewTypes as $viewType) {
            $groupImages = [];
            $position = 0;

            foreach ($images as $media) {
                $imageUrl = "{$baseUrl}/api/v1/media/file/{$media->file_name}";

                $groupImages[] = [
                    'path'    => $imageUrl,
                    'alt'     => ['default' => $media->alt_text ?: pathinfo($media->file_name, PATHINFO_FILENAME)],
                    'title'   => ['default' => $media->alt_text ?: $media->file_name],
                    'abs_url' => $imageUrl,
                ];
                $position++;
            }

            $imageGroups[] = [
                'view_type' => $viewType,
                'images'    => $groupImages,
            ];
        }

        // PATCH Produkt mit image_groups
        $response = $http->patch(
            "{$instanceUrl}/s/-/dw/data/" . self::API_VERSION . "/products/{$productId}",
            ['image_groups' => $imageGroups],
        );

        $response->throw();

        return [
            'synced'     => $images->count(),
            'view_types' => $viewTypes,
        ];
    }

    /**
     * Entfernt alle Bilder eines Produkts in SFCC.
     */
    public function clearProductImages(
        PendingRequest $http,
        string $instanceUrl,
        string $productId,
    ): void {
        $instanceUrl = rtrim($instanceUrl, '/');

        $http->patch(
            "{$instanceUrl}/s/-/dw/data/" . self::API_VERSION . "/products/{$productId}",
            ['image_groups' => []],
        )->throw();
    }
}
