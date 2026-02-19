<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WarmupCache – Wärmt den Redis-Cache nach Massenoperationen auf.
 *
 * Wird dispatcht nach:
 * - Import (ImportCompleted Event)
 * - Manueller Aufruf via Artisan/Admin
 *
 * Strategie:
 * 1. Alle übergebenen Produkt-IDs in den Cache laden
 * 2. Die 100 meistgenutzten Produkte ebenfalls cachen
 * 3. Search-Index für alle betroffenen Produkte aktualisieren
 */
class WarmupCache implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 600; // 10 Minuten für große Imports

    public function __construct(
        public readonly array $productIds,
    ) {
        $this->onQueue('warmup');
    }

    /**
     * Cache aufwärmen.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $warmedCount = 0;

        // 1. Importierte Produkte cachen
        foreach (array_chunk($this->productIds, 50) as $chunk) {
            $products = Product::with([
                'productType',
                'attributeValues',
                'attributeValues.attribute',
                'mediaAssignments',
                'mediaAssignments.media',
                'prices',
                'prices.priceType',
            ])->whereIn('id', $chunk)->get();

            foreach ($products as $product) {
                $this->warmProduct($product);
                $warmedCount++;
            }
        }

        // 2. Die meistgenutzten Produkte ebenfalls cachen
        //    (basierend auf updated_at als Proxy für Aktivität)
        $topProductIds = Product::query()
            ->whereNotIn('id', $this->productIds)
            ->where('status', 'active')
            ->orderBy('updated_at', 'desc')
            ->limit(100)
            ->pluck('id');

        foreach ($topProductIds->chunk(50) as $chunk) {
            $products = Product::with([
                'productType',
                'attributeValues',
                'attributeValues.attribute',
                'mediaAssignments',
                'mediaAssignments.media',
                'prices',
                'prices.priceType',
            ])->whereIn('id', $chunk)->get();

            foreach ($products as $product) {
                $this->warmProduct($product);
                $warmedCount++;
            }
        }

        // 3. Search-Index für importierte Produkte aktualisieren
        foreach ($this->productIds as $productId) {
            dispatch(new UpdateSearchIndex($productId));
        }

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('WarmupCache: Complete', [
            'imported_count' => count($this->productIds),
            'total_warmed' => $warmedCount,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Ein einzelnes Produkt in den Cache laden.
     *
     * Cache-Key: product:{id}:full
     * TTL: 1 Stunde (3600 Sekunden)
     */
    private function warmProduct(Product $product): void
    {
        try {
            Cache::tags(['product:' . $product->id])->put(
                "product:{$product->id}:full",
                $product->toArray(),
                3600,
            );
        } catch (\Throwable $e) {
            Log::warning('WarmupCache: Failed to warm product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
