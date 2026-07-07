<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Media;
use App\Models\ProductMediaAssignment;
use App\Models\WebsiteProfile;
use Illuminate\Support\Collection;

/**
 * PrimaryImageResolver — einzige Quelle der Wahrheit für die Frage
 * "welches Medium ist das Titelbild dieses Produkts?".
 *
 * Fallback-Kette (identisch für alle Aufrufer): konfigurierter Thumbnail-UsageType
 * → is_primary-Flag → erstes Bild nach sort_order. Wird von UpdateSearchIndex
 * (Aufbau von products_search_index.primary_image, genutzt von /Produkte und
 * /Preview) sowie von CatalogProductDetailResource (Galerie-Reihenfolge auf
 * /Preview/Produktdetail) verwendet, damit alle Ansichten dasselbe Bild als
 * "das" Titelbild behandeln.
 */
class PrimaryImageResolver
{
    /**
     * Dateiname des Titelbilds per DB-Query ermitteln (ohne geladene media-Relation).
     */
    public static function resolveFileName(string $productId): ?string
    {
        $thumbnailUsageTypeId = self::thumbnailUsageTypeId();

        if ($thumbnailUsageTypeId) {
            $image = ProductMediaAssignment::query()
                ->join('media', 'media.id', '=', 'product_media_assignments.media_id')
                ->where('product_media_assignments.product_id', $productId)
                ->where('product_media_assignments.usage_type_id', $thumbnailUsageTypeId)
                ->orderBy('product_media_assignments.sort_order')
                ->value('media.file_name');
            if ($image) {
                return $image;
            }
        }

        $image = ProductMediaAssignment::query()
            ->join('media', 'media.id', '=', 'product_media_assignments.media_id')
            ->where('product_media_assignments.product_id', $productId)
            ->where('product_media_assignments.is_primary', true)
            ->value('media.file_name');
        if ($image) {
            return $image;
        }

        return ProductMediaAssignment::query()
            ->join('media', 'media.id', '=', 'product_media_assignments.media_id')
            ->where('product_media_assignments.product_id', $productId)
            ->where('media.media_type', 'image')
            ->orderBy('product_media_assignments.sort_order')
            ->value('media.file_name');
    }

    /**
     * Titelbild aus einer bereits geladenen `media`-Relation (mit Pivot
     * usage_type_id/is_primary/sort_order) ermitteln, ohne zusätzliche Query.
     */
    public static function resolveFromCollection(Collection $media): ?Media
    {
        $thumbnailUsageTypeId = self::thumbnailUsageTypeId();

        if ($thumbnailUsageTypeId) {
            $match = $media->first(fn (Media $m) => $m->pivot->usage_type_id === $thumbnailUsageTypeId);
            if ($match) {
                return $match;
            }
        }

        $primary = $media->first(fn (Media $m) => (bool) $m->pivot->is_primary);
        if ($primary) {
            return $primary;
        }

        return $media
            ->filter(fn (Media $m) => $m->media_type === 'image')
            ->sortBy('pivot.sort_order')
            ->first();
    }

    private static function thumbnailUsageTypeId(): ?string
    {
        return WebsiteProfile::getActivePayload()['thumbnail_usage_type_id'] ?? null;
    }
}
