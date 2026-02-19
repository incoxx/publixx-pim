<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\UpdateSearchIndex;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * UpdateSearchIndexListener – Lauscht auf ProductCreated und ProductUpdated Events.
 *
 * Dispatcht den UpdateSearchIndex-Job, damit der denormalisierte
 * Search-Index asynchron aktualisiert wird.
 *
 * Events von Agent 4 (Vererbung) und Agent 6 (Import) lösen
 * diese Listener ebenfalls aus.
 */
class UpdateSearchIndexListener implements ShouldQueue
{
    public string $queue = 'indexing';

    /**
     * Event verarbeiten.
     *
     * Akzeptiert ProductCreated und ProductUpdated Events.
     * Beide Events müssen ein $product Attribut oder eine product_id Eigenschaft haben.
     */
    public function handle(object $event): void
    {
        $productId = $this->extractProductId($event);

        if (!$productId) {
            Log::warning('UpdateSearchIndexListener: Could not extract product_id from event', [
                'event_class' => get_class($event),
            ]);
            return;
        }

        dispatch(new UpdateSearchIndex($productId));

        Log::debug('UpdateSearchIndexListener: Dispatched UpdateSearchIndex', [
            'product_id' => $productId,
            'event' => get_class($event),
        ]);
    }

    /**
     * Produkt-ID aus dem Event extrahieren.
     *
     * Unterstützt verschiedene Event-Strukturen:
     * - $event->product (Eloquent Model)
     * - $event->productId (String)
     * - $event->product_id (String)
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
