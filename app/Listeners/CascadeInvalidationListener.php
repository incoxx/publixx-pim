<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\UpdateSearchIndex;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CascadeInvalidationListener – Reagiert auf AttributeValuesChanged Events.
 *
 * Verantwortlich für:
 * - Produkt-Cache invalidieren
 * - Search-Index aktualisieren
 * - Varianten-Kaskade: Wenn ein Hauptprodukt geändert wird,
 *   müssen alle Varianten ebenfalls invalidiert werden.
 *
 * Event-Payload: $event->productId, $event->attributeIds
 */
class CascadeInvalidationListener implements ShouldQueue
{
    public string $queue = 'cache';

    /**
     * Event verarbeiten.
     */
    public function handle(object $event): void
    {
        $productId = $event->productId ?? $event->product_id ?? null;
        $attributeIds = $event->attributeIds ?? $event->attribute_ids ?? [];

        if (!$productId) {
            Log::warning('CascadeInvalidationListener: No product_id in event', [
                'event_class' => get_class($event),
            ]);
            return;
        }

        // 1. Produkt-Cache invalidieren
        $this->invalidateProductCache($productId);

        // 2. Search-Index aktualisieren
        dispatch(new UpdateSearchIndex($productId));

        // 3. Varianten-Kaskade
        $variantIds = Product::where('parent_product_id', $productId)->pluck('id');

        foreach ($variantIds as $variantId) {
            $this->invalidateProductCache($variantId);
            dispatch(new UpdateSearchIndex($variantId));
        }

        Log::info('CascadeInvalidationListener: Processed', [
            'product_id' => $productId,
            'attribute_ids' => $attributeIds,
            'variants_affected' => $variantIds->count(),
        ]);
    }

    /**
     * Cache für ein Produkt invalidieren.
     */
    private function invalidateProductCache(string $productId): void
    {
        try {
            Cache::tags(['product:' . $productId])->flush();
        } catch (\Throwable $e) {
            Log::warning('CascadeInvalidationListener: Cache flush failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
