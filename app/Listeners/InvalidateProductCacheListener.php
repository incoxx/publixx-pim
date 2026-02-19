<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\RemoveFromSearchIndex;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * InvalidateProductCacheListener – Invalidiert den Redis-Cache für ein Produkt.
 *
 * Lauscht auf:
 * - ProductUpdated → Cache flush + Re-Index
 * - ProductDeleted → Cache flush + Index entfernen
 */
class InvalidateProductCacheListener implements ShouldQueue
{
    public string $queue = 'cache';

    /**
     * Event verarbeiten.
     */
    public function handle(object $event): void
    {
        $productId = $this->extractProductId($event);

        if (!$productId) {
            Log::warning('InvalidateProductCacheListener: Could not extract product_id', [
                'event_class' => get_class($event),
            ]);
            return;
        }

        try {
            Cache::tags(['product:' . $productId])->flush();
        } catch (\Throwable $e) {
            Log::warning('InvalidateProductCacheListener: Cache flush failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }

        // Bei ProductDeleted: auch aus Search-Index entfernen
        $eventClass = get_class($event);
        if (str_contains($eventClass, 'ProductDeleted')) {
            dispatch(new RemoveFromSearchIndex($productId));
        }

        Log::debug('InvalidateProductCacheListener: Cache flushed', [
            'product_id' => $productId,
            'event' => $eventClass,
        ]);
    }

    /**
     * Produkt-ID aus dem Event extrahieren.
     */
    private function extractProductId(object $event): ?string
    {
        if (isset($event->product) && is_object($event->product)) {
            return $event->product->id;
        }

        if (isset($event->productId)) {
            return $event->productId;
        }

        if (isset($event->product_id)) {
            return $event->product_id;
        }

        return null;
    }
}
