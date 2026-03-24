<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\CatalogTemplate;
use App\Models\DictionaryEntry;
use App\Services\CompositeFormatResolver;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductSearchIndex;
use App\Models\Setting;
use App\Models\WebsiteProfile;
use App\Models\ValueListEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Generiert einen kompletten Offline-Katalog als ZIP-Datei.
 *
 * Der Export enthält:
 * - products/index.json (Chunk-Index)
 * - products/chunk-0.json, chunk-1.json, ... (Produktlisten in 500er Chunks)
 * - products-detail/{bucket}/{id}.json (Detail-JSON pro Produkt, max 1000 pro Unterordner)
 * - categories.json (Hierarchie-Baum)
 * - facets.json (Filter-Konfiguration)
 * - settings.json (Katalog-Theme-Einstellungen)
 *
 * Features:
 * - Chunked: Verarbeitet Produkte in 500er Batches
 * - Cancellable: Prüft zwischen Chunks auf Abbruch (Cache-Flag)
 * - Progress: Schreibt Fortschritt in Cache (pollbar via API)
 */
class OfflineCatalogExportService
{
    private const CHUNK_SIZE = 500;
    private const DETAIL_DIR_SIZE = 1000; // max JSON files per detail subdirectory
    private const CACHE_KEY_PROGRESS = 'offline_catalog_export:progress';
    private const CACHE_KEY_CANCEL = 'offline_catalog_export:cancel';
    private const CACHE_TTL = 1800; // 30 min

