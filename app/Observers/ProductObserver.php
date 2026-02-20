<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\RemoveFromSearchIndex;
use App\Jobs\UpdateSearchIndex;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ProductObserver – reagiert auf CRUD-Operationen am Product-Model.
 *
 * Verantwortlich für:
 * - Redis-Cache-Invalidierung (product:{id}:* Tags)
 * - Async-Aktualisierung des denormalisierten products_search_index
 * - Kaskaden-Invalidierung bei Varianten
 */
class ProductObserver
{
    /**
     * Produkt wurde erstellt.
     * → Search-Index befüllen (async).
     */
    public function created(Product $product): void
    {
        // UpdateSearchIndex is dispatched via ProductCreated event → UpdateSearchIndexListener
        Log::debug('ProductObserver::created', ['product_id' => $product->id]);
    }

    /**
     * Produkt wurde aktualisiert.
     * → Cache invalidieren + Search-Index aktualisieren.
     * → Varianten-Cache ebenfalls invalidieren (Vererbungs-Kaskade).
     */
    public function updated(Product $product): void
    {
        // Eigenen Cache invalidieren
        $this->invalidateProductCache($product->id);

        // UpdateSearchIndex is dispatched via ProductUpdated event → UpdateSearchIndexListener

        // Varianten-Kaskade: Alle Kinder ebenfalls invalidieren
        $this->invalidateVariants($product);

        Log::debug('ProductObserver::updated', [
            'product_id' => $product->id,
            'dirty' => $product->getDirty(),
        ]);
    }

    /**
     * Produkt wird gelöscht.
     * → Cache invalidieren + aus Search-Index entfernen.
     */
    public function deleted(Product $product): void
    {
        $this->invalidateProductCache($product->id);

        try {
            dispatch(new RemoveFromSearchIndex($product->id))->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('ProductObserver: Failed to dispatch RemoveFromSearchIndex', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Varianten-Cache ebenfalls invalidieren
        $this->invalidateVariants($product);

        Log::debug('ProductObserver::deleted', ['product_id' => $product->id]);
    }

    /**
     * Cache für ein einzelnes Produkt invalidieren.
     * Nutzt Cache-Tags für selektives Flushing.
     */
    private function invalidateProductCache(string $productId): void
    {
        try {
            Cache::tags(['product:' . $productId])->flush();
        } catch (\Throwable $e) {
            // Redis nicht erreichbar → Log, aber kein Abbruch
            Log::warning('ProductObserver: Cache flush failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Varianten-Kaskade: Invalidiert Cache und Search-Index
     * aller Varianten (product_type_ref = 'variant', parent_product_id = $product->id).
     */
    private function invalidateVariants(Product $product): void
    {
        $variantIds = Product::where('parent_product_id', $product->id)->pluck('id');

        foreach ($variantIds as $variantId) {
            $this->invalidateProductCache($variantId);
            try {
                dispatch(new UpdateSearchIndex($variantId))->afterCommit();
            } catch (\Throwable $e) {
                Log::warning('ProductObserver: Failed to dispatch UpdateSearchIndex for variant', [
                    'variant_id' => $variantId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
