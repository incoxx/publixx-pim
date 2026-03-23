<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\WebsiteProfile;
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
 * Strategie: Temporäre Tabelle mit Produkt-IDs → JOINs statt WHERE IN.
 * Alle Zusatzdaten werden per Chunk geladen und per Upsert geschrieben.
 */
class BulkReindexSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 min max
    public int $tries = 3;
    public array $backoff = [10, 30];

    private const CACHE_KEY = 'search_reindex_progress';
    private const CANCEL_KEY = 'search_reindex_cancel';
    private const CHUNK_SIZE = 500;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    public function handle(): void
    {
        // Abbruch-Flag zurücksetzen
        Cache::forget(self::CANCEL_KEY);

        $total = Product::where('status', 'active')
            ->where('product_type_ref', 'product')
            ->count();

        $this->updateProgress('running', 0, $total);

        $hasVariantColumns = Schema::hasColumn('products_search_index', 'product_type_ref');
        $themePayload = WebsiteProfile::getActivePayload();
        $thumbnailUsageTypeId = $themePayload['thumbnail_usage_type_id'] ?? null;

        // Vorberechnungen (einmalig)
        $mandatoryCount = DB::table('attributes')
            ->where('is_mandatory', true)
            ->where('status', 'active')
            ->count();

        // Searchable-Attribute-IDs vorher laden (einmalig)
        $searchableAttrIds = DB::table('attributes')
            ->where('is_searchable', true)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        // Mandatory-Attribute-IDs vorher laden (einmalig)
        $mandatoryAttrIds = $mandatoryCount > 0
            ? DB::table('attributes')
                ->where('is_mandatory', true)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray()
            : [];

        // productName + description Attribute-IDs vorher laden
        $nameAttrId = DB::table('attributes')
            ->where('technical_name', 'productName')
            ->value('id');
        $descAttrId = DB::table('attributes')
            ->where('technical_name', 'description')
            ->value('id');

        // list_price PriceType-ID vorher laden
        $listPriceTypeId = DB::table('price_types')
            ->where('technical_name', 'list_price')
            ->value('id');

        $processed = 0;
        $startTime = microtime(true);

        Product::where('status', 'active')
            ->where('product_type_ref', 'product')
            ->select('id', 'sku', 'ean', 'status', 'product_type_id', 'product_type_ref',
                     'parent_product_id', 'master_hierarchy_node_id')
            ->with('productType:id,technical_name')
            ->chunkById(self::CHUNK_SIZE, function ($products) use (
                &$processed, $total, $hasVariantColumns, $thumbnailUsageTypeId,
                $mandatoryCount, $searchableAttrIds, $mandatoryAttrIds,
                $nameAttrId, $descAttrId, $listPriceTypeId, $startTime,
            ) {
                // Abbruch prüfen
                if (Cache::get(self::CANCEL_KEY)) {
                    $this->updateProgress('cancelled', $processed, $total);
                    Log::info("BulkReindexSearchJob: Abgebrochen bei {$processed}/{$total} Produkten.");
                    return false; // chunkById stoppt
                }

                $productIds = $products->pluck('id')->toArray();

                // Alle Daten laden (optimiert mit vorberechneten IDs)
                $names = $nameAttrId ? $this->batchLoadNames($productIds, $nameAttrId) : [];
                $descriptions = $descAttrId ? $this->batchLoadDescriptions($productIds, $descAttrId) : [];
                $searchableTexts = !empty($searchableAttrIds) ? $this->batchLoadSearchableTexts($productIds, $searchableAttrIds) : [];
                $mediaTexts = $this->batchLoadMediaTexts($productIds);
                $hierarchyPaths = $this->batchLoadHierarchyPaths($products);
                $primaryImages = $this->batchLoadPrimaryImages($productIds, $thumbnailUsageTypeId);
                $listPrices = $listPriceTypeId ? $this->batchLoadListPrices($productIds, $listPriceTypeId) : [];
                $completeness = !empty($mandatoryAttrIds)
                    ? $this->batchLoadCompleteness($productIds, $mandatoryAttrIds, $mandatoryCount)
                    : [];

                $now = now();
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
                        'updated_at' => $now,
                    ];

                    if ($hasVariantColumns) {
                        $row['product_type_ref'] = $product->product_type_ref;
                        $row['parent_product_id'] = $product->parent_product_id;
                    }

                    $rows[] = $row;
                }

                // Bulk upsert (in Batches, um MySQL-Placeholder-Limit zu vermeiden)
                $updateColumns = array_keys($rows[0] ?? []);
                foreach (array_chunk($rows, 200) as $batch) {
                    DB::table('products_search_index')->upsert(
                        $batch,
                        ['product_id'],
                        $updateColumns,
                    );
                }

                $processed += count($products);

                // Fortschritt nur alle 2 Sekunden aktualisieren (weniger Cache-Writes)
                static $lastProgressUpdate = 0;
                $now = microtime(true);
                if ($now - $lastProgressUpdate > 2.0 || $processed >= $total) {
                    $elapsed = $now - $startTime;
                    $rate = $processed > 0 ? $elapsed / $processed : 0;
                    $remaining = ($total - $processed) * $rate;
                    $this->updateProgress('running', $processed, $total, null, (int) round($remaining));
                    $lastProgressUpdate = $now;
                }
            });

        // Abschluss (nur wenn nicht abgebrochen)
        $currentProgress = Cache::get(self::CACHE_KEY);
        if (($currentProgress['status'] ?? '') !== 'cancelled') {
            $elapsed = round(microtime(true) - $startTime, 1);
            $this->updateProgress('completed', $processed, $total);
            Log::info("BulkReindexSearchJob: Abgeschlossen – {$processed} Produkte in {$elapsed}s indiziert.");
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('failed', 0, 0, $exception->getMessage());
        Log::error('BulkReindexSearchJob failed: ' . $exception->getMessage());
    }

    private function updateProgress(string $status, int $processed, int $total, ?string $error = null, ?int $estimatedSeconds = null): void
    {
        Cache::put(self::CACHE_KEY, [
            'status' => $status,
            'processed' => $processed,
            'total' => $total,
            'percent' => $total > 0 ? round(($processed / $total) * 100, 1) : 0,
            'error' => $error,
            'estimated_seconds' => $estimatedSeconds,
            'updated_at' => now()->toIso8601String(),
        ], 600); // 10 min TTL
    }

    // ── Batch loaders (optimiert mit vorberechneten IDs) ──────────

    /**
     * productName laden — direkt per attribute_id statt JOIN.
     */
    private function batchLoadNames(array $productIds, string $nameAttrId): array
    {
        $rows = DB::table('product_attribute_values')
            ->where('attribute_id', $nameAttrId)
            ->where('multiplied_index', 0)
            ->whereIn('product_id', $productIds)
            ->whereNotNull('value_string')
            ->select('product_id', 'language', 'value_string')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $lang = $row->language ?? 'de';
            if (!isset($result[$row->product_id][$lang])) {
                $result[$row->product_id][$lang] = $row->value_string;
            }
        }
        return $result;
    }

    private function batchLoadDescriptions(array $productIds, string $descAttrId): array
    {
        return DB::table('product_attribute_values')
            ->where('attribute_id', $descAttrId)
            ->where('multiplied_index', 0)
            ->whereIn('product_id', $productIds)
            ->where(function ($q) {
                $q->where('language', 'de')->orWhereNull('language');
            })
            ->whereNotNull('value_string')
            ->pluck('value_string', 'product_id')
            ->toArray();
    }

    /**
     * Searchable-Texte: Alle Typen in einer Query (UNION-artig via OR).
     */
    private function batchLoadSearchableTexts(array $productIds, array $searchableAttrIds): array
    {
        $rows = DB::table('product_attribute_values as pav')
            ->leftJoin('value_list_entries as vle', 'vle.id', '=', 'pav.value_selection_id')
            ->whereIn('pav.product_id', $productIds)
            ->whereIn('pav.attribute_id', $searchableAttrIds)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('pav.value_string')->where('pav.value_string', '!=', '');
                })->orWhereNotNull('pav.value_number')
                  ->orWhereNotNull('pav.value_selection_id');
            })
            ->select(
                'pav.product_id',
                'pav.value_string',
                'pav.value_number',
                'vle.display_value_de',
                'vle.display_value_en',
            )
            ->get();

        $texts = [];
        foreach ($rows as $row) {
            $pid = $row->product_id;
            if ($row->value_string !== null && $row->value_string !== '') {
                $clean = strip_tags((string) $row->value_string);
                if ($clean !== '') {
                    $texts[$pid][] = $clean;
                }
            }
            if ($row->value_number !== null) {
                $texts[$pid][] = (string) $row->value_number;
            }
            if ($row->display_value_de) {
                $texts[$pid][] = $row->display_value_de;
            }
            if ($row->display_value_en && $row->display_value_en !== $row->display_value_de) {
                $texts[$pid][] = $row->display_value_en;
            }
        }

        return array_map(fn ($parts) => implode(' | ', array_unique($parts)), $texts);
    }

    private function batchLoadMediaTexts(array $productIds): array
    {
        $rows = DB::table('product_media_assignments as pma')
            ->join('media', 'media.id', '=', 'pma.media_id')
            ->whereIn('pma.product_id', $productIds)
            ->where(function ($q) {
                $q->where('media.media_type', 'document')
                  ->orWhere('media.mime_type', 'like', 'application/pdf%');
            })
            ->select('pma.product_id', 'media.file_name', 'media.title_de', 'media.title_en', 'media.description_de', 'media.description_en')
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
            $rows = DB::table('product_media_assignments as pma')
                ->join('media', 'media.id', '=', 'pma.media_id')
                ->whereIn('pma.product_id', $productIds)
                ->where('pma.usage_type_id', $thumbnailUsageTypeId)
                ->orderBy('pma.sort_order')
                ->select('pma.product_id', 'media.file_name')
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
        $rows = DB::table('product_media_assignments as pma')
            ->join('media', 'media.id', '=', 'pma.media_id')
            ->whereIn('pma.product_id', $remaining)
            ->where('pma.is_primary', true)
            ->select('pma.product_id', 'media.file_name')
            ->get();

        foreach ($rows as $row) {
            if (!isset($result[$row->product_id])) {
                $result[$row->product_id] = $row->file_name;
            }
        }

        $remaining = array_diff($productIds, array_keys($result));
        if (empty($remaining)) return $result;

        // Priority 3: first image by sort_order
        $rows = DB::table('product_media_assignments as pma')
            ->join('media', 'media.id', '=', 'pma.media_id')
            ->whereIn('pma.product_id', $remaining)
            ->where('media.media_type', 'image')
            ->orderBy('pma.sort_order')
            ->select('pma.product_id', 'media.file_name')
            ->get();

        foreach ($rows as $row) {
            if (!isset($result[$row->product_id])) {
                $result[$row->product_id] = $row->file_name;
            }
        }

        return $result;
    }

    private function batchLoadListPrices(array $productIds, string $listPriceTypeId): array
    {
        $rows = DB::table('product_prices')
            ->whereIn('product_id', $productIds)
            ->where('price_type_id', $listPriceTypeId)
            ->where(function ($q) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>=', now()->toDateString());
            })
            ->orderBy('valid_from', 'desc')
            ->select('product_id', 'amount')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->product_id])) {
                $result[$row->product_id] = (float) $row->amount;
            }
        }
        return $result;
    }

    private function batchLoadCompleteness(array $productIds, array $mandatoryAttrIds, int $mandatoryCount): array
    {
        $counts = DB::table('product_attribute_values')
            ->whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $mandatoryAttrIds)
            ->where('multiplied_index', 0)
            ->where(function ($q) {
                $q->whereNotNull('value_string')
                  ->orWhereNotNull('value_number')
                  ->orWhereNotNull('value_date')
                  ->orWhereNotNull('value_flag')
                  ->orWhereNotNull('value_selection_id');
            })
            ->groupBy('product_id')
            ->select('product_id', DB::raw('COUNT(DISTINCT attribute_id) as filled'))
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
