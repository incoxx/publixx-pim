<?php

declare(strict_types=1);

namespace App\Observers\Content;

use App\Models\Product;
use App\Models\ProductRelation;
use App\Services\Content\ContentCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidiert produktbezogenen Content-Cache (Produkt-Detailseite + alle
 * einbettenden Content-Seiten über den Reverse-Index), wenn sich Produktdaten
 * ändern. Registriert auf Product, ProductPrice, ProductAttributeValue,
 * ProductMediaAssignment, ProductRelation (siehe EventServiceProvider).
 */
class ContentProductCacheObserver
{
    public function __construct(private readonly ContentCache $cache) {}

    public function saved(Model $model): void
    {
        $this->flush($model);
    }

    public function deleted(Model $model): void
    {
        $this->flush($model);
    }

    private function flush(Model $model): void
    {
        $productId = match (true) {
            $model instanceof Product => $model->id,
            $model instanceof ProductRelation => $model->source_product_id,
            default => $model->getAttribute('product_id'),
        };

        if (is_string($productId) && $productId !== '') {
            $this->cache->flushProduct($productId);
        }
    }
}