    /**
     * Starte den Export. Gibt Pfad zur ZIP-Datei zurück.
     *
     * @return array{path: string, total_products: int, chunks: int, cancelled: bool}
     */
    public function generate(?string $lang = null, ?string $templateId = null, ?string $websiteProfileId = null): array
    {
        $lang = $lang ?? 'de';
        $startTime = microtime(true);

        // Cancel-Flag zurücksetzen, Fortschritt initialisieren
        Cache::forget(self::CACHE_KEY_CANCEL);
        $this->updateProgress('Initialisiere...', 0, 0, 'initializing');

        // Use specified profile or fall back to the active one
        if ($websiteProfileId) {
            $themePayload = WebsiteProfile::find($websiteProfileId)?->payload ?? WebsiteProfile::getActivePayload();
        } else {
            $themePayload = WebsiteProfile::getActivePayload();
        }

        // Temporäres Verzeichnis
        $tmpDir = storage_path('app/tmp/offline-catalog-' . uniqid());
        $productsDir = $tmpDir . '/products';
        $detailDir = $tmpDir . '/products-detail';
        if (!mkdir($productsDir, 0755, true)) {
            throw new \RuntimeException("Verzeichnis konnte nicht erstellt werden: {$productsDir}");
        }
        if (!mkdir($detailDir, 0755, true)) {
            throw new \RuntimeException("Verzeichnis konnte nicht erstellt werden: {$detailDir}");
        }

        try {
            // 1. Build hierarchy query — only products assigned to visible hierarchy nodes
            $baseQuery = $this->buildProductQuery($themePayload);
            $hierarchyId = $themePayload['hierarchy_id'] ?? null;
            $hierarchy = $hierarchyId ? Hierarchy::find($hierarchyId) : null;
            $excludedNodeIds = $themePayload['catalog_excluded_node_ids'] ?? [];

            $hierarchyQuery = clone $baseQuery;
            if ($hierarchy) {
                $this->applyHierarchyFilter($hierarchyQuery, $hierarchy, $excludedNodeIds);
            } else {
                $hierarchyQuery->whereNotNull('master_hierarchy_node_id');
            }

            $hierarchyCount = $hierarchyQuery->count();

            Log::channel('export')->info('Offline-Katalog: Produkt-Aufteilung', [
                'hierarchy' => $hierarchyCount,
            ]);

            $this->updateProgress('Produkte werden exportiert...', 0, $hierarchyCount, 'exporting');

            if ($hierarchyCount === 0) {
                $this->updateProgress('Keine aktiven Produkte gefunden.', 0, 0, 'completed');
                $this->cleanup($tmpDir);
                return ['path' => null, 'total_products' => 0, 'chunks' => 0, 'cancelled' => false];
            }

            // 2a. Export hierarchy products (primary index — browse catalog)
            $chunkFiles = [];
            $offset = 0;
            $chunkIndex = 0;
            $detailCounter = 0; // Global counter for detail subdirectory bucketing
            $cancelled = false;
            $exportedProductIds = []; // Track which products already have a detail JSON

            while ($offset < $hierarchyCount) {
                if ($this->isCancelled()) {
                    $cancelled = true;
                    Log::channel('export')->info('Offline-Katalog-Export abgebrochen', [
                        'exported' => $offset,
                        'total' => $hierarchyCount,
                    ]);
                    break;
                }

                $products = $this->loadProductChunk($themePayload, $lang, $offset, self::CHUNK_SIZE);

                $chunkFileName = "chunk-{$chunkIndex}.json";
                $listData = [];
                foreach ($products as $product) {
                    $bucket = intdiv($detailCounter, self::DETAIL_DIR_SIZE);
                    $item = $this->buildListItem($product, $themePayload, $lang, $themePayload['catalog_excluded_node_ids'] ?? []);
                    $item['_dd'] = $bucket;
                    $listData[] = $item;

                    $subDir = "{$detailDir}/{$bucket}";
                    if (!is_dir($subDir) && !mkdir($subDir, 0755, true)) {
                        throw new \RuntimeException("Verzeichnis konnte nicht erstellt werden: {$subDir}");
                    }
                    $detailData = $this->buildDetailItem($product, $themePayload, $lang);
                    $this->writeJsonFile("{$subDir}/{$product->id}.json", $detailData);

                    $exportedProductIds[] = $product->id;
                    $detailCounter++;
                }
                $this->writeJsonFile("{$productsDir}/{$chunkFileName}", $listData);
                $chunkFiles[] = $chunkFileName;

                $offset += self::CHUNK_SIZE;
                $chunkIndex++;
                $this->updateProgress(
                    'Hierarchie-Produkte werden exportiert...',
                    min($offset, $hierarchyCount),
                    $hierarchyCount,
                    'exporting'
                );
            }

            // 2b. Export detail JSONs for ALL remaining active products
            //     Hierarchy products already have detail JSONs from phase 2a.
            //     This phase covers everything else: relation targets, spare parts,
            //     and any other active products reachable via search.
            $relationDetailMap = [];
            if (!$cancelled) {
                // Count remaining products (all active minus already exported)
                $remainingQuery = $this->buildBaseProductQuery()
                    ->whereNotIn('id', $exportedProductIds);
                $remainingCount = $remainingQuery->count();

                if ($remainingCount > 0) {
                    Log::channel('export')->info('Offline-Katalog: Detail-Export für restliche Produkte', [
                        'remaining' => $remainingCount,
                    ]);

                    $this->updateProgress(
                        'Detail-Dateien für weitere Produkte...',
                        $hierarchyCount,
                        $hierarchyCount + $remainingCount,
                        'exporting'
                    );

                    $remainingOffset = 0;
                    $relDetailCount = 0;

                    while ($remainingOffset < $remainingCount) {
                        if ($this->isCancelled()) {
                            $cancelled = true;
                            break;
                        }

                        $products = $this->buildBaseProductQuery()
                            ->whereNotIn('id', $exportedProductIds)
                            ->with([
                                'searchIndex',
                                'masterHierarchyNode',
                                'attributeValues' => fn ($q) => $q->where(function ($q2) use ($lang) {
                                    $q2->whereNull('language')->orWhere('language', $lang);
                                }),
                                'attributeValues.attribute',
                                'attributeValues.valueListEntry',
                                'attributeValues.dictionaryEntry',
                                'attributeValues.unit',
                                'prices' => fn ($q) => $q->where(function ($q2) {
                                    $q2->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString());
                                })->orderBy('amount'),
                                'media',
                                'variants',
                                'outgoingRelations.relationType',
                                'outgoingRelations.targetProduct',
                            ])
                            ->orderBy('id')
                            ->skip($remainingOffset)
                            ->take(self::CHUNK_SIZE)
                            ->get();

                        foreach ($products as $product) {
                            $bucket = intdiv($detailCounter, self::DETAIL_DIR_SIZE);
                            $subDir = "{$detailDir}/{$bucket}";
                            if (!is_dir($subDir) && !mkdir($subDir, 0755, true)) {
                                throw new \RuntimeException("Verzeichnis konnte nicht erstellt werden: {$subDir}");
                            }
                            $detailData = $this->buildDetailItem($product, $themePayload, $lang);
                            $this->writeJsonFile("{$subDir}/{$product->id}.json", $detailData);

                            $relationDetailMap[$product->id] = $bucket;
                            $detailCounter++;
                            $relDetailCount++;
                        }

                        $remainingOffset += self::CHUNK_SIZE;
                        $this->updateProgress(
                            'Detail-Dateien für weitere Produkte...',
                            $hierarchyCount + $relDetailCount,
                            $hierarchyCount + $remainingCount,
                            'exporting'
                        );
                    }
                }
            }

            if ($cancelled) {
                $this->updateProgress('Export abgebrochen.', $offset, $hierarchyCount, 'cancelled');
                $this->cleanup($tmpDir);
                return ['path' => null, 'total_products' => $offset, 'chunks' => $chunkIndex, 'cancelled' => true];
            }

            // 2c. Search index — lightweight entries for ALL active products (not just hierarchy)
            //     Loaded on-demand in the browser when the user performs a text search.
            $searchIndexDir = $tmpDir . '/search-index';
            $searchChunkFiles = [];
            if (!mkdir($searchIndexDir, 0755, true)) {
                throw new \RuntimeException("Verzeichnis konnte nicht erstellt werden: {$searchIndexDir}");
            }

            $allProductsQuery = $this->buildBaseProductQuery();
            $allProductsCount = $allProductsQuery->count();
            $searchOffset = 0;
            $searchChunkIndex = 0;
            $searchChunkSize = 2000; // Larger chunks since entries are small

            $this->updateProgress('Such-Index wird erstellt...', 0, $allProductsCount, 'search-index');

            // Set of product IDs already in primary chunks (to avoid duplicates in search)
            $primaryIdSet = array_flip($exportedProductIds);

            while ($searchOffset < $allProductsCount) {
                if ($this->isCancelled()) {
                    $cancelled = true;
                    break;
                }

                $products = $this->buildBaseProductQuery()
                    ->with(['searchIndex'])
                    ->orderBy('id')
                    ->skip($searchOffset)
                    ->take($searchChunkSize)
                    ->get();

                $chunkData = [];
                foreach ($products as $product) {
                    // Skip products already in primary chunks
                    if (isset($primaryIdSet[$product->id])) continue;

                    $index = $product->searchIndex;
                    $name = $lang === 'en'
                        ? ($index?->name_en ?: $index?->name_de)
                        : $index?->name_de;

                    $entry = [
                        'id' => $product->id,
                        'name' => $name,
                        'sku' => $product->sku,
                    ];
                    if ($product->ean) $entry['ean'] = $product->ean;
                    $searchText = $index?->searchable_text
                        ? mb_substr($index->searchable_text, 0, 300)
                        : null;
                    if ($searchText) $entry['search'] = $searchText;

                    // Include image and detail bucket for search result display
                    $imageUrl = $index?->primary_image
                        ? "/api/v1/catalog/media/{$index->primary_image}"
                        : null;
                    if ($imageUrl) $entry['img'] = $imageUrl;

                    // Detail bucket: check relationDetailMap first, fallback to null
                    if (isset($relationDetailMap[$product->id])) {
                        $entry['_dd'] = $relationDetailMap[$product->id];
                    }

                    $chunkData[] = $entry;
                }

                if (!empty($chunkData)) {
                    $searchChunkFileName = "chunk-{$searchChunkIndex}.json";
                    $this->writeJsonFile("{$searchIndexDir}/{$searchChunkFileName}", $chunkData);
                    $searchChunkFiles[] = $searchChunkFileName;
                    $searchChunkIndex++;
                }

                $searchOffset += $searchChunkSize;
                $this->updateProgress(
                    'Such-Index wird erstellt...',
                    min($searchOffset, $allProductsCount),
                    $allProductsCount,
                    'search-index'
                );
            }

            // Write search index metadata
            $this->writeJsonFile("{$searchIndexDir}/index.json", [
                'totalProducts' => $allProductsCount - count($exportedProductIds),
                'chunks' => $searchChunkFiles,
            ]);

            Log::channel('export')->info('Offline-Katalog: Such-Index erstellt', [
                'all_products' => $allProductsCount,
                'hierarchy_products' => count($exportedProductIds),
                'search_only' => $allProductsCount - count($exportedProductIds),
                'chunks' => count($searchChunkFiles),
            ]);

            if ($cancelled) {
                $this->updateProgress('Export abgebrochen.', $hierarchyCount, $hierarchyCount, 'cancelled');
                $this->cleanup($tmpDir);
                return ['path' => null, 'total_products' => $hierarchyCount, 'chunks' => $chunkIndex, 'cancelled' => true];
            }

            // 3. Index-Datei
            $this->updateProgress('Index wird erstellt...', $hierarchyCount, $hierarchyCount, 'indexing');
            $totalDetailBuckets = $detailCounter > 0 ? intdiv($detailCounter - 1, self::DETAIL_DIR_SIZE) + 1 : 0;
            $indexData = [
                'totalProducts' => $hierarchyCount,
                'chunkSize' => self::CHUNK_SIZE,
                'chunks' => $chunkFiles,
                'detailDirSize' => self::DETAIL_DIR_SIZE,
                'detailBuckets' => $totalDetailBuckets,
            ];
            // Relation detail lookup: maps product ID → bucket for products not in chunks
            if (!empty($relationDetailMap)) {
                $indexData['relationDetailMap'] = $relationDetailMap;
            }
            $this->writeJsonFile("{$productsDir}/index.json", $indexData);

            // 4. Kategorien
            if ($this->isCancelled()) {
                $this->updateProgress('Export abgebrochen.', $hierarchyCount, $hierarchyCount, 'cancelled');
                $this->cleanup($tmpDir);
                return ['path' => null, 'total_products' => $hierarchyCount, 'chunks' => $chunkIndex, 'cancelled' => true];
            }
            $this->updateProgress('Kategorien werden exportiert...', $hierarchyCount, $hierarchyCount, 'categories');
            $categories = $this->buildCategories($themePayload, $lang);
            $this->writeJsonFile("{$tmpDir}/categories.json", ['data' => $categories]);

            // 5. Facetten
            $this->updateProgress('Facetten werden exportiert...', $hierarchyCount, $hierarchyCount, 'facets');
            $facets = $this->buildFacets($themePayload, $lang);
            $this->writeJsonFile("{$tmpDir}/facets.json", $facets);

            // 6. Attributgruppen
            $attrGroups = $this->buildAttributeGroups($lang);
            $this->writeJsonFile("{$tmpDir}/attribute-groups.json", ['data' => $attrGroups]);

            // 7. Settings
            $settings = $this->buildSettings($themePayload);
            $this->writeJsonFile("{$tmpDir}/settings.json", ['data' => $settings]);

            // 8. index.html + Offline-Assets
            $this->updateProgress('HTML wird generiert...', $hierarchyCount, $hierarchyCount, 'html');
            $this->buildOfflineHtml($tmpDir, $templateId, $lang);

            // 9. ZIP erstellen
            if ($this->isCancelled()) {
                $this->updateProgress('Export abgebrochen.', $hierarchyCount, $hierarchyCount, 'cancelled');
                $this->cleanup($tmpDir);
                return ['path' => null, 'total_products' => $hierarchyCount, 'chunks' => $chunkIndex, 'cancelled' => true];
            }
            $this->updateProgress('ZIP wird erstellt...', $hierarchyCount, $hierarchyCount, 'zipping');

            $outputDir = storage_path('app/exports');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $zipFileName = 'offline-catalog_' . now()->format('Y-m-d_His') . '.zip';
            $zipPath = "{$outputDir}/{$zipFileName}";
            $this->createZip($tmpDir, $zipPath);

            // tmp-Verzeichnis als Preview-Quelle behalten (Umbenennen statt löschen)
            $previewDir = storage_path('app/offline-preview');
            if (is_dir($previewDir)) {
                $this->cleanup($previewDir);
            }
            rename($tmpDir, $previewDir);

            // data/ Unterverzeichnis erstellen — die index.html verweist auf ./data/,
            // aber die JSON-Dateien liegen direkt im Preview-Verzeichnis.
            // Wir erstellen data/ und verschieben die Datenverzeichnisse/Dateien hinein.
            $dataDir = "{$previewDir}/data";
            mkdir($dataDir, 0755, true);
            foreach (['products', 'products-detail', 'search-index', 'categories.json', 'facets.json', 'attribute-groups.json', 'settings.json'] as $item) {
                $src = "{$previewDir}/{$item}";
                if (file_exists($src)) {
                    rename($src, "{$dataDir}/{$item}");
                }
            }

            $duration = round(microtime(true) - $startTime, 2);
            $fileSize = filesize($zipPath);

            $relationDetailCount = count($relationDetailMap ?? []);
            $totalExported = $hierarchyCount + $relationDetailCount;

            $this->updateProgress('Export abgeschlossen.', $totalExported, $totalExported, 'completed', [
                'path' => $zipPath,
                'file_name' => $zipFileName,
                'file_size' => $fileSize,
                'duration' => $duration,
                'preview_dir' => $previewDir,
            ]);

            Log::channel('export')->info('Offline-Katalog-Export abgeschlossen', [
                'path' => $zipPath,
                'hierarchy_products' => $hierarchyCount,
                'relation_details' => $relationDetailCount,
                'total_exported' => $totalExported,
                'chunks' => $chunkIndex,
                'duration' => $duration,
                'file_size' => $fileSize,
            ]);

            return [
                'path' => $zipPath,
                'file_name' => $zipFileName,
                'total_products' => $totalExported,
                'hierarchy_products' => $hierarchyCount,
                'relation_details' => $relationDetailCount,
                'chunks' => $chunkIndex,
                'duration' => $duration,
                'file_size' => $fileSize,
                'cancelled' => false,
            ];
        } catch (\Throwable $e) {
            $this->cleanup($tmpDir);
            Cache::forget(self::CACHE_KEY_CANCEL);
            $this->updateProgress('Fehler: ' . $e->getMessage(), 0, 0, 'failed');
            throw $e;
        }
    }

    public function cancel(): void
    {
        Cache::put(self::CACHE_KEY_CANCEL, true, 300);
    }

    public function getProgress(): ?array
    {
        return Cache::get(self::CACHE_KEY_PROGRESS);
    }

    public function clearProgress(): void
    {
        Cache::forget(self::CACHE_KEY_PROGRESS);
        Cache::forget(self::CACHE_KEY_CANCEL);
    }

    private function isCancelled(): bool
    {
        return (bool) Cache::get(self::CACHE_KEY_CANCEL, false);
    }

    private function updateProgress(string $phase, int $current, int $total, string $status, array $extra = []): void
    {
        Cache::put(self::CACHE_KEY_PROGRESS, array_merge([
            'phase' => $phase,
            'current' => $current,
            'total' => $total,
            'status' => $status,
            'percent' => $total > 0 ? round(($current / $total) * 100) : 0,
            'updated_at' => now()->toIso8601String(),
        ], $extra), self::CACHE_TTL);
    }

    // ─── Query & Data Building ────────────────────────────────────

    /**
     * Hierarchie-Filter auf eine Query anwenden (Subquery statt pluck, vermeidet too-many-placeholders).
     */
    private function applyHierarchyFilter(
        \Illuminate\Database\Eloquent\Builder $query,
        Hierarchy $hierarchy,
        array $excludedNodeIds = [],
    ): \Illuminate\Database\Eloquent\Builder {
        $visibleNodeQuery = HierarchyNode::where('hierarchy_id', $hierarchy->id)
            ->where('is_active', true)
            ->when(!empty($excludedNodeIds), fn ($q) => $q->whereNotIn('id', $excludedNodeIds))
            ->select('id');

        if ($hierarchy->hierarchy_type === 'output') {
            $outputProductQuery = OutputHierarchyProductAssignment::whereIn('hierarchy_node_id', $visibleNodeQuery)
                ->select('product_id');
            $query->whereIn('id', $outputProductQuery);
        } else {
            $query->whereIn('master_hierarchy_node_id', $visibleNodeQuery);
        }

        return $query;
    }

    private function buildProductQuery(array $themePayload): \Illuminate\Database\Eloquent\Builder
    {
        $query = $this->buildBaseProductQuery();

        // Nur verknüpfte Produkte, wenn konfiguriert
        $linkedOnly = !empty($themePayload['catalog_linked_products_only']);
        $hierarchyId = $themePayload['hierarchy_id'] ?? null;

        if ($linkedOnly && $hierarchyId) {
            $hierarchy = Hierarchy::find($hierarchyId);
            $excludedNodeIds = $themePayload['catalog_excluded_node_ids'] ?? [];
            if ($hierarchy) {
                $this->applyHierarchyFilter($query, $hierarchy, $excludedNodeIds);
            }
        }

        return $query->orderBy('sku');
    }

    /**
     * Base query for ALL active products — no hierarchy/linked filter.
     * Used for search index and detail-only exports.
     */
    private function buildBaseProductQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Product::where('status', 'active')
            ->where('product_type_ref', 'product');
    }

    private function loadProductChunk(array $themePayload, string $lang, int $offset, int $limit): Collection
    {
        $query = $this->buildProductQuery($themePayload);

        $hierarchyId = $themePayload['hierarchy_id'] ?? null;
        $hierarchy = $hierarchyId ? Hierarchy::find($hierarchyId) : null;
        $excludedNodeIds = $themePayload['catalog_excluded_node_ids'] ?? [];

        if ($hierarchy) {
            $this->applyHierarchyFilter($query, $hierarchy, $excludedNodeIds);
        } else {
            $query->whereNotNull('master_hierarchy_node_id');
        }

        return $query->with([
                'searchIndex',
                'masterHierarchyNode',
                'attributeValues' => fn ($q) => $q->where(function ($q2) use ($lang) {
                    $q2->whereNull('language')->orWhere('language', $lang);
                }),
                'attributeValues.attribute',
                'attributeValues.valueListEntry',
                'attributeValues.dictionaryEntry',
                'attributeValues.unit',
                'prices' => fn ($q) => $q->where(function ($q2) {
                    $q2->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString());
                })->orderBy('amount'),
                'media',
                'variants',
                'outgoingRelations.relationType',
                'outgoingRelations.targetProduct',
            ])
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    private function buildListItem(Product $product, array $themePayload, string $lang, array $excludedNodeIds = []): array
    {
        $index = $product->searchIndex;
        $node = $product->masterHierarchyNode;

        // Resolve name
        $name = $lang === 'en'
            ? ($index?->name_en ?: $index?->name_de)
            : $index?->name_de;

        // Resolve image URL
        $imageUrl = $index?->primary_image
            ? "/api/v1/catalog/media/{$index->primary_image}"
            : null;

        // Category info — exclude hidden nodes from category references
        $excludedSet = !empty($excludedNodeIds) ? array_flip($excludedNodeIds) : [];
        $rawCategoryId = $product->master_hierarchy_node_id;
        $isNodeExcluded = $rawCategoryId && isset($excludedSet[$rawCategoryId]);

        $categoryPath = ($node && !$isNodeExcluded)
            ? ($lang === 'en' && $node->name_en ? $node->name_en : $node->name_de)
            : null;
        $categoryId = $isNodeExcluded ? null : $rawCategoryId;
        $categoryIds = [];

        // Ancestor category IDs for filtering (skip excluded nodes)
        if ($node && $node->path) {
            $ancestorIds = array_filter(explode('/', $node->path));
            if (!empty($excludedSet)) {
                $ancestorIds = array_filter($ancestorIds, fn ($id) => !isset($excludedSet[$id]));
            }
            $categoryIds = array_values($ancestorIds);
        }
        if ($categoryId) {
            $categoryIds[] = $categoryId;
        }

        // Price
        $price = null;
        $currency = 'EUR';
        $priceTypeId = $themePayload['card_price_type_id'] ?? null;
        if ($priceTypeId) {
            $priceEntry = $product->prices->firstWhere('price_type_id', $priceTypeId);
            if ($priceEntry) {
                $price = (float) $priceEntry->amount;
                $currency = $priceEntry->currency ?? 'EUR';
            }
        }
        if ($price === null && $index?->list_price) {
            $price = (float) $index->list_price;
        }

        // Card attributes
        $cardAttributeIds = $themePayload['card_attribute_ids'] ?? [];
        $cardAttributes = [];
        if (!empty($cardAttributeIds)) {
            $cardAttrOrder = array_flip($cardAttributeIds);
            foreach ($product->attributeValues as $av) {
                if (!in_array($av->attribute_id, $cardAttributeIds)) continue;
                $attr = $av->attribute;
                if (!$attr || $attr->is_internal) continue;
                $value = $this->resolveAttributeValue($av, $attr, $lang);
                if ($value === null || $value === '') continue;
                $unit = $av->unit?->abbreviation;
                $cardAttributes[] = [
                    'attribute_id' => $attr->id,
                    'label' => $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de,
                    'value' => $unit ? $value . ' ' . $unit : $value,
                    '_sort' => $cardAttrOrder[$attr->id] ?? 999,
                ];
            }
            usort($cardAttributes, fn ($a, $b) => $a['_sort'] - $b['_sort']);
            $cardAttributes = array_map(function ($a) {
                unset($a['_sort']);
                return $a;
            }, $cardAttributes);
        }

        // Primary attribute value
        $primaryValue = null;
        $primaryId = $themePayload['primary_card_attribute_id'] ?? null;
        if ($primaryId) {
            $pav = $product->attributeValues->firstWhere('attribute_id', $primaryId);
            if ($pav && $pav->attribute) {
                $v = $this->resolveAttributeValue($pav, $pav->attribute, $lang);
                $unit = $pav->unit?->abbreviation;
                $primaryValue = $unit ? $v . ' ' . $unit : $v;
            }
        }

        // Searchable text for offline search — aus dem Search-Index, der alle
        // durchsuchbaren Attributwerte enthält (name, ShortDescription, etc.)
        // Name/SKU/EAN werden im JS separat gesucht, daher hier rausfiltern.
        $searchableText = $index?->searchable_text
            ? mb_substr($index->searchable_text, 0, 500)
            : null;

        // Facet values for offline filtering — kompaktes Format:
        // ValueList/Selection: value_selection_id (String)
        // Flag: true/false
        // Number: Zahlenwert (float)
        // Sonstiges: value_string
        $facetAttributeIds = $themePayload['facet_attribute_ids'] ?? [];
        $facetValues = [];
        if (!empty($facetAttributeIds)) {
            foreach ($product->attributeValues as $av) {
                if (!in_array($av->attribute_id, $facetAttributeIds)) continue;
                $attr = $av->attribute;
                if (!$attr) continue;

                if (in_array($attr->data_type, ['ValueList', 'Selection', 'Dictionary'])) {
                    // Nur die ID speichern — der Anzeigename steht in facets.json
                    $facetValues[$attr->id] = $av->value_selection_id;
                } elseif ($attr->data_type === 'Flag') {
                    $facetValues[$attr->id] = $av->value_flag;
                } elseif (in_array($attr->data_type, ['Number', 'Float', 'Decimal', 'Integer'])) {
                    $facetValues[$attr->id] = $av->value_number !== null ? (float) $av->value_number : null;
                } else {
                    $facetValues[$attr->id] = $av->value_string;
                }
            }
        }

        $item = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $name,
            'cat' => $categoryId,
            'cats' => $categoryIds,
            'img' => $imageUrl,
        ];

        // Optionale Felder nur wenn vorhanden (spart Platz bei 100k+ Produkten)
        if ($product->ean) $item['ean'] = $product->ean;
        if ($categoryPath) $item['cat_name'] = $categoryPath;
        if ($price !== null) $item['price'] = $price;
        if ($currency !== 'EUR') $item['cur'] = $currency;
        if ($primaryValue) $item['primary'] = $primaryValue;
        if (!empty($cardAttributes)) $item['attrs'] = $cardAttributes;
        if ($searchableText) $item['search'] = $searchableText;
        if (!empty($facetValues)) $item['facets'] = $facetValues;

        return $item;
    }

    private function buildDetailItem(Product $product, array $themePayload, string $lang): array
    {
        $index = $product->searchIndex;
        $node = $product->masterHierarchyNode;

        // Breadcrumb
        $breadcrumb = [];
        if ($node) {
            $ancestors = HierarchyNode::ancestorsOf($node->path)
                ->orderBy('depth')
                ->get();
            foreach ($ancestors as $ancestor) {
                $breadcrumb[] = [
                    'id' => $ancestor->id,
                    'name' => $lang === 'en' && $ancestor->name_en ? $ancestor->name_en : $ancestor->name_de,
                ];
            }
            $breadcrumb[] = [
                'id' => $node->id,
                'name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
            ];
        }

        // Attributes — Composite-Kinder aggregieren
        $attributes = [];
        $compositeChildIds = [];
        $writtenComposites = [];

        // Erst Composite-Kinder identifizieren
        foreach ($product->attributeValues as $av) {
            $attr = $av->attribute;
            if ($attr && $attr->parent_attribute_id) {
                $compositeChildIds[$attr->id] = $attr->parent_attribute_id;
            }
        }

        foreach ($product->attributeValues as $av) {
            $attr = $av->attribute;
            if (!$attr || $attr->is_internal) continue;

            // Kind-Attribute überspringen (werden über Parent ausgegeben)
            if (isset($compositeChildIds[$attr->id])) {
                $parentId = $compositeChildIds[$attr->id];
                if (!isset($writtenComposites[$parentId])) {
                    $composite = Attribute::find($parentId);
                    if ($composite && $composite->data_type === 'Composite') {
                        $children = Attribute::where('parent_attribute_id', $composite->id)
                            ->orderBy('position')->get();
                        $childValues = [];
                        foreach ($children as $child) {
                            $childAv = $product->attributeValues->first(fn ($v) => $v->attribute_id === $child->id);
                            $childValues[] = $childAv ? ($this->resolveAttributeValue($childAv, $child, $lang) ?? '') : '';
                        }
                        $formattedValue = $composite->composite_format
                            ? CompositeFormatResolver::resolve($composite->composite_format, $children->all(), $childValues)
                            : implode(' × ', array_filter($childValues, fn ($v) => $v !== ''));

                        if ($formattedValue !== '') {
                            $attributes[] = [
                                'attribute_id' => $composite->id,
                                'technical_name' => $composite->technical_name,
                                'label' => $lang === 'en' && $composite->name_en ? $composite->name_en : $composite->name_de,
                                'data_type' => 'Composite',
                                'value' => $formattedValue,
                                'raw_value' => $formattedValue,
                                'unit' => null,
                                'group_id' => $composite->attribute_type_id,
                            ];
                        }
                    }
                    $writtenComposites[$parentId] = true;
                }
                continue;
            }

            $value = $this->resolveAttributeValue($av, $attr, $lang);
            if ($value === null || $value === '') continue;

            $unit = $av->unit?->abbreviation;
            $attributes[] = [
                'attribute_id' => $attr->id,
                'technical_name' => $attr->technical_name,
                'label' => $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de,
                'data_type' => $attr->data_type,
                'value' => $unit ? $value . ' ' . $unit : $value,
                'raw_value' => $value,
                'unit' => $unit,
                'group_id' => $attr->attribute_type_id,
            ];
        }

        // Media
        $media = $product->media->map(fn ($m) => [
            'id' => $m->id,
            'file_name' => $m->file_name,
            'mime_type' => $m->mime_type,
            'url' => "/api/v1/catalog/media/{$m->file_name}",
        ])->values()->toArray();

        // Prices
        $prices = $product->prices->map(fn ($p) => [
            'amount' => (float) $p->amount,
            'currency' => $p->currency ?? 'EUR',
            'price_type' => $p->priceType?->name ?? null,
        ])->values()->toArray();

        // Relations
        $relations = $product->outgoingRelations->map(fn ($r) => [
            'type' => $r->relationType?->name ?? null,
            'target_id' => $r->target_product_id,
            'target_sku' => $r->targetProduct?->sku,
            'target_name' => $r->targetProduct?->name,
        ])->values()->toArray();

        // Variants
        $variants = $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'sku' => $v->sku,
            'name' => $v->name,
        ])->values()->toArray();

        // Description attributes
        $descriptionAttributes = $themePayload['description_attributes'] ?? [];
        $descAttrData = [];
        if (!empty($descriptionAttributes)) {
            $descAttrIds = array_column($descriptionAttributes, 'attribute_id');
            $typographyMap = [];
            foreach ($descriptionAttributes as $da) {
                $typographyMap[$da['attribute_id']] = $da['typography'] ?? 'base';
            }
            foreach ($product->attributeValues as $av) {
                if (!in_array($av->attribute_id, $descAttrIds)) continue;
                $attr = $av->attribute;
                if (!$attr || $attr->is_internal) continue;
                $value = $this->resolveAttributeValue($av, $attr, $lang);
                if ($value === null || $value === '') continue;
                $unit = $av->unit?->abbreviation;
                $descAttrData[$av->attribute_id] = [
                    'attribute_id' => $attr->id,
                    'label' => $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de,
                    'value' => $unit ? $value . ' ' . $unit : $value,
                    'typography' => $typographyMap[$attr->id] ?? 'base',
                ];
            }
            // Preserve configured order
            $ordered = [];
            foreach ($descAttrIds as $id) {
                if (isset($descAttrData[$id])) {
                    $ordered[] = $descAttrData[$id];
                }
            }
            $descAttrData = $ordered;
        }

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'name' => $lang === 'en'
                ? ($index?->name_en ?: $index?->name_de)
                : $index?->name_de,
            'description' => $index?->description_de,
            'breadcrumb' => $breadcrumb,
            'attributes' => $attributes,
            'description_attributes' => $descAttrData,
            'media' => $media,
            'prices' => $prices,
            'relations' => $relations,
            'variants' => $variants,
        ];
    }

    // ─── Categories ───────────────────────────────────────────────

    private function buildCategories(array $themePayload, string $lang): array
    {
        $type = 'master';
        $hierarchyId = $themePayload['hierarchy_id'] ?? null;
        $excludedNodeIds = $themePayload['catalog_excluded_node_ids'] ?? [];

        $hierarchyQuery = Hierarchy::where('hierarchy_type', $type);
        if ($hierarchyId) {
            $hierarchyQuery->where('id', $hierarchyId);
        }
        $hierarchy = $hierarchyQuery->first();

        if (!$hierarchy) {
            return ['hierarchy_id' => null, 'hierarchy_name' => null, 'type' => $type, 'nodes' => []];
        }

        $nodesQuery = $hierarchy->nodes()
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('sort_order');

        // Filter out excluded nodes
        if (!empty($excludedNodeIds)) {
            $nodesQuery->whereNotIn('id', $excludedNodeIds);
        }

        $allNodes = $nodesQuery->get();

        $productCounts = $this->getProductCounts($allNodes, $type);

        // Build nested tree
        $rootNodes = $allNodes->whereNull('parent_node_id');
        $nodesByParent = $allNodes->groupBy('parent_node_id');

        $buildTree = function ($nodes) use (&$buildTree, $nodesByParent, $productCounts, $lang) {
            return $nodes->map(function ($node) use (&$buildTree, $nodesByParent, $productCounts, $lang) {
                $children = $nodesByParent->get($node->id, collect());
                return [
                    'id' => $node->id,
                    'name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
                    'product_count' => $productCounts[$node->id] ?? 0,
                    'children' => $buildTree($children)->values()->toArray(),
                ];
            })->values();
        };

        return [
            'hierarchy_id' => $hierarchy->id,
            'hierarchy_name' => $lang === 'en' && $hierarchy->name_en ? $hierarchy->name_en : $hierarchy->name_de,
            'type' => $type,
            'nodes' => $buildTree($rootNodes)->toArray(),
        ];
    }

    private function getProductCounts(Collection $nodes, string $hierarchyType): array
    {
        $nodeIds = $nodes->pluck('id')->toArray();

        if ($hierarchyType === 'output') {
            $directCounts = OutputHierarchyProductAssignment::query()
                ->join('products', 'products.id', '=', 'output_hierarchy_product_assignments.product_id')
                ->where('products.status', 'active')
                ->whereIn('output_hierarchy_product_assignments.hierarchy_node_id', $nodeIds)
                ->groupBy('output_hierarchy_product_assignments.hierarchy_node_id')
                ->select('output_hierarchy_product_assignments.hierarchy_node_id', DB::raw('COUNT(DISTINCT output_hierarchy_product_assignments.product_id) as cnt'))
                ->pluck('cnt', 'hierarchy_node_id')
                ->toArray();
        } else {
            $directCounts = Product::where('status', 'active')
                ->whereIn('master_hierarchy_node_id', $nodeIds)
                ->groupBy('master_hierarchy_node_id')
                ->select('master_hierarchy_node_id', DB::raw('COUNT(*) as cnt'))
                ->pluck('cnt', 'master_hierarchy_node_id')
                ->toArray();
        }

        $counts = [];
        foreach ($nodes as $node) {
            $counts[$node->id] = $directCounts[$node->id] ?? 0;
        }

        $sortedNodes = $nodes->sortByDesc('depth');
        foreach ($sortedNodes as $node) {
            if ($node->parent_node_id && isset($counts[$node->parent_node_id])) {
                $counts[$node->parent_node_id] += $counts[$node->id];
            }
        }

        return $counts;
    }

    // ─── Facets ───────────────────────────────────────────────────

    private function buildFacets(array $themePayload, string $lang): array
    {
        $facetAttributeIds = $themePayload['facet_attribute_ids'] ?? [];
        if (empty($facetAttributeIds)) {
            Log::channel('export')->info('Facets: keine facet_attribute_ids konfiguriert');
            return ['facets' => []];
        }

        $attributes = Attribute::whereIn('id', $facetAttributeIds)->get()->keyBy('id');

        // Scope facets to hierarchy products only (matching the primary/browse index)
        $facetQuery = $this->buildProductQuery($themePayload);
        $hierarchyId = $themePayload['hierarchy_id'] ?? null;
        $hierarchy = $hierarchyId ? Hierarchy::find($hierarchyId) : null;
        $excludedNodeIds = $themePayload['catalog_excluded_node_ids'] ?? [];

        if ($hierarchy && $hierarchy->hierarchy_type === 'output') {
            $visibleNodeQuery = HierarchyNode::where('hierarchy_id', $hierarchy->id)
                ->where('is_active', true)
                ->when(!empty($excludedNodeIds), fn ($q) => $q->whereNotIn('id', $excludedNodeIds))
                ->select('id');
            $outputProductQuery = OutputHierarchyProductAssignment::whereIn('hierarchy_node_id', $visibleNodeQuery)
                ->select('product_id');
            $facetQuery->whereIn('id', $outputProductQuery);
        } elseif ($hierarchy) {
            $visibleNodeQuery = HierarchyNode::where('hierarchy_id', $hierarchy->id)
                ->where('is_active', true)
                ->when(!empty($excludedNodeIds), fn ($q) => $q->whereNotIn('id', $excludedNodeIds))
                ->select('id');
            $facetQuery->whereIn('master_hierarchy_node_id', $visibleNodeQuery);
        } else {
            $facetQuery->whereNotNull('master_hierarchy_node_id');
        }
        $activeProductQuery = $facetQuery->select('id');

        Log::channel('export')->info('Facets: starte Export', [
            'configured_ids' => count($facetAttributeIds),
            'found_attrs' => $attributes->count(),
            'types' => $attributes->pluck('data_type', 'technical_name')->toArray(),
        ]);

        $facets = [];
        foreach ($facetAttributeIds as $attrId) {
            $attr = $attributes->get($attrId);
            if (!$attr) {
                Log::channel('export')->warning("Facet: Attribut {$attrId} nicht gefunden");
                continue;
            }

            $label = $lang === 'en' && $attr->name_en ? $attr->name_en : ($attr->name_de ?: $attr->technical_name);
            $baseQuery = ProductAttributeValue::where('attribute_id', $attrId)
                ->whereIn('product_id', $activeProductQuery);

            if (in_array($attr->data_type, ['ValueList', 'Selection', 'Dictionary'])) {
                $rows = (clone $baseQuery)
                    ->whereNotNull('value_selection_id')
                    ->select('value_selection_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                    ->groupBy('value_selection_id')
                    ->orderByDesc('cnt')
                    ->limit(50)
                    ->get();

                // ValueList/Selection → ValueListEntry, Dictionary → DictionaryEntry
                $ids = $rows->pluck('value_selection_id')->toArray();
                if ($attr->data_type === 'Dictionary') {
                    $entries = DictionaryEntry::whereIn('id', $ids)->get()->keyBy('id');
                } else {
                    $entries = ValueListEntry::whereIn('id', $ids)->get()->keyBy('id');
                }

                $values = [];
                foreach ($rows as $row) {
                    $entry = $entries->get($row->value_selection_id);
                    if (!$entry) continue;

                    if ($attr->data_type === 'Dictionary') {
                        $displayValue = $lang === 'en' && $entry->short_text_en
                            ? $entry->short_text_en
                            : $entry->short_text_de;
                    } else {
                        $displayValue = $lang === 'en' && $entry->display_value_en
                            ? $entry->display_value_en
                            : $entry->display_value_de;
                    }

                    $values[] = [
                        'value' => $displayValue,
                        'value_id' => $row->value_selection_id,
                        'count' => $row->cnt,
                    ];
                }
                $facets[] = ['attribute_id' => $attrId, 'label' => $label, 'data_type' => 'ValueList', 'values' => $values];
            } elseif ($attr->data_type === 'Flag') {
                $counts = (clone $baseQuery)
                    ->whereNotNull('value_flag')
                    ->select('value_flag', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                    ->groupBy('value_flag')
                    ->get()
                    ->keyBy('value_flag');

                $facets[] = [
                    'attribute_id' => $attrId,
                    'label' => $label,
                    'data_type' => 'Boolean',
                    'values' => [
                        ['value' => 'Ja', 'filter_value' => '1', 'count' => $counts->get(1)?->cnt ?? 0],
                        ['value' => 'Nein', 'filter_value' => '0', 'count' => $counts->get(0)?->cnt ?? 0],
                    ],
                ];
            } elseif (in_array($attr->data_type, ['Decimal', 'Integer', 'Number', 'Float'])) {
                $stats = (clone $baseQuery)
                    ->whereNotNull('value_number')
                    ->select(DB::raw('MIN(value_number) as min_val'), DB::raw('MAX(value_number) as max_val'), DB::raw('COUNT(DISTINCT product_id) as cnt'))
                    ->first();

                $unit = null;
                $firstWithUnit = (clone $baseQuery)->whereNotNull('unit_id')->first();
                if ($firstWithUnit && $firstWithUnit->unit) {
                    $unit = $firstWithUnit->unit->abbreviation;
                }

                $facets[] = [
                    'attribute_id' => $attrId,
                    'label' => $label,
                    'data_type' => 'Decimal',
                    'min' => $stats->min_val !== null ? (float) $stats->min_val : null,
                    'max' => $stats->max_val !== null ? (float) $stats->max_val : null,
                    'count' => $stats->cnt ?? 0,
                    'unit' => $unit,
                ];
            } elseif (in_array($attr->data_type, ['Text', 'String'])) {
                $rows = (clone $baseQuery)
                    ->whereNotNull('value_string')
                    ->where('value_string', '!=', '')
                    ->select('value_string', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                    ->groupBy('value_string')
                    ->orderByDesc('cnt')
                    ->limit(20)
                    ->get();

                $values = $rows->map(fn ($r) => [
                    'value' => $r->value_string,
                    'value_id' => $r->value_string,
                    'count' => $r->cnt,
                ])->toArray();

                $facets[] = [
                    'attribute_id' => $attrId,
                    'label' => $label,
                    'data_type' => 'Text',
                    'values' => $values,
                ];
            } else {
                Log::channel('export')->warning("Facet: unbekannter data_type '{$attr->data_type}' für {$attr->technical_name}");
            }
        }

        Log::channel('export')->info('Facets: Export abgeschlossen', [
            'total' => count($facets),
            'types' => collect($facets)->groupBy('data_type')->map->count()->toArray(),
            'labels' => collect($facets)->pluck('label')->toArray(),
        ]);

        return ['facets' => $facets];
    }

    // ─── Attribute Groups ────────────────────────────────────────

    private function buildAttributeGroups(string $lang): array
    {
        return AttributeType::orderBy('sort_order')->get()->map(fn ($g) => [
            'id' => $g->id,
            'name' => $lang === 'en' && $g->name_en ? $g->name_en : $g->name_de,
            'sort_order' => $g->sort_order,
        ])->values()->toArray();
    }

    // ─── Settings ─────────────────────────────────────────────────

    private function buildSettings(array $themePayload): array
    {
        return [
            'catalog_name' => $themePayload['catalog_name'] ?? 'Katalog',
            'primary_color' => $themePayload['primary_color'] ?? '#2563eb',
            'card_show_sku' => $themePayload['card_show_sku'] ?? false,
            'card_show_category' => $themePayload['card_show_category'] ?? true,
            'card_show_price' => $themePayload['card_show_price'] ?? true,
            'catalog_compare_enabled' => $themePayload['catalog_compare_enabled'] ?? false,
            'catalog_compare_max_products' => $themePayload['catalog_compare_max_products'] ?? 3,
            'catalog_share_wishlist_enabled' => $themePayload['catalog_share_wishlist_enabled'] ?? false,
            'catalog_pdf_enabled' => $themePayload['catalog_pdf_enabled'] ?? false, // client-side PDF via jsPDF
            'catalog_excel_export_enabled' => false, // Excel not available offline
            'catalog_access_mode' => 'public', // offline is always public
            'mode' => 'offline',
        ];
    }

    // ─── Offline HTML & Assets ─────────────────────────────────

    /**
     * Build the offline index.html and copy JS/CSS assets into the tmp directory.
     */
    private function buildOfflineHtml(string $tmpDir, ?string $templateId, string $lang): void
    {
        // 1. Copy JS & CSS into tmpDir
        $embedDist = base_path('catalog-embed/dist');
        $jsFile = "{$embedDist}/catalog-offline.umd.js";
        $cssFile = "{$embedDist}/catalog-embed.css";

        if (!file_exists($jsFile)) {
            Log::channel('export')->error('Offline-Bundle JS nicht gefunden', ['path' => $jsFile]);
            throw new \RuntimeException(
                "Offline-Bundle nicht gefunden ({$jsFile}). Bitte per Deployment bereitstellen: cd catalog-embed && VITE_BUILD_TARGET=offline npx vite build"
            );
        }

        copy($jsFile, "{$tmpDir}/catalog-offline.umd.js");
        if (file_exists($cssFile)) {
            copy($cssFile, "{$tmpDir}/catalog-embed.css");
        }

        // 2. Collect build metadata for release info
        $buildMeta = [
            'build_date' => now()->format('Y-m-d H:i:s'),
            'js_hash' => substr(md5_file($jsFile), 0, 8),
            'css_hash' => file_exists($cssFile) ? substr(md5_file($cssFile), 0, 8) : '-',
            'template' => $templateId ? (CatalogTemplate::find($templateId)?->name ?? $templateId) : 'basic',
            'locale' => $lang,
        ];

        // 3. Load template HTML
        $html = $this->loadTemplateHtml($templateId);

        // 4. Transform for offline use
        $html = $this->transformHtmlForOffline($html, $lang, $buildMeta);

        // 5. Write index.html
        if (file_put_contents("{$tmpDir}/index.html", $html) === false) {
            throw new \RuntimeException("Datei konnte nicht geschrieben werden: {$tmpDir}/index.html");
        }
    }

    /**
     * Load the HTML template from database or fallback to basic example.
     */
    private function loadTemplateHtml(?string $templateId): string
    {
        if ($templateId) {
            $template = CatalogTemplate::find($templateId);
            if ($template && $template->html_template) {
                return $template->html_template;
            }
        }

        // Fallback: basic example
        $fallbackPath = base_path('catalog-embed/examples/basic.html');
        if (File::exists($fallbackPath)) {
            return File::get($fallbackPath);
        }

        // Absolute minimum fallback
        return $this->getMinimalOfflineHtml();
    }

    /**
     * Transform an online catalog template HTML into an offline-ready version.
     */
    private function transformHtmlForOffline(string $html, string $lang, array $buildMeta = []): string
    {
        // Remove existing catalog-embed script references (online versions)
        $html = preg_replace(
            '/<script[^>]*src=["\'][^"\']*catalog-embed[^"\']*\.js["\'][^>]*><\/script>\s*/i',
            '',
            $html
        );

        // Remove existing CSS references to catalog-embed
        $html = preg_replace(
            '/<link[^>]*href=["\'][^"\']*catalog-embed[^"\']*\.css["\'][^>]*>\s*/i',
            '',
            $html
        );

        // Remove existing PublixxCatalog.init(...) script block (online init)
        $html = preg_replace(
            '/<script>\s*PublixxCatalog\.init\s*\(\s*\{[^}]*\}\s*\)\s*;?\s*<\/script>\s*/is',
            '',
            $html
        );

        // Inject offline CSS before </head>
        $offlineCss = '  <link rel="stylesheet" href="./catalog-embed.css">' . "\n";
        if (str_contains($html, '</head>')) {
            $html = str_replace('</head>', $offlineCss . '</head>', $html);
        }

        // Inject offline JS + init before </body>
        $offlineScript = <<<HTML
  <script src="./catalog-offline.umd.js"></script>
  <script>
    PublixxCatalogOffline.init({
      dataPath: './data/',
      locale: '{$lang}',
      perPage: 24,
    })
  </script>
HTML;

        // Ensure sidebar-toggle is present (needed for mobile hamburger menu).
        // Inject right after <body> so it appears early in the DOM.
        if (! str_contains($html, 'data-catalog="sidebar-toggle"')) {
            $html = preg_replace(
                '/<body[^>]*>/i',
                '$0' . "\n" . '  <div data-catalog="sidebar-toggle" style="position:fixed;top:12px;left:12px;z-index:9995"></div>',
                $html,
                1
            );
        }

        // Ensure essential hidden widget placeholders are present (wishlist drawer,
        // product detail modal, compare modal). Templates from the DB might omit these.
        $requiredWidgets = ['wishlist', 'product-detail', 'compare'];
        $hiddenWidgetHtml = '';
        foreach ($requiredWidgets as $widget) {
            if (! str_contains($html, 'data-catalog="' . $widget . '"')) {
                $hiddenWidgetHtml .= '  <div data-catalog="' . $widget . '"></div>' . "\n";
            }
        }

        // Build release info (HTML comment + info icon popup)
        $releaseHtml = '';
        if ($buildMeta) {
            $date = e($buildMeta['build_date'] ?? '-');
            $jsHash = e($buildMeta['js_hash'] ?? '-');
            $cssHash = e($buildMeta['css_hash'] ?? '-');
            $template = e($buildMeta['template'] ?? '-');
            $locale = e($buildMeta['locale'] ?? '-');

            $releaseHtml = <<<HTML

  <!-- ════════════════════════════════════════════════
       PublixxCatalog Offline Build
       Build:    {$date}
       JS:       {$jsHash}
       CSS:      {$cssHash}
       Template: {$template}
       Locale:   {$locale}
       ════════════════════════════════════════════════ -->
  <div id="pxc-release-info" style="position:fixed;bottom:12px;right:12px;z-index:9000">
    <button onclick="document.getElementById('pxc-release-popup').style.display=document.getElementById('pxc-release-popup').style.display==='none'?'block':'none'"
      style="width:28px;height:28px;border-radius:50%;border:1px solid #ccc;background:#fff;color:#888;font-size:14px;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,0.15);display:flex;align-items:center;justify-content:center"
      title="Build-Info">&nbsp;&#x2139;&nbsp;</button>
    <div id="pxc-release-popup" style="display:none;position:absolute;bottom:36px;right:0;background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px 16px;font-family:monospace;font-size:12px;line-height:1.6;white-space:nowrap;box-shadow:0 4px 12px rgba(0,0,0,0.15);color:#333">
      <div style="font-weight:700;margin-bottom:4px;font-size:13px;color:#004588">PublixxCatalog Offline</div>
      <div>Build: &nbsp;{$date}</div>
      <div>JS: &nbsp;&nbsp;&nbsp;{$jsHash}</div>
      <div>CSS: &nbsp;&nbsp;{$cssHash}</div>
      <div>Template: {$template}</div>
      <div>Locale: &nbsp;{$locale}</div>
    </div>
  </div>
HTML;
        }

        if (str_contains($html, '</body>')) {
            $html = str_replace('</body>', $hiddenWidgetHtml . $offlineScript . $releaseHtml . "\n</body>", $html);
        } else {
            // No </body> tag — append at end
            $html .= "\n" . $hiddenWidgetHtml . $offlineScript . $releaseHtml;
        }

        return $html;
    }

    /**
     * Minimal offline HTML fallback when no template is available.
     */
    private function getMinimalOfflineHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Offline-Katalog</title>
  <style>
    body { margin: 0; font-family: system-ui, sans-serif; background: #f5f5f5; }
    .page-header { background: #1B3A5C; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
    .page-header h1 { margin: 0; font-size: 1.25rem; }
    .page-header__actions { display: flex; align-items: center; gap: 12px; }
    .layout { display: grid; grid-template-columns: 280px 1fr; max-width: 1400px; margin: 0 auto; min-height: calc(100vh - 64px); }
    .sidebar { background: white; border-right: 1px solid #e5e7eb; overflow-y: auto; }
    .main { padding: 24px; }
    @media (max-width: 768px) { .layout { grid-template-columns: 1fr; } .sidebar { display: none; } }
  </style>
</head>
<body>
  <header class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
      <div data-catalog="sidebar-toggle"></div>
      <h1>Produktkatalog</h1>
    </div>
    <div class="page-header__actions">
      <div data-catalog="search"></div>
      <div data-catalog="locale"></div>
      <div data-catalog="wishlist-button"></div>
    </div>
  </header>
  <div class="layout">
    <aside class="sidebar" data-catalog-sidebar>
      <div data-catalog="categories"></div>
      <div data-catalog="facets"></div>
    </aside>
    <main class="main">
      <div data-catalog="toolbar"></div>
      <div data-catalog="active-filters"></div>
      <div data-catalog="product-grid"></div>
      <div data-catalog="pagination"></div>
    </main>
  </div>
  <div data-catalog="wishlist"></div>
  <div data-catalog="product-detail"></div>
  <div data-catalog="compare"></div>
</body>
</html>
HTML;
    }

    // ─── File I/O ─────────────────────────────────────────────────

    private function writeJsonFile(string $path, mixed $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("JSON-Encoding fehlgeschlagen: " . json_last_error_msg());
        }
        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Datei konnte nicht geschrieben werden: {$path}");
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function resolveAttributeValue($attrValue, $attr, string $lang): ?string
    {
        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float', 'Decimal', 'Integer' => $attrValue->value_number !== null
                ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.')
                : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null ? ($attrValue->value_flag ? 'true' : 'false') : null,
            'Selection', 'ValueList' => $this->resolveSelectionValue($attrValue, $lang),
            'Dictionary' => $this->resolveDictionaryValue($attrValue, $lang),
            default => $attrValue->value_string,
        };
    }

    private function resolveSelectionValue($attrValue, string $lang): ?string
    {
        $entry = $attrValue->valueListEntry;
        if (!$entry) return null;
        return $lang === 'en' && $entry->display_value_en ? $entry->display_value_en : $entry->display_value_de;
    }

    private function resolveDictionaryValue($attrValue, string $lang): ?string
    {
        $entry = $attrValue->dictionaryEntry;
        if (!$entry) return null;
        return $lang === 'en' && $entry->short_text_en ? $entry->short_text_en : $entry->short_text_de;
    }

    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("ZIP konnte nicht erstellt werden: {$zipPath}");
        }

        // Add root-level files (index.html, JS, CSS)
        $rootFiles = ['index.html', 'catalog-offline.umd.js', 'catalog-embed.css'];
        foreach ($rootFiles as $file) {
            $filePath = $sourceDir . '/' . $file;
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $file);
            }
        }

        // Add data/ directory with JSON exports
        $dataDir = $sourceDir . '/products';
        if (is_dir($dataDir)) {
            $this->addDirToZip($zip, $sourceDir, 'data');
        }

        $zip->close();
    }

    private function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $filePath = $dir . '/' . $file;
            $zipPath = $prefix . '/' . $file;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirToZip($zip, $filePath, $zipPath);
            } else {
                $zip->addFile($filePath, $zipPath);
            }
        }
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $action = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $action($fileinfo->getRealPath());
        }

        rmdir($dir);
    }
}
