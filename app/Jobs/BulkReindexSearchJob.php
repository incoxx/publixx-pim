<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\Setting;
use App\Support\KoelnerPhonetik;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk reindex: processes all active products in efficient batch queries.
 *
 * Instead of N individual jobs with ~15 queries each, this job loads
 * all supplementary data in bulk per chunk, then upserts the index rows.
 * 100k products: ~5 min instead of ~60 min.
 */
class BulkReindexSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 min max
    public int $tries = 1;

    private const CACHE_KEY = 'search_reindex_progress';
    private const CHUNK_SIZE = 500;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    public function handle(): void
    {
        $total = Product::where('status', 'active')
            ->where('product_type_ref', 'product')
            ->count();

        $this->updateProgress('running', 0, $total);

        $hasVariantColumns = Schema::hasColumn('products_search_index', 'product_type_ref');
        $themePayload = Setting::getPayload('catalog_theme');
        $thumbnailUsageTypeId = $themePayload['thumbnail_usage_type_id'] ?? null;

        // Pre-load mandatory attribute count (same for all products)
        $mandatoryCount = DB::table('attributes')
            ->where('is_mandatory', true)
            ->where('status', 'active')
            ->count();

        $processed = 0;

        Product::where('status', 'active')
            ->where('product_type_ref', 'product')
            ->select('id', 'sku', 'ean', 'status', 'product_type_id', 'product_type_ref',
                     'parent_product_id', 'master_hierarchy_node_id')
            ->with('productType:id,technical_name')
            ->chunkById(self::CHUNK_SIZE, function ($products) use (
                &$processed, $total, $hasVariantColumns, $thumbnailUsageTypeId, $mandatoryCount,
            ) {
                $productIds = $products->pluck('id')->toArray();

                // Batch load all supplementary data
                $names = $this->batchLoadNames($productIds);
                $descriptions = $this->batchLoadDescriptions($productIds);
                $searchableTexts = $this->batchLoadSearchableTexts($productIds);
                $mediaTexts = $this->batchLoadMediaTexts($productIds);
                $hierarchyPaths = $this->batchLoadHierarchyPaths($products);
                $primaryImages = $this->batchLoadPrimaryImages($productIds, $thumbnailUsageTypeId);
                $listPrices = $this->batchLoadListPrices($productIds);
                $completeness = $mandatoryCount > 0
                    ? $this->batchLoadCompleteness($productIds, $mandatoryCount)
                    : [];

                $rows = [];
                foreach ($products as $product) {
                    $pid = $product->id;
                    $nameDe = $names[$pid]['de'] ?? null;
                    $nameEn = $names[$pid]['en'] ?? null;
                    $searchableText = $searchableTexts[$pid] ?? null;

                    $row = [
                        'product_id' => $pid,
                        'sku' => $product->sku,
                        'ean' => $product->ean,
                        'product_type' => $product->productType?->technical_name,
                        'status' => $product->status,
                        'name_de' => $nameDe ? mb_substr($nameDe, 0, 500) : null,
                        'name_en' => $nameEn ? mb_substr($nameEn, 0, 500) : null,
                        'description_de' => $descriptions[$pid] ?? null,
                        'hierarchy_path' => $hierarchyPaths[$pid] ?? null,
                        'primary_image' => $primaryImages[$pid] ?? null,
                        'list_price' => $listPrices[$pid] ?? null,
                        'attribute_completeness' => $completeness[$pid] ?? ($mandatoryCount === 0 ? 100 : 0),
                        'phonetic_name_de' => $nameDe
                            ? mb_substr(KoelnerPhonetik::encode($nameDe), 0, 100)
                            : null,
                        'searchable_text' => $searchableText ?: null,
                        'media_text' => $mediaTexts[$pid] ?? null,
                        'phonetic_text' => $searchableText
                            ? mb_substr(KoelnerPhonetik::encode($searchableText), 0, 5000)
                            : null,
                        'updated_at' => now(),
                    ];

                    if ($hasVariantColumns) {
                        $row['product_type_ref'] = $product->product_type_ref;
                        $row['parent_product_id'] = $product->parent_product_id;
                    }

                    $rows[] = $row;
                }

                // Bulk upsert
                DB::table('products_search_index')->upsert(
                    $rows,
                    ['product_id'],
                    array_keys($rows[0] ?? []),
                );

                $processed += count($products);
                $this->updateProgress('running', $processed, $total);
            });

        $this->updateProgress('completed', $processed, $total);

        Log::info("BulkReindexSearchJob: Completed – {$processed} products indexed.");
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('failed', 0, 0, $exception->getMessage());
        Log::error('BulkReindexSearchJob failed: ' . $exception->getMessage());
    }

    private function updateProgress(string $status, int $processed, int $total, ?string $error = null): void
    {
        Cache::put(self::CACHE_KEY, [
            'status' => $status,
            'processed' => $processed,
            'total' => $total,
            'percent' => $total > 0 ? round(($processed / $total) * 100, 1) : 0,
            'error' => $error,
            'updated_at' => now()->toIso8601String(),
        ], 600); // 10 min TTL
    }

    // ── Batch loaders ──────────────────────────────────

    /**
     * Load productName attribute for all products in chunk.
     * Returns [ product_id => ['de' => '...', 'en' => '...'] ]
     */
    private function batchLoadNames(array $productIds): array
    {
        $rows = ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.technical_name', 'productName')
            ->where('product_attribute_values.multiplied_index', 0)
            ->whereIn('product_attribute_values.product_id', $productIds)
            ->whereNotNull('product_attribute_values.value_string')
            ->select(
                'product_attribute_values.product_id',
                'product_attribute_values.language',
                'product_attribute_values.value_string',
            )
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $lang = $row->language ?? 'de';
            // Only set if not already set for this lang (respect priority)
            if (!isset($result[$row->product_id][$lang])) {
                $result[$row->product_id][$lang] = $row->value_string;
            }
        }
        return $result;
    }

    private function batchLoadDescriptions(array $productIds): array
    {
        return ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.technical_name', 'description')
            ->where('product_attribute_values.multiplied_index', 0)
            ->whereIn('product_attribute_values.product_id', $productIds)
            ->whereIn('product_attribute_values.language', ['de', null])
            ->whereNotNull('product_attribute_values.value_string')
            ->pluck('product_attribute_values.value_string', 'product_attribute_values.product_id')
            ->toArray();
    }

    private function batchLoadSearchableTexts(array $productIds): array
    {
        // String values
        $stringRows = ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->whereIn('product_attribute_values.product_id', $productIds)
            ->where('attributes.is_searchable', true)
            ->whereNotNull('product_attribute_values.value_string')
            ->where('product_attribute_values.value_string', '!=', '')
            ->select('product_attribute_values.product_id', 'product_attribute_values.value_string')
            ->get();

        // Number values
        $numberRows = ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->whereIn('product_attribute_values.product_id', $productIds)
            ->where('attributes.is_searchable', true)
            ->whereNotNull('product_attribute_values.value_number')
            ->select('product_attribute_values.product_id', 'product_attribute_values.value_number')
            ->get();

        // Selection values
        $selectionRows = ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->join('value_list_entries', 'value_list_entries.id', '=', 'product_attribute_values.value_selection_id')
            ->whereIn('product_attribute_values.product_id', $productIds)
            ->where('attributes.is_searchable', true)
            ->whereNotNull('product_attribute_values.value_selection_id')
            ->select(
                'product_attribute_values.product_id',
                'value_list_entries.display_value_de',
                'value_list_entries.display_value_en',
            )
            ->get();

        $texts = [];
        foreach ($stringRows as $row) {
            $clean = strip_tags((string) $row->value_string);
            if ($clean !== '') {
                $texts[$row->product_id][] = $clean;
            }
        }
        foreach ($numberRows as $row) {
            $texts[$row->product_id][] = (string) $row->value_number;
        }
        foreach ($selectionRows as $row) {
            if ($row->display_value_de) {
                $texts[$row->product_id][] = $row->display_value_de;
            }
            if ($row->display_value_en && $row->display_value_en !== $row->display_value_de) {
                $texts[$row->product_id][] = $row->display_value_en;
            }
        }

        return array_map(fn ($parts) => implode(' | ', $parts), $texts);
    }

    private function batchLoadMediaTexts(array $productIds): array
    {
        $rows = ProductMediaAssignment::query()
            ->join('media', 'media.id', '=', 'product_media_assignments.media_id')
            ->whereIn('product_media_assignments.product_id', $productIds)
            ->where(function ($q) {
                $q->where('media.media_type', 'document')
                  ->orWhere('media.mime_type', 'like', 'application/pdf%');
            })
            ->select(
                'product_media_assignments.product_id',
                'media.file_name',
                'media.title_de',
                'media.title_en',
                'media.description_de',
                'media.description_en',
            )
            ->get();

        $texts = [];
        foreach ($rows as $m) {
            $pid = $m->product_id;
            if ($m->file_name) {
                $texts[$pid][] = pathinfo($m->file_name, PATHINFO_FILENAME);
            }
            if ($m->title_de) $texts[$pid][] = $m->title_de;
            if ($m->title_en && $m->title_en !== $m->title_de) $texts[$pid][] = $m->title_en;
            if ($m->description_de) $texts[$pid][] = $m->description_de;
            if ($m->description_en && $m->description_en !== $m->description_de) $texts[$pid][] = $m->description_en;
        }

        return array_map(fn ($parts) => implode(' | ', $parts), $texts);
    }

    private function batchLoadHierarchyPaths($products): array
    {
        $nodeIds = $products->pluck('master_hierarchy_node_id')->filter()->unique()->toArray();
        if (empty($nodeIds)) return [];

        $paths = DB::table('hierarchy_nodes')
            ->whereIn('id', $nodeIds)
            ->pluck('path', 'id')
            ->toArray();

        $result = [];
        foreach ($products as $p) {
            if ($p->master_hierarchy_node_id && isset($paths[$p->master_hierarchy_node_id])) {
                $result[$p->id] = $paths[$p->master_hierarchy_node_id];
            }
        }
        return $result;
    }

    private function batchLoadPrimaryImages(array $productIds, ?string $thumbnailUsageTypeId): array
    {
        $result = [];

        // Priority 1: thumbnail usage type
        if ($thumbnailUsageTypeId) {
            $rows = ProductMediaAssignment::query()
                ->join('media', 'media.id', '=', 'product_media_assignments.media_id')
                ->whereIn('product_media_assignments.product_id', $productIds)
                ->where('product_media_assignments.usage_type_id', $thumbnailUsageTypeId)
                ->orderBy('product_media_assignments.sort_order')
                ->select('product_media_assignments.product_id', 'media.file_name')
                ->get();

            foreach ($rows as $row) {
                if (!isset($result[$row->product_id])) {
                    $result[$row->product_id] = $row->file_name;
                }
            }
        }

        $remaining = array_diff($productIds, array_keys($result));
        if (empty($remaining)) return $result;

        // Priority 2: is_primary flag
        $rows = ProductMediaAssignment::query()
            ->join('media', 'media.id', '=', 'product_media_assignments.media_id')
            ->whereIn('product_media_assignments.product_id', $remaining)
            ->where('product_media_assignments.is_primary', true)
            ->select('product_media_assignments.product_id', 'media.file_name')
            ->get();

        foreach ($rows as $row) {
            if (!isset($result[$row->product_id])) {
                $result[$row->product_id] = $row->file_name;
            }
        }

        $remaining = array_diff($productIds, array_keys($result));
        if (empty($remaining)) return $result;

        // Priority 3: first image by sort_order
        $rows = ProductMediaAssignment::query()
            ->join('media', 'media.id', '=', 'product_media_assignments.media_id')
            ->whereIn('product_media_assignments.product_id', $remaining)
            ->where('media.media_type', 'image')
            ->orderBy('product_media_assignments.sort_order')
            ->select('product_media_assignments.product_id', 'media.file_name')
            ->get();

        foreach ($rows as $row) {
            if (!isset($result[$row->product_id])) {
                $result[$row->product_id] = $row->file_name;
            }
        }

        return $result;
    }

    private function batchLoadListPrices(array $productIds): array
    {
        $rows = ProductPrice::query()
            ->join('price_types', 'price_types.id', '=', 'product_prices.price_type_id')
            ->whereIn('product_prices.product_id', $productIds)
            ->where('price_types.technical_name', 'list_price')
            ->where(function ($q) {
                $q->whereNull('product_prices.valid_to')
                  ->orWhere('product_prices.valid_to', '>=', now()->toDateString());
            })
            ->orderBy('product_prices.valid_from', 'desc')
            ->select('product_prices.product_id', 'product_prices.amount')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->product_id])) {
                $result[$row->product_id] = (float) $row->amount;
            }
        }
        return $result;
    }

    private function batchLoadCompleteness(array $productIds, int $mandatoryCount): array
    {
        $counts = ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->whereIn('product_attribute_values.product_id', $productIds)
            ->where('attributes.is_mandatory', true)
            ->where('product_attribute_values.multiplied_index', 0)
            ->where(function ($q) {
                $q->whereNotNull('product_attribute_values.value_string')
                  ->orWhereNotNull('product_attribute_values.value_number')
                  ->orWhereNotNull('product_attribute_values.value_date')
                  ->orWhereNotNull('product_attribute_values.value_flag')
                  ->orWhereNotNull('product_attribute_values.value_selection_id');
            })
            ->groupBy('product_attribute_values.product_id')
            ->select(
                'product_attribute_values.product_id',
                DB::raw('COUNT(DISTINCT product_attribute_values.attribute_id) as filled'),
            )
            ->pluck('filled', 'product_id')
            ->toArray();

        $result = [];
        foreach ($productIds as $pid) {
            $filled = $counts[$pid] ?? 0;
            $result[$pid] = (int) min(100, round(($filled / $mandatoryCount) * 100));
        }
        return $result;
    }
}
