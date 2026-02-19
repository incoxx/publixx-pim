<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\UpdateSearchIndex;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AttributeValueObserver – reagiert auf Änderungen an Produkt-Attributwerten.
 *
 * Verantwortlich für:
 * - Produkt-Cache invalidieren (product:{id}:full, product:{id}:lang:{lang})
 * - Search-Index aktualisieren
 * - Vererbungs-Kaskade: Varianten-Cache invalidieren wenn Eltern-Wert sich ändert
 */
class AttributeValueObserver
{
    /**
     * Attributwert wurde gespeichert (created oder updated).
     * → Produkt-Cache invalidieren + Search-Index aktualisieren.
     * → Varianten-Kaskade.
     */
    public function saved(ProductAttributeValue $value): void
    {
        $this->handleChange($value);
    }

    /**
     * Attributwert wurde gelöscht.
     * → Gleiche Invalidierung wie bei saved.
     */
    public function deleted(ProductAttributeValue $value): void
    {
        $this->handleChange($value);
    }

    /**
     * Zentrale Change-Behandlung für saved/deleted.
     */
    private function handleChange(ProductAttributeValue $value): void
    {
        $productId = $value->product_id;

        // 1. Produkt-Cache invalidieren
        $this->invalidateProductCache($productId);

        // 2. Search-Index async aktualisieren
        dispatch(new UpdateSearchIndex($productId))->afterCommit();

        // 3. Varianten-Kaskade: Wenn das betroffene Produkt Varianten hat,
        //    müssen deren Caches auch invalidiert werden (Vererbung).
        $this->invalidateVariantCascade($productId);

        Log::debug('AttributeValueObserver::change', [
            'product_id' => $productId,
            'attribute_id' => $value->attribute_id,
            'language' => $value->language,
        ]);
    }

    /**
     * Cache für ein Produkt invalidieren (alle Tags).
     */
    private function invalidateProductCache(string $productId): void
    {
        try {
            Cache::tags(['product:' . $productId])->flush();
        } catch (\Throwable $e) {
            Log::warning('AttributeValueObserver: Cache flush failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Varianten-Kaskade: Invalidiert Cache + Search-Index
     * aller Varianten des betroffenen Produkts.
     *
     * Wenn Produkt A den Attributwert "Produktname" ändert und
     * Variante B diesen Wert erbt (inherit), muss B's Cache
     * ebenfalls invalidiert werden.
     */
    private function invalidateVariantCascade(string $productId): void
    {
        $variantIds = Product::where('parent_product_id', $productId)->pluck('id');

        foreach ($variantIds as $variantId) {
            $this->invalidateProductCache($variantId);
            dispatch(new UpdateSearchIndex($variantId))->afterCommit();
        }

        if ($variantIds->isNotEmpty()) {
            Log::debug('AttributeValueObserver: Variant cascade', [
                'parent_id' => $productId,
                'variant_count' => $variantIds->count(),
            ]);
        }
    }
}
