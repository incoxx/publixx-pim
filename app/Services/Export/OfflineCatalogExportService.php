<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Attribute;
use App\Models\CatalogTemplate;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductSearchIndex;
use App\Models\Setting;
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
    public function generate(?string $lang = null, ?string $templateId = null): array
    {
        $lang = $lang ?? 'de';
        $startTime = microtime(true);

        // Cancel-Flag zurücksetzen, Fortschritt initialisieren
        Cache::forget(self::CACHE_KEY_CANCEL);
        $this->updateProgress('Initialisiere...', 0, 0, 'initializing');

        $themePayload = Setting::getPayload('catalog_theme') ?? [];

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
            // 1. Gesamtanzahl ermitteln
            $totalProducts = $this->buildProductQuery($themePayload)->count();
            $this->updateProgress('Produkte werden exportiert...', 0, $totalProducts, 'exporting');

            if ($totalProducts === 0) {
                $this->updateProgress('Keine aktiven Produkte gefunden.', 0, 0, 'completed');
                $this->cleanup($tmpDir);
                return ['path' => null, 'total_products' => 0, 'chunks' => 0, 'cancelled' => false];
            }

            // 2. Produkte in Chunks exportieren
            $chunkFiles = [];
            $offset = 0;
            $chunkIndex = 0;
            $detailCounter = 0; // Global counter for detail subdirectory bucketing
            $cancelled = false;

            while ($offset < $totalProducts) {
                // Cancel-Check vor jedem Chunk
                if ($this->isCancelled()) {
                    $cancelled = true;
                    Log::channel('export')->info('Offline-Katalog-Export abgebrochen', [
                        'exported' => $offset,
                        'total' => $totalProducts,
                    ]);
                    break;
                }

                $products = $this->loadProductChunk($themePayload, $lang, $offset, self::CHUNK_SIZE);

                // Chunk-Datei (Listendaten) — _detail_dir wird pro Produkt gesetzt
                $chunkFileName = "chunk-{$chunkIndex}.json";
                $listData = [];
                foreach ($products as $product) {
                    $bucket = intdiv($detailCounter, self::DETAIL_DIR_SIZE);
                    $item = $this->buildListItem($product, $themePayload, $lang);
                    $item['_detail_dir'] = $bucket;
                    $listData[] = $item;

                    // Detail-Datei in Unterverzeichnis schreiben
                    $subDir = "{$detailDir}/{$bucket}";
                    if (!is_dir($subDir) && !mkdir($subDir, 0755, true)) {
                        throw new \RuntimeException("Verzeichnis konnte nicht erstellt werden: {$subDir}");
                    }
                    $detailData = $this->buildDetailItem($product, $themePayload, $lang);
                    $this->writeJsonFile(
                        "{$subDir}/{$product->id}.json",
                        $detailData
                    );

                    $detailCounter++;
                }
                $this->writeJsonFile(
                    "{$productsDir}/{$chunkFileName}",
                    $listData
                );
                $chunkFiles[] = $chunkFileName;

                $offset += self::CHUNK_SIZE;
                $chunkIndex++;
                $this->updateProgress(
                    'Produkte werden exportiert...',
                    min($offset, $totalProducts),
                    $totalProducts,
                    'exporting'
                );
            }

            if ($cancelled) {
                $this->updateProgress('Export abgebrochen.', $offset, $totalProducts, 'cancelled');
                $this->cleanup($tmpDir);
                return ['path' => null, 'total_products' => $offset, 'chunks' => $chunkIndex, 'cancelled' => true];
            }

            // 3. Index-Datei
            $this->updateProgress('Index wird erstellt...', $totalProducts, $totalProducts, 'indexing');
            $totalDetailBuckets = $totalProducts > 0 ? intdiv($totalProducts - 1, self::DETAIL_DIR_SIZE) + 1 : 0;
            $this->writeJsonFile("{$productsDir}/index.json", [
                'totalProducts' => $totalProducts,
                'chunkSize' => self::CHUNK_SIZE,
                'chunks' => $chunkFiles,
                'detailDirSize' => self::DETAIL_DIR_SIZE,
                'detailBuckets' => $totalDetailBuckets,
            ]);

            // 4. Kategorien
            if ($this->isCancelled()) {
                $this->updateProgress('Export abgebrochen.', $totalProducts, $totalProducts, 'cancelled');
                $this->cleanup($tmpDir);
                return ['path' => null, 'total_products' => $totalProducts, 'chunks' => $chunkIndex, 'cancelled' => true];
            }
            $this->updateProgress('Kategorien werden exportiert...', $totalProducts, $totalProducts, 'categories');
            $categories = $this->buildCategories($themePayload, $lang);
            $this->writeJsonFile("{$tmpDir}/categories.json", ['data' => $categories]);

            // 5. Facetten
            $this->updateProgress('Facetten werden exportiert...', $totalProducts, $totalProducts, 'facets');
            $facets = $this->buildFacets($themePayload, $lang);
            $this->writeJsonFile("{$tmpDir}/facets.json", $facets);

            // 6. Settings
            $settings = $this->buildSettings($themePayload);
            $this->writeJsonFile("{$tmpDir}/settings.json", ['data' => $settings]);

            // 7. index.html + Offline-Assets
            $this->updateProgress('HTML wird generiert...', $totalProducts, $totalProducts, 'html');
            $this->buildOfflineHtml($tmpDir, $templateId, $lang);

            // 8. ZIP erstellen
            if ($this->isCancelled()) {
                $this->updateProgress('Export abgebrochen.', $totalProducts, $totalProducts, 'cancelled');
                $this->cleanup($tmpDir);
                return ['path' => null, 'total_products' => $totalProducts, 'chunks' => $chunkIndex, 'cancelled' => true];
            }
            $this->updateProgress('ZIP wird erstellt...', $totalProducts, $totalProducts, 'zipping');

            $outputDir = storage_path('app/exports');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $zipFileName = 'offline-catalog_' . now()->format('Y-m-d_His') . '.zip';
            $zipPath = "{$outputDir}/{$zipFileName}";
            $this->createZip($tmpDir, $zipPath);

            // Aufräumen
            $this->cleanup($tmpDir);

            $duration = round(microtime(true) - $startTime, 2);
            $fileSize = filesize($zipPath);

            $this->updateProgress('Export abgeschlossen.', $totalProducts, $totalProducts, 'completed', [
                'path' => $zipPath,
                'file_name' => $zipFileName,
                'file_size' => $fileSize,
                'duration' => $duration,
            ]);

            Log::channel('export')->info('Offline-Katalog-Export abgeschlossen', [
                'path' => $zipPath,
                'total_products' => $totalProducts,
                'chunks' => $chunkIndex,
                'duration' => $duration,
                'file_size' => $fileSize,
            ]);

            return [
                'path' => $zipPath,
                'file_name' => $zipFileName,
                'total_products' => $totalProducts,
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

    private function buildProductQuery(array $themePayload): \Illuminate\Database\Eloquent\Builder
    {
        $query = Product::where('status', 'active')
            ->where('product_type_ref', 'product');

        // Nur verknüpfte Produkte, wenn konfiguriert
        $linkedOnly = !empty($themePayload['catalog_linked_products_only']);
        $hierarchyId = $themePayload['hierarchy_id'] ?? null;

        if ($linkedOnly && $hierarchyId) {
            $hierarchy = Hierarchy::find($hierarchyId);
            if ($hierarchy) {
                $allNodeIds = HierarchyNode::where('hierarchy_id', $hierarchy->id)->pluck('id');
                if ($hierarchy->hierarchy_type === 'output') {
                    $linkedProductIds = OutputHierarchyProductAssignment::whereIn('hierarchy_node_id', $allNodeIds)
                        ->pluck('product_id');
                    $query->whereIn('id', $linkedProductIds);
                } else {
                    $query->whereIn('master_hierarchy_node_id', $allNodeIds);
                }
            }
        }

        return $query->orderBy('sku');
    }

    private function loadProductChunk(array $themePayload, string $lang, int $offset, int $limit): Collection
    {
        return $this->buildProductQuery($themePayload)
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
            ->skip($offset)
            ->take($limit)
            ->get();
    }

    private function buildListItem(Product $product, array $themePayload, string $lang): array
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

        // Category info
        $categoryPath = $node
            ? ($lang === 'en' && $node->name_en ? $node->name_en : $node->name_de)
            : null;
        $categoryId = $product->master_hierarchy_node_id;
        $categoryIds = $categoryId ? [$categoryId] : [];

        // Ancestor category IDs for filtering
        if ($node && $node->path) {
            $ancestorIds = array_filter(explode('/', $node->path));
            $categoryIds = array_merge($ancestorIds, $categoryIds);
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

        // Searchable text for offline search
        $searchableText = collect([
            $index?->name_de,
            $index?->name_en,
            $product->sku,
            $product->ean,
            $index?->description_de,
        ])->filter()->implode(' ');

        // Facet values for offline filtering
        $facetAttributeIds = $themePayload['facet_attribute_ids'] ?? [];
        $facetValues = [];
        if (!empty($facetAttributeIds)) {
            foreach ($product->attributeValues as $av) {
                if (!in_array($av->attribute_id, $facetAttributeIds)) continue;
                $attr = $av->attribute;
                if (!$attr) continue;

                if (in_array($attr->data_type, ['ValueList', 'Selection', 'Dictionary'])) {
                    $entry = $av->valueListEntry ?? $av->dictionaryEntry;
                    $facetValues[$attr->id] = [
                        'value' => $entry ? ($lang === 'en' && ($entry->display_value_en ?? $entry->short_text_en)
                            ? ($entry->display_value_en ?? $entry->short_text_en)
                            : ($entry->display_value_de ?? $entry->short_text_de)) : null,
                        'value_id' => $av->value_selection_id,
                    ];
                } elseif ($attr->data_type === 'Flag') {
                    $facetValues[$attr->id] = ['value' => $av->value_flag];
                } elseif (in_array($attr->data_type, ['Number', 'Float', 'Decimal', 'Integer'])) {
                    $facetValues[$attr->id] = ['value' => $av->value_number !== null ? (float) $av->value_number : null];
                } else {
                    $facetValues[$attr->id] = ['value' => $av->value_string];
                }
            }
        }

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'name' => $name,
            'name_de' => $index?->name_de,
            'name_en' => $index?->name_en,
            'description' => $index?->description_de,
            'category_path' => $categoryPath,
            'category_id' => $categoryId,
            'category_ids' => $categoryIds,
            'image_url' => $imageUrl,
            'price' => $price,
            'currency' => $currency,
            'product_type' => $index?->product_type,
            'primary_attribute_value' => $primaryValue,
            'card_attributes' => $cardAttributes,
            'searchable_text' => $searchableText,
            'facet_values' => $facetValues,
        ];
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

        // Attributes
        $attributes = [];
        foreach ($product->attributeValues as $av) {
            $attr = $av->attribute;
            if (!$attr || $attr->is_internal) continue;

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
                'group' => $attr->attribute_group_id,
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

        $hierarchyQuery = Hierarchy::where('hierarchy_type', $type);
        if ($hierarchyId) {
            $hierarchyQuery->where('id', $hierarchyId);
        }
        $hierarchy = $hierarchyQuery->first();

        if (!$hierarchy) {
            return ['hierarchy_id' => null, 'hierarchy_name' => null, 'type' => $type, 'nodes' => []];
        }

        $allNodes = $hierarchy->nodes()
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->get();

        $productCounts = $this->getProductCounts($allNodes, $type);

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
            return ['facets' => []];
        }

        $attributes = Attribute::whereIn('id', $facetAttributeIds)->get()->keyBy('id');
        $activeProductQuery = Product::where('status', 'active')
            ->where('product_type_ref', 'product')
            ->select('id');

        $facets = [];
        foreach ($facetAttributeIds as $attrId) {
            $attr = $attributes->get($attrId);
            if (!$attr) continue;

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

                $entries = ValueListEntry::whereIn('id', $rows->pluck('value_selection_id')->toArray())->get()->keyBy('id');
                $values = [];
                foreach ($rows as $row) {
                    $entry = $entries->get($row->value_selection_id);
                    if (!$entry) continue;
                    $values[] = [
                        'value' => $lang === 'en' && $entry->display_value_en ? $entry->display_value_en : $entry->display_value_de,
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
            }
        }

        return ['facets' => $facets];
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
            'catalog_pdf_enabled' => false, // PDF not available offline
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

        // 3. Load template HTML
        $html = $this->loadTemplateHtml($templateId);

        // 4. Transform for offline use
        $html = $this->transformHtmlForOffline($html, $lang);

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
    private function transformHtmlForOffline(string $html, string $lang): string
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

        if (str_contains($html, '</body>')) {
            $html = str_replace('</body>', $offlineScript . "\n</body>", $html);
        } else {
            // No </body> tag — append at end
            $html .= "\n" . $offlineScript;
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
    .layout { display: grid; grid-template-columns: 280px 1fr; max-width: 1400px; margin: 0 auto; min-height: calc(100vh - 64px); }
    .sidebar { background: white; border-right: 1px solid #e5e7eb; overflow-y: auto; }
    .main { padding: 24px; }
    @media (max-width: 768px) { .layout { grid-template-columns: 1fr; } .sidebar { display: none; } }
  </style>
</head>
<body>
  <header class="page-header">
    <h1>Produktkatalog</h1>
    <div style="display:flex;align-items:center;gap:12px">
      <div data-catalog="search"></div>
      <div data-catalog="wishlist"></div>
    </div>
  </header>
  <div class="layout">
    <aside class="sidebar">
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
