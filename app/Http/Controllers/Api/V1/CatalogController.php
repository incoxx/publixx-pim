<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CatalogCategoryNodeResource;
use App\Http\Resources\Api\V1\CatalogProductDetailResource;
use App\Http\Resources\Api\V1\CatalogProductResource;
use App\Models\AttributeViewAssignment;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductSearchIndex;
use App\Models\Attribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\Setting;
use App\Models\ValueListEntry;
use App\Models\PdfTemplate;
use App\Services\Inheritance\HierarchyInheritanceService;
use App\Services\PdfTemplate\PdfTemplateService;
use App\Services\Preview\ProductPreviewService;
use App\Support\KoelnerPhonetik;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogController extends BaseController
{
    /**
     * GET /api/v1/catalog/products
     *
     * Paginated list of active products (uses search index for performance).
     */
    public function products(Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'de');
        $perPage = max(1, min((int) $request->query('per_page', '24'), 100));
        $sortField = $request->query('sort', 'name');
        $sortOrder = $request->query('order', 'asc') === 'desc' ? 'desc' : 'asc';
        $search = $request->query('search');
        $categoryId = $request->query('category');
        $hierarchyType = $request->query('hierarchy_type', 'master');

        $query = ProductSearchIndex::query()
            ->join('products', 'products.id', '=', 'products_search_index.product_id')
            ->where('products.status', 'active')
            ->where('products.product_type_ref', 'product');

        $isSearchActive = $search && trim($search) !== '';

        // Search overrides all other filters (category + facets)
        if ($isSearchActive) {
            $term = trim($search);
            $likeTerm = '%' . $term . '%';

            if (DB::getDriverName() === 'mysql') {
                $phoneticTerm = KoelnerPhonetik::encode($term);

                $query->where(function ($q) use ($term, $likeTerm, $phoneticTerm) {
                    $q->whereRaw(
                        'MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)',
                        [$term . '*']
                    )
                    // LIKE fallback for name (catches cases FULLTEXT misses)
                    ->orWhere('products_search_index.name_de', 'like', $likeTerm)
                    ->orWhere('products_search_index.name_en', 'like', $likeTerm)
                    ->orWhere('products_search_index.sku', 'like', $likeTerm)
                    ->orWhere('products_search_index.ean', 'like', $likeTerm)
                    ->orWhere('products_search_index.description_de', 'like', $likeTerm)
                    ->orWhereRaw(
                        'products_search_index.searchable_text IS NOT NULL AND MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(? IN BOOLEAN MODE)',
                        [$term . '*']
                    )
                    ->orWhere('products_search_index.searchable_text', 'like', $likeTerm)
                    ->orWhere('products_search_index.media_text', 'like', $likeTerm)
                    // Phonetic fallback for typo tolerance
                    ->orWhere('products_search_index.phonetic_name_de', 'like', '%' . $phoneticTerm . '%')
                    ->orWhere('products_search_index.phonetic_text', 'like', '%' . $phoneticTerm . '%');
                });

                // Relevance sorting is applied later via addSelect + orderByDesc
            } else {
                // SQLite fallback
                $query->where(function ($q) use ($likeTerm) {
                    $q->where('products_search_index.name_de', 'like', $likeTerm)
                      ->orWhere('products_search_index.name_en', 'like', $likeTerm)
                      ->orWhere('products_search_index.sku', 'like', $likeTerm)
                      ->orWhere('products_search_index.ean', 'like', $likeTerm)
                      ->orWhere('products_search_index.description_de', 'like', $likeTerm)
                      ->orWhere('products_search_index.searchable_text', 'like', $likeTerm)
                      ->orWhere('products_search_index.media_text', 'like', $likeTerm);
                });
            }
        } else {
            // No search active — apply category + facet filters normally

            // Category filter
            if ($categoryId) {
                $node = HierarchyNode::find($categoryId);
                if ($node) {
                    $descendantIds = HierarchyNode::where('hierarchy_id', $node->hierarchy_id)
                        ->where('path', 'like', $node->path . '%')
                        ->pluck('id')
                        ->toArray();

                    if ($hierarchyType === 'output') {
                        $productIds = OutputHierarchyProductAssignment::whereIn('hierarchy_node_id', $descendantIds)
                            ->pluck('product_id');
                        $query->whereIn('products_search_index.product_id', $productIds);
                    } else {
                        $query->whereIn('products.master_hierarchy_node_id', $descendantIds);
                    }
                }
            }

            // "Nur verknüpfte Produkte" – restrict to products in the configured hierarchy
            if (!$categoryId) {
                $themePayload = Setting::getPayload('catalog_theme') ?? [];
                $linkedOnly = !empty($themePayload['catalog_linked_products_only']);
                $settingsHierarchyId = $themePayload['hierarchy_id'] ?? null;

                if ($linkedOnly && $settingsHierarchyId) {
                    $hierarchy = Hierarchy::find($settingsHierarchyId);
                    if ($hierarchy) {
                        $allNodeIds = HierarchyNode::where('hierarchy_id', $hierarchy->id)->pluck('id');
                        if ($hierarchy->hierarchy_type === 'output') {
                            $linkedProductIds = OutputHierarchyProductAssignment::whereIn('hierarchy_node_id', $allNodeIds)
                                ->pluck('product_id');
                            $query->whereIn('products_search_index.product_id', $linkedProductIds);
                        } else {
                            $query->whereIn('products.master_hierarchy_node_id', $allNodeIds);
                        }
                    }
                }
            }

            // Facet filters
            $filters = $request->query('filters', []);
            if (is_array($filters)) {
                $filterIdx = 0;
                foreach ($filters as $attrId => $filterValue) {
                    if (!is_string($attrId) || empty($filterValue)) {
                        continue;
                    }
                    $alias = "pav_f{$filterIdx}";
                    $filterIdx++;

                    $query->join("product_attribute_values as {$alias}", function ($join) use ($alias, $attrId) {
                        $join->on("{$alias}.product_id", '=', 'products.id')
                             ->where("{$alias}.attribute_id", '=', $attrId);
                    });

                    if (str_contains($filterValue, ':')) {
                        [$min, $max] = explode(':', $filterValue, 2);
                        if ($min !== '') {
                            $query->where("{$alias}.value_number", '>=', (float) $min);
                        }
                        if ($max !== '') {
                            $query->where("{$alias}.value_number", '<=', (float) $max);
                        }
                    } elseif ($filterValue === '0' || $filterValue === '1') {
                        $query->where("{$alias}.value_flag", '=', $filterValue === '1');
                    } else {
                        $values = array_filter(explode(',', $filterValue));
                        if (!empty($values)) {
                            $query->where(function ($q) use ($alias, $values) {
                                $q->whereIn("{$alias}.value_selection_id", $values)
                                  ->orWhereIn("{$alias}.value_string", $values);
                            });
                        }
                    }
                }
            }
        }

        $selectColumns = [
            'products.id',
            'products_search_index.sku',
            'products_search_index.ean',
            'products_search_index.name_de',
            'products_search_index.name_en',
            'products_search_index.description_de',
            'products_search_index.hierarchy_path',
            'products_search_index.primary_image',
            'products_search_index.list_price',
            'products_search_index.product_type',
        ];

        // Include search-related columns for match_sources when searching
        if ($isSearchActive) {
            $selectColumns[] = 'products_search_index.searchable_text';
            $selectColumns[] = 'products_search_index.media_text';
            $selectColumns[] = 'products_search_index.phonetic_name_de';
            $selectColumns[] = 'products_search_index.phonetic_text';
        }

        $query->select($selectColumns);

        // Relevance scoring + sorting — addSelect AFTER select() to avoid overwrite
        if ($isSearchActive && DB::getDriverName() === 'mysql') {
            $term = trim($search);
            $query->addSelect(DB::raw(
                '(MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(' . DB::getPdo()->quote($term . '*') . ' IN BOOLEAN MODE)) * 10 '
                . '+ IF(products_search_index.searchable_text IS NOT NULL, (MATCH(products_search_index.searchable_text, products_search_index.media_text) AGAINST(' . DB::getPdo()->quote($term . '*') . ' IN BOOLEAN MODE)) * 3, 0) '
                . 'as relevance_score'
            ));
            $query->orderByDesc('relevance_score');
        } elseif (!$isSearchActive) {
            $sortColumn = match ($sortField) {
                'price' => 'products_search_index.list_price',
                'sku' => 'products_search_index.sku',
                'updated_at' => 'products_search_index.updated_at',
                default => $lang === 'en' ? 'products_search_index.name_en' : 'products_search_index.name_de',
            };
            $query->orderBy($sortColumn, $sortOrder);
        } else {
            // SQLite search — no relevance scoring, use default sort
            $sortColumn = match ($sortField) {
                'price' => 'products_search_index.list_price',
                'sku' => 'products_search_index.sku',
                'updated_at' => 'products_search_index.updated_at',
                default => $lang === 'en' ? 'products_search_index.name_en' : 'products_search_index.name_de',
            };
            $query->orderBy($sortColumn, $sortOrder);
        }

        $paginated = $query->paginate($perPage);

        // Load card attributes if configured
        $themePayload = Setting::getPayload('catalog_theme') ?? [];
        $cardAttributeIds = $themePayload['card_attribute_ids'] ?? [];
        $primaryCardAttributeId = $themePayload['primary_card_attribute_id'] ?? null;
        $cardAttributeMap = [];
        $primaryAttributeMap = [];

        if (!empty($cardAttributeIds)) {
            $productIds = collect($paginated->items())->pluck('id')->toArray();
            if (!empty($productIds)) {
                $cardValues = ProductAttributeValue::whereIn('product_id', $productIds)
                    ->whereIn('attribute_id', $cardAttributeIds)
                    ->with(['attribute', 'valueListEntry', 'unit'])
                    ->get();

                foreach ($cardValues as $cv) {
                    $attr = $cv->attribute;
                    if (!$attr || $attr->is_internal) {
                        continue;
                    }
                    $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;
                    $value = $this->resolveExportAttributeValue($cv, $attr, $lang);
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $unit = $cv->unit?->abbreviation;
                    $cardAttributeMap[$cv->product_id][] = [
                        'attribute_id' => $attr->id,
                        'technical_name' => $attr->technical_name,
                        'label' => $label,
                        'value' => $unit ? $value . ' ' . $unit : $value,
                    ];
                }

                // Sort card attributes per product according to configured order
                $cardAttrOrder = array_flip($cardAttributeIds);
                foreach ($cardAttributeMap as $pid => $attrs) {
                    usort($attrs, fn ($a, $b) => ($cardAttrOrder[$a['attribute_id']] ?? 999) - ($cardAttrOrder[$b['attribute_id']] ?? 999));
                    $cardAttributeMap[$pid] = $attrs;
                }
            }
        }

        // Load primary attribute if configured
        if ($primaryCardAttributeId) {
            $productIds = $productIds ?? collect($paginated->items())->pluck('id')->toArray();
            if (!empty($productIds)) {
                $primaryValues = ProductAttributeValue::whereIn('product_id', $productIds)
                    ->where('attribute_id', $primaryCardAttributeId)
                    ->with(['attribute', 'valueListEntry', 'unit'])
                    ->get();

                foreach ($primaryValues as $pv) {
                    $attr = $pv->attribute;
                    if (!$attr) continue;
                    $value = $this->resolveExportAttributeValue($pv, $attr, $lang);
                    if ($value === null || $value === '') continue;
                    $unit = $pv->unit?->abbreviation;
                    $primaryAttributeMap[$pv->product_id] = $unit ? $value . ' ' . $unit : $value;
                }
            }
        }

        // Resolve price from configured price type + country (instead of hardcoded list_price)
        $priceTypeId = $themePayload['card_price_type_id'] ?? null;
        $priceCountry = $themePayload['card_price_country'] ?? null;
        $priceMap = [];

        if ($priceTypeId) {
            $productIds = $productIds ?? collect($paginated->items())->pluck('id')->toArray();
            if (!empty($productIds)) {
                $priceQuery = ProductPrice::whereIn('product_id', $productIds)
                    ->where('price_type_id', $priceTypeId)
                    ->where(function ($q) {
                        $q->whereNull('valid_to')
                           ->orWhere('valid_to', '>=', now()->toDateString());
                    });

                if ($priceCountry) {
                    $priceQuery->where(function ($q) use ($priceCountry) {
                        $q->where('country', $priceCountry)
                           ->orWhereNull('country');
                    })->orderByRaw('country IS NULL ASC'); // prefer country-specific
                }

                $priceQuery->orderBy('valid_from', 'desc');

                $prices = $priceQuery->get();
                foreach ($prices as $price) {
                    // Keep only the first (most relevant) price per product
                    if (!isset($priceMap[$price->product_id])) {
                        $priceMap[$price->product_id] = [
                            'amount' => (float) $price->amount,
                            'currency' => $price->currency,
                        ];
                    }
                }
            }
        }

        // Resolve hierarchy_path UUIDs to human-readable names
        $allPaths = collect($paginated->items())
            ->pluck('hierarchy_path')
            ->filter()
            ->unique();
        $ancestorNodeIds = collect();
        foreach ($allPaths as $path) {
            $ids = array_filter(explode('/', $path));
            $ancestorNodeIds = $ancestorNodeIds->merge($ids);
        }
        // Also add master_hierarchy_node_id for the leaf name
        $leafNodeIds = collect($paginated->items())->pluck('product_id')->toArray();
        $leafNodes = DB::table('products')
            ->whereIn('id', $leafNodeIds)
            ->whereNotNull('master_hierarchy_node_id')
            ->pluck('master_hierarchy_node_id');
        $ancestorNodeIds = $ancestorNodeIds->merge($leafNodes)->unique()->filter();
        $nodeNameMap = [];
        if ($ancestorNodeIds->isNotEmpty()) {
            $nodeNameMap = HierarchyNode::whereIn('id', $ancestorNodeIds)
                ->get()
                ->mapWithKeys(fn ($n) => [$n->id => $lang === 'en' && $n->name_en ? $n->name_en : $n->name_de])
                ->toArray();
        }

        // Compute match_sources for search results (only for current page)
        if ($isSearchActive) {
            $term = mb_strtolower(trim($search));
            $productIdsForMatch = collect($paginated->items())->pluck('id')->toArray();

            // Batch-load attribute match sources for all products on this page
            $attrMatchMap = $this->findAttributeMatchSourcesBatch($productIdsForMatch, $term, $lang);

            $phoneticTerm = KoelnerPhonetik::encode($term);

            foreach ($paginated->items() as $item) {
                $sources = [];

                // Check name match — also check primary_attribute_value (displayed name, loaded from EAV)
                // since name_de/name_en in search index may be stale until reindex
                $nameTexts = array_filter([
                    $item->name_de ?? '',
                    $item->name_en ?? '',
                    $primaryAttributeMap[$item->id] ?? '',
                ]);
                $nameMatch = false;
                foreach ($nameTexts as $nameText) {
                    if ($nameText !== '' && str_contains(mb_strtolower($nameText), $term)) {
                        $nameMatch = true;
                        break;
                    }
                }
                if ($nameMatch) {
                    $sources[] = ['type' => 'name', 'label' => $lang === 'en' ? 'Product name' : 'Produktname'];
                }
                if (str_contains(mb_strtolower($item->sku ?? ''), $term)) {
                    $sources[] = ['type' => 'sku', 'label' => 'SKU'];
                }
                if (str_contains(mb_strtolower($item->ean ?? ''), $term)) {
                    $sources[] = ['type' => 'ean', 'label' => 'EAN'];
                }
                if (str_contains(mb_strtolower($item->description_de ?? ''), $term)) {
                    $sources[] = ['type' => 'description', 'label' => $lang === 'en' ? 'Description' : 'Beschreibung'];
                }
                if (str_contains(mb_strtolower($item->searchable_text ?? ''), $term)) {
                    $sources = array_merge($sources, $attrMatchMap[$item->id] ?? []);
                }
                if (str_contains(mb_strtolower($item->media_text ?? ''), $term)) {
                    $sources[] = ['type' => 'media', 'label' => $lang === 'en' ? 'Document' : 'Dokument'];
                }

                // Explicit phonetic check — only show "Ähnlich klingend" when actually matched phonetically
                if (empty($sources) && $phoneticTerm !== '') {
                    $isPhoneticMatch = str_contains($item->phonetic_name_de ?? '', $phoneticTerm)
                        || str_contains($item->phonetic_text ?? '', $phoneticTerm);
                    if ($isPhoneticMatch) {
                        $sources[] = ['type' => 'phonetic', 'label' => $lang === 'en' ? 'Sounds similar' : 'Ähnlich klingend'];
                    }
                }

                // Fallback: product was found but we couldn't determine the exact source
                if (empty($sources)) {
                    $sources[] = ['type' => 'name', 'label' => $lang === 'en' ? 'Product name' : 'Produktname'];
                }

                $item->match_sources = $sources;
            }
        }

        // Attach card attributes, primary attribute, resolved price, and resolved category path to each item
        foreach ($paginated->items() as $item) {
            $item->card_attributes = $cardAttributeMap[$item->id] ?? [];
            $item->primary_attribute_value = $primaryAttributeMap[$item->id] ?? null;
            if (!empty($priceMap) && isset($priceMap[$item->id])) {
                $item->resolved_price = $priceMap[$item->id];
            }
            // Resolve hierarchy_path to readable names
            if ($item->hierarchy_path) {
                $pathIds = array_filter(explode('/', $item->hierarchy_path));
                $pathNames = array_filter(array_map(fn ($id) => $nodeNameMap[$id] ?? null, $pathIds));
                $item->hierarchy_path = implode(' / ', $pathNames);
            }
        }

        $data = CatalogProductResource::collection($paginated->items())
            ->additional(['lang' => $lang])
            ->resolve();

        return response()->json($data, 200, [
            'X-Total-Count' => (string) $paginated->total(),
            'X-Current-Page' => (string) $paginated->currentPage(),
            'X-Last-Page' => (string) $paginated->lastPage(),
            'X-Per-Page' => (string) $paginated->perPage(),
        ]);
    }

    /**
     * GET /api/v1/catalog/products/{product}
     *
     * Full product detail for active products.
     */
    public function product(Request $request, string $productId): JsonResponse
    {
        $lang = $request->query('lang', 'de');

        // Check which relation types to show in catalog
        $themePayloadEarly = Setting::getPayload('catalog_theme') ?? [];
        $catalogRelationTypeIds = $themePayloadEarly['catalog_relation_type_ids'] ?? [];

        $product = Product::where('status', 'active')
            ->with([
                'media',
                'prices' => function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNull('valid_to')
                           ->orWhere('valid_to', '>=', now());
                    })->orderBy('amount');
                },
                'searchIndex',
                'masterHierarchyNode',
                'attributeValues' => function ($q) use ($lang) {
                    $q->where(function ($q2) use ($lang) {
                        $q2->whereNull('language')->orWhere('language', $lang);
                    });
                },
                'attributeValues.attribute',
                'attributeValues.valueListEntry',
                'attributeValues.dictionaryEntry',
                'attributeValues.unit',
                'variants',
                'outgoingRelations' => function ($q) use ($catalogRelationTypeIds) {
                    if (!empty($catalogRelationTypeIds)) {
                        $q->whereIn('relation_type_id', $catalogRelationTypeIds);
                    } else {
                        $q->whereRaw('1 = 0'); // Load none if no types configured
                    }
                    $q->orderBy('sort_order');
                },
                'outgoingRelations.targetProduct',
                'outgoingRelations.relationType',
            ])
            ->findOrFail($productId);

        // Build breadcrumb from materialized path
        $breadcrumb = [];
        if ($product->masterHierarchyNode) {
            $ancestors = HierarchyNode::ancestorsOf($product->masterHierarchyNode->path)
                ->orderBy('depth')
                ->get();

            foreach ($ancestors as $ancestor) {
                $breadcrumb[] = [
                    'id' => $ancestor->id,
                    'name' => $lang === 'en' && $ancestor->name_en ? $ancestor->name_en : $ancestor->name_de,
                ];
            }

            // Add the current node
            $breadcrumb[] = [
                'id' => $product->masterHierarchyNode->id,
                'name' => $lang === 'en' && $product->masterHierarchyNode->name_en
                    ? $product->masterHierarchyNode->name_en
                    : $product->masterHierarchyNode->name_de,
            ];
        }

        // Reuse theme settings already loaded for relation type filtering
        $themePayload = $themePayloadEarly;
        $allowedAttributeIds = $this->buildAllowedAttributeIds($product, $themePayload);

        // Load description attributes configuration
        $descriptionAttributes = $themePayload['description_attributes'] ?? [];
        $descriptionAttrData = [];
        if (!empty($descriptionAttributes)) {
            $descAttrIds = array_column($descriptionAttributes, 'attribute_id');
            $typographyMap = [];
            foreach ($descriptionAttributes as $da) {
                $typographyMap[$da['attribute_id']] = $da['typography'] ?? 'base';
            }

            $descValues = ProductAttributeValue::where('product_id', $product->id)
                ->whereIn('attribute_id', $descAttrIds)
                ->with(['attribute', 'valueListEntry', 'unit'])
                ->get();

            foreach ($descValues as $dv) {
                $attr = $dv->attribute;
                if (!$attr || $attr->is_internal) {
                    continue;
                }
                $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;
                $value = $this->resolveExportAttributeValue($dv, $attr, $lang);
                if ($value === null || $value === '') {
                    continue;
                }
                $unit = $dv->unit?->abbreviation;
                $descriptionAttrData[$attr->id] = [
                    'attribute_id' => $attr->id,
                    'label' => $label,
                    'value' => $unit ? $value . ' ' . $unit : $value,
                    'typography' => $typographyMap[$attr->id] ?? 'base',
                ];
            }

            // Preserve configured order
            $ordered = [];
            foreach ($descAttrIds as $id) {
                if (isset($descriptionAttrData[$id])) {
                    $ordered[] = $descriptionAttrData[$id];
                }
            }
            $descriptionAttrData = $ordered;
        }

        // Merge output hierarchy attribute values when an output hierarchy is configured
        $this->mergeOutputHierarchyValues($product, $themePayload);

        return response()->json([
            'data' => (new CatalogProductDetailResource($product))
                ->additional([
                    'lang' => $lang,
                    'breadcrumb' => $breadcrumb,
                    'allowed_attribute_ids' => $allowedAttributeIds,
                    'description_attributes' => $descriptionAttrData,
                    'catalog_relation_type_ids' => $catalogRelationTypeIds,
                ])
                ->resolve(),
        ]);
    }

    /**
     * GET /api/v1/catalog/products/{product}/json
     *
     * Raw JSON view of a single product with all attributes.
     */
    public function productJson(Request $request, string $productId): JsonResponse
    {
        $lang = $request->query('lang', 'de');

        $product = Product::where('status', 'active')
            ->with([
                'media',
                'prices' => function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNull('valid_to')
                           ->orWhere('valid_to', '>=', now());
                    })->orderBy('amount');
                },
                'searchIndex',
                'masterHierarchyNode',
                'attributeValues.attribute',
                'attributeValues.valueListEntry',
                'attributeValues.dictionaryEntry',
                'attributeValues.unit',
                'variants',
            ])
            ->findOrFail($productId);

        // Build breadcrumb
        $breadcrumb = [];
        if ($product->masterHierarchyNode) {
            $ancestors = HierarchyNode::ancestorsOf($product->masterHierarchyNode->path)
                ->orderBy('depth')
                ->get();
            foreach ($ancestors as $ancestor) {
                $breadcrumb[] = [
                    'id' => $ancestor->id,
                    'name' => $lang === 'en' && $ancestor->name_en ? $ancestor->name_en : $ancestor->name_de,
                ];
            }
            $breadcrumb[] = [
                'id' => $product->masterHierarchyNode->id,
                'name' => $lang === 'en' && $product->masterHierarchyNode->name_en
                    ? $product->masterHierarchyNode->name_en
                    : $product->masterHierarchyNode->name_de,
            ];
        }

        // Load attribute view filter from settings (includes hierarchy-assigned attributes)
        $themePayload = Setting::getPayload('catalog_theme') ?? [];
        $allowedAttributeIds = $this->buildAllowedAttributeIds($product, $themePayload);

        // Load description attributes configuration
        $descriptionAttributes = $themePayload['description_attributes'] ?? [];
        $descriptionAttrData = [];
        if (!empty($descriptionAttributes)) {
            $descAttrIds = array_column($descriptionAttributes, 'attribute_id');
            $typographyMap = [];
            foreach ($descriptionAttributes as $da) {
                $typographyMap[$da['attribute_id']] = $da['typography'] ?? 'base';
            }

            $descValues = ProductAttributeValue::where('product_id', $product->id)
                ->whereIn('attribute_id', $descAttrIds)
                ->with(['attribute', 'valueListEntry', 'unit'])
                ->get();

            foreach ($descValues as $dv) {
                $attr = $dv->attribute;
                if (!$attr || $attr->is_internal) {
                    continue;
                }
                $label = $lang === 'en' && $attr->name_en ? $attr->name_en : $attr->name_de;
                $value = $this->resolveExportAttributeValue($dv, $attr, $lang);
                if ($value === null || $value === '') {
                    continue;
                }
                $unit = $dv->unit?->abbreviation;
                $descriptionAttrData[$attr->id] = [
                    'attribute_id' => $attr->id,
                    'label' => $label,
                    'value' => $unit ? $value . ' ' . $unit : $value,
                    'typography' => $typographyMap[$attr->id] ?? 'base',
                ];
            }

            $ordered = [];
            foreach ($descAttrIds as $id) {
                if (isset($descriptionAttrData[$id])) {
                    $ordered[] = $descriptionAttrData[$id];
                }
            }
            $descriptionAttrData = $ordered;
        }

        // Merge output hierarchy attribute values when an output hierarchy is configured
        $this->mergeOutputHierarchyValues($product, $themePayload);

        return response()->json(
            (new CatalogProductDetailResource($product))
                ->additional([
                    'lang' => $lang,
                    'breadcrumb' => $breadcrumb,
                    'allowed_attribute_ids' => $allowedAttributeIds,
                    'description_attributes' => $descriptionAttrData,
                ])
                ->resolve(),
            200,
            ['Content-Type' => 'application/json'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * GET /api/v1/catalog/products/export.json
     *
     * Flat aggregated JSON array of all active products with attributes.
     * Query params: start (offset, default 0), limit (default 100, max 1000), lang
     */
    public function productsExportJson(Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'de');
        $start = max(0, (int) $request->query('start', '0'));
        $limit = min(max(1, (int) $request->query('limit', '100')), 1000);

        $totalCount = Product::where('status', 'active')
            ->where('product_type_ref', 'product')
            ->count();

        $products = Product::where('status', 'active')
            ->where('product_type_ref', 'product')
            ->with([
                'searchIndex',
                'masterHierarchyNode',
                'attributeValues.attribute',
                'attributeValues.valueListEntry',
                'attributeValues.dictionaryEntry',
                'attributeValues.unit',
                'prices' => function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNull('valid_to')
                           ->orWhere('valid_to', '>=', now());
                    })->orderBy('amount');
                },
            ])
            ->orderBy('sku')
            ->skip($start)
            ->take($limit)
            ->get();

        // Pre-load all hierarchy nodes needed for category paths
        $allNodeIds = $products->pluck('master_hierarchy_node_id')->filter()->unique();
        $allPaths = $products->map(fn ($p) => $p->masterHierarchyNode?->path)->filter()->unique();
        // Collect ancestor node IDs from materialized paths
        $ancestorNodeIds = collect();
        foreach ($allPaths as $path) {
            // Path format: /uuid1/uuid2/ — extract UUIDs
            $ids = array_filter(explode('/', $path));
            $ancestorNodeIds = $ancestorNodeIds->merge($ids);
        }
        $ancestorNodeIds = $ancestorNodeIds->merge($allNodeIds)->unique();
        $nodeMap = HierarchyNode::whereIn('id', $ancestorNodeIds)->get()->keyBy('id');

        $flatProducts = $products->map(function (Product $product) use ($lang, $nodeMap) {
            // Build category info from hierarchy node
            $categoryName = null;
            $categoryId = $product->master_hierarchy_node_id;
            $categoryPath = null;

            $node = $product->masterHierarchyNode;
            if ($node) {
                $categoryName = $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de;

                // Build path from ancestor IDs in materialized path
                $ancestorIds = array_filter(explode('/', $node->path));
                $pathParts = [];
                foreach ($ancestorIds as $ancestorId) {
                    $ancestor = $nodeMap->get($ancestorId);
                    if ($ancestor) {
                        $pathParts[] = $lang === 'en' && $ancestor->name_en ? $ancestor->name_en : $ancestor->name_de;
                    }
                }
                $pathParts[] = $categoryName;
                $categoryPath = implode('|', $pathParts);
            }

            $row = [
                'artikelnummer' => $product->sku,
                'ean' => $product->ean,
                'name' => $lang === 'en'
                    ? ($product->searchIndex?->name_en ?: $product->searchIndex?->name_de)
                    : $product->searchIndex?->name_de,
                'beschreibung' => $product->searchIndex?->description_de,
                'kategorie' => $categoryName,
                'kategorie_id' => $categoryId,
                'kategorie_pfad' => $categoryPath,
                'preis' => $product->prices->first()?->amount,
                'waehrung' => $product->prices->first()?->currency ?? 'EUR',
            ];

            // Flatten all attribute values into the row (exclude internal attributes)
            foreach ($product->attributeValues as $attrValue) {
                $attr = $attrValue->attribute;
                if (!$attr || $attr->is_internal) {
                    continue;
                }

                $key = $attr->technical_name;
                $value = $this->resolveExportAttributeValue($attrValue, $attr, $lang);
                if ($value !== null && $value !== '') {
                    $unit = $attrValue->unit?->abbreviation;
                    $row[$key] = $unit ? $value . ' ' . $unit : $value;
                }
            }

            return $row;
        })->values();

        return response()->json($flatProducts->values(), 200, [
            'Content-Type' => 'application/json',
            'X-Total-Count' => (string) $totalCount,
            'X-Start' => (string) $start,
            'X-Limit' => (string) $limit,
            'X-Count' => (string) $flatProducts->count(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Batch-find which searchable attributes matched for a set of product IDs.
     * Single query for all products on the page for performance.
     */
    private function findAttributeMatchSourcesBatch(array $productIds, string $term, string $lang): array
    {
        if (empty($productIds)) {
            return [];
        }

        $likeTerm = '%' . $term . '%';

        // Find matching attributes via value_string (String, RichText, etc.)
        $stringMatches = ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->whereIn('product_attribute_values.product_id', $productIds)
            ->where('attributes.is_searchable', true)
            ->where('product_attribute_values.value_string', 'like', $likeTerm)
            ->select(
                'product_attribute_values.product_id',
                'attributes.name_de',
                'attributes.name_en',
                'attributes.technical_name',
            )
            ->get();

        // Find matching attributes via value_list_entries (Selection/Dictionary)
        $selectionMatches = ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->join('value_list_entries', 'value_list_entries.id', '=', 'product_attribute_values.value_selection_id')
            ->whereIn('product_attribute_values.product_id', $productIds)
            ->where('attributes.is_searchable', true)
            ->where(function ($q) use ($likeTerm) {
                $q->where('value_list_entries.display_value_de', 'like', $likeTerm)
                  ->orWhere('value_list_entries.display_value_en', 'like', $likeTerm);
            })
            ->select(
                'product_attribute_values.product_id',
                'attributes.name_de',
                'attributes.name_en',
                'attributes.technical_name',
            )
            ->get();

        $map = [];
        $allMatches = $stringMatches->merge($selectionMatches);

        foreach ($allMatches as $match) {
            $pid = $match->product_id;
            $techName = $match->technical_name;

            // Deduplicate per product+attribute and limit to 3
            if (!isset($map[$pid])) {
                $map[$pid] = [];
            }
            $existing = array_column($map[$pid], 'technical_name');
            if (count($map[$pid]) < 3 && !in_array($techName, $existing, true)) {
                $map[$pid][] = [
                    'type' => 'attribute',
                    'label' => $lang === 'en' && $match->name_en ? $match->name_en : $match->name_de,
                    'technical_name' => $techName,
                ];
            }
        }

        return $map;
    }

    /**
     * Resolve attribute value for flat export.
     */
    private function resolveExportAttributeValue($attrValue, $attr, string $lang): ?string
    {
        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float' => $attrValue->value_number !== null ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.') : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null ? ($attrValue->value_flag ? 'true' : 'false') : null,
            'Selection' => $this->resolveExportSelectionValue($attrValue, $lang),
            'Dictionary' => $this->resolveExportDictionaryValue($attrValue, $lang),
            default => $attrValue->value_string,
        };
    }

    private function resolveExportSelectionValue($attrValue, string $lang): ?string
    {
        $entry = $attrValue->valueListEntry;
        if (!$entry) {
            return null;
        }
        return $lang === 'en' && $entry->display_value_en
            ? $entry->display_value_en
            : $entry->display_value_de;
    }

    private function resolveExportDictionaryValue($attrValue, string $lang): ?string
    {
        $entry = $attrValue->dictionaryEntry;
        if (!$entry) {
            return null;
        }
        return $lang === 'en' && $entry->short_text_en
            ? $entry->short_text_en
            : $entry->short_text_de;
    }

    /**
     * GET /api/v1/catalog/categories
     *
     * Category tree with product counts.
     */
    public function categories(Request $request): JsonResponse
    {
        $type = $request->query('type', 'master');
        $hierarchyId = $request->query('hierarchy_id');
        $lang = $request->query('lang', 'de');

        // Fall back to hierarchy_id from catalog theme settings if not passed as param
        if (!$hierarchyId) {
            $themePayload = Setting::getPayload('catalog_theme') ?? [];
            $hierarchyId = $themePayload['hierarchy_id'] ?? null;
        }

        $hierarchyQuery = Hierarchy::where('hierarchy_type', $type);
        if ($hierarchyId) {
            $hierarchyQuery->where('id', $hierarchyId);
        }
        $hierarchy = $hierarchyQuery->first();

        if (!$hierarchy) {
            return response()->json([
                'data' => [
                    'hierarchy_id' => null,
                    'hierarchy_name' => null,
                    'type' => $type,
                    'nodes' => [],
                ],
            ]);
        }

        // Load all active nodes for this hierarchy
        $allNodes = $hierarchy->nodes()
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->get();

        // Count active products per node (including descendants)
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

        return response()->json([
            'data' => [
                'hierarchy_id' => $hierarchy->id,
                'hierarchy_name' => $lang === 'en' && $hierarchy->name_en ? $hierarchy->name_en : $hierarchy->name_de,
                'type' => $type,
                'nodes' => $buildTree($rootNodes)->toArray(),
            ],
        ]);
    }

    /**
     * GET /api/v1/catalog/media/{filename}
     *
     * Serve media files publicly (no auth).
     * For images, serves a thumbnail (600x600) for performance.
     * Use ?original=1 to get the full-size file.
     */
    public function media(Request $request, string $filename): BinaryFileResponse
    {
        $media = Media::where('file_name', $filename)->latest()->firstOrFail();

        // For images, serve thumbnail by default (much faster for catalog grids)
        if (str_starts_with($media->mime_type, 'image/') && !$request->query('original')) {
            $thumbPath = app(\App\Services\ThumbnailService::class)->generate($media, 600, 600);
            if ($thumbPath && file_exists($thumbPath)) {
                return response()->file($thumbPath, [
                    'Content-Type' => $media->mime_type,
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
        }

        $path = Storage::disk('public')->path($media->file_path);

        if (!file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->file($path, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * GET /api/v1/catalog/facets
     *
     * Returns available facet filters based on configured attributes.
     */
    public function facets(Request $request): JsonResponse
    {
        $lang = $request->query('lang', 'de');
        $themePayload = Setting::getPayload('catalog_theme') ?? [];
        $facetAttributeIds = $themePayload['facet_attribute_ids'] ?? [];

        if (empty($facetAttributeIds)) {
            return response()->json(['facets' => []]);
        }

        $attributes = Attribute::whereIn('id', $facetAttributeIds)->get()->keyBy('id');

        // Only active products — use subquery to avoid too many placeholders
        $activeProductQuery = Product::where('status', 'active')
            ->where('product_type_ref', 'product')
            ->select('id');

        $facets = [];

        foreach ($facetAttributeIds as $attrId) {
            $attr = $attributes->get($attrId);
            if (!$attr) {
                continue;
            }

            $label = $lang === 'en' && $attr->name_en ? $attr->name_en : ($attr->name_de ?: $attr->technical_name);
            $dataType = $attr->data_type;

            $baseQuery = ProductAttributeValue::where('attribute_id', $attrId)
                ->whereIn('product_id', $activeProductQuery);

            if (in_array($dataType, ['ValueList', 'Selection', 'Dictionary'])) {
                // Get distinct value_list_entry values with counts
                $rows = (clone $baseQuery)
                    ->whereNotNull('value_selection_id')
                    ->select('value_selection_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                    ->groupBy('value_selection_id')
                    ->orderByDesc('cnt')
                    ->limit(50)
                    ->get();

                $entryIds = $rows->pluck('value_selection_id')->toArray();
                $entries = ValueListEntry::whereIn('id', $entryIds)->get()->keyBy('id');

                $values = [];
                foreach ($rows as $row) {
                    $entry = $entries->get($row->value_selection_id);
                    if (!$entry) {
                        continue;
                    }
                    $displayValue = $lang === 'en' && $entry->display_value_en
                        ? $entry->display_value_en
                        : $entry->display_value_de;
                    $values[] = [
                        'value' => $displayValue,
                        'value_id' => $row->value_selection_id,
                        'count' => $row->cnt,
                    ];
                }

                $facets[] = [
                    'attribute_id' => $attrId,
                    'label' => $label,
                    'data_type' => 'ValueList',
                    'values' => $values,
                ];
            } elseif ($dataType === 'Boolean' || $dataType === 'Flag') {
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
            } elseif (in_array($dataType, ['Decimal', 'Integer', 'Number', 'Float'])) {
                $stats = (clone $baseQuery)
                    ->whereNotNull('value_number')
                    ->select(
                        DB::raw('MIN(value_number) as min_val'),
                        DB::raw('MAX(value_number) as max_val'),
                        DB::raw('COUNT(DISTINCT product_id) as cnt')
                    )
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
            } elseif ($dataType === 'Text' || $dataType === 'String') {
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
            }
        }

        return response()->json(['facets' => $facets]);
    }

    /**
     * Count active products per node (including descendants).
     */
    private function getProductCounts($nodes, string $hierarchyType): array
    {
        $counts = [];

        if ($hierarchyType === 'output') {
            // For output hierarchy: count via output_hierarchy_product_assignments
            $nodeIds = $nodes->pluck('id')->toArray();
            $directCounts = OutputHierarchyProductAssignment::query()
                ->join('products', 'products.id', '=', 'output_hierarchy_product_assignments.product_id')
                ->where('products.status', 'active')
                ->whereIn('output_hierarchy_product_assignments.hierarchy_node_id', $nodeIds)
                ->groupBy('output_hierarchy_product_assignments.hierarchy_node_id')
                ->select('output_hierarchy_product_assignments.hierarchy_node_id', DB::raw('COUNT(DISTINCT output_hierarchy_product_assignments.product_id) as cnt'))
                ->pluck('cnt', 'hierarchy_node_id')
                ->toArray();
        } else {
            // For master hierarchy: count via products.master_hierarchy_node_id
            $nodeIds = $nodes->pluck('id')->toArray();
            $directCounts = Product::where('status', 'active')
                ->whereIn('master_hierarchy_node_id', $nodeIds)
                ->groupBy('master_hierarchy_node_id')
                ->select('master_hierarchy_node_id', DB::raw('COUNT(*) as cnt'))
                ->pluck('cnt', 'master_hierarchy_node_id')
                ->toArray();
        }

        // Build a parent map for rolling up counts
        $nodeMap = $nodes->keyBy('id');

        // Assign direct counts
        foreach ($nodes as $node) {
            $counts[$node->id] = $directCounts[$node->id] ?? 0;
        }

        // Roll up: traverse from deepest to shallowest
        $sortedNodes = $nodes->sortByDesc('depth');
        foreach ($sortedNodes as $node) {
            if ($node->parent_node_id && isset($counts[$node->parent_node_id])) {
                $counts[$node->parent_node_id] += $counts[$node->id];
            }
        }

        return $counts;
    }

    /**
     * When an output hierarchy is configured in settings, merge channel-specific
     * attribute values into the product's attributeValues relation.
     * Build allowed attribute IDs from attribute views + hierarchy node assignments.
     */
    private function buildAllowedAttributeIds(Product $product, array $themePayload): ?array
    {
        $attributeViewIds = $themePayload['attribute_view_ids'] ?? [];

        if (empty($attributeViewIds)) {
            return null;
        }

        $viewAttributeIds = AttributeViewAssignment::whereIn('attribute_view_id', $attributeViewIds)
            ->pluck('attribute_id')
            ->unique();

        // Also include hierarchy-assigned attributes (UDX, BMEcat, etc.)
        $hierarchyAttributeIds = collect();
        if ($product->masterHierarchyNode) {
            $hierarchyService = app(HierarchyInheritanceService::class);
            $hierarchyAttributeIds = $hierarchyService->getEffectiveAttributes($product->masterHierarchyNode)
                ->pluck('attribute_id');
        }

        return $viewAttributeIds->merge($hierarchyAttributeIds)
            ->unique()
            ->toArray();
    }

    /**
     * Output values override master values for the same attribute+language+index.
     */
    private function mergeOutputHierarchyValues(Product $product, array $themePayload): void
    {
        $settingsHierarchyId = $themePayload['hierarchy_id'] ?? null;
        if (!$settingsHierarchyId) {
            return;
        }

        $hierarchy = Hierarchy::find($settingsHierarchyId);
        if (!$hierarchy || $hierarchy->hierarchy_type !== 'output') {
            return;
        }

        $channelValues = ProductAttributeValue::where('product_id', $product->id)
            ->where('output_hierarchy_id', $settingsHierarchyId)
            ->with(['attribute', 'valueListEntry', 'unit'])
            ->get();

        if ($channelValues->isEmpty()) {
            return;
        }

        $mergeKey = fn ($v) => $v->attribute_id . '|' . ($v->language ?? '') . '|' . ($v->multiplied_index ?? 0);

        $merged = $product->attributeValues
            ->filter(fn ($v) => $v->output_hierarchy_id === null)
            ->keyBy($mergeKey);

        foreach ($channelValues as $cv) {
            $merged->put($mergeKey($cv), $cv);
        }

        $product->setRelation('attributeValues', $merged->values());
    }

    // ── Catalog PDF / Export / Compare ──────────────────────────────────

    /**
     * GET /api/v1/catalog/products/{product}/pdf
     *
     * Generate a PDF for a single product using the configured template.
     */
    public function productPdf(string $productId, PdfTemplateService $pdfTemplateService): \Illuminate\Http\Response|JsonResponse
    {
        $themePayload = Setting::getPayload('catalog_theme') ?? [];

        if (empty($themePayload['catalog_pdf_enabled'])) {
            return response()->json(['message' => 'PDF-Download ist nicht aktiviert.'], 403);
        }

        $templateId = $themePayload['catalog_pdf_template_id'] ?? null;
        if (!$templateId) {
            return response()->json(['message' => 'Keine PDF-Vorlage konfiguriert.'], 422);
        }

        $template = PdfTemplate::find($templateId);
        if (!$template) {
            return response()->json(['message' => 'PDF-Vorlage nicht gefunden.'], 404);
        }

        $product = Product::where('status', 'active')->findOrFail($productId);
        $lang = request()->query('lang', 'de');

        $pdfContent = $pdfTemplateService->generateForProduct($template, $product, $lang);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . ($product->sku ?? $product->id) . '.pdf"',
        ]);
    }

    /**
     * POST /api/v1/catalog/wishlist/pdf
     *
     * Generate a combined PDF for multiple products (client-side wishlist).
     */
    public function wishlistPdf(Request $request, PdfTemplateService $pdfTemplateService): \Illuminate\Http\Response|JsonResponse
    {
        $themePayload = Setting::getPayload('catalog_theme') ?? [];

        if (empty($themePayload['catalog_pdf_enabled'])) {
            return response()->json(['message' => 'PDF-Download ist nicht aktiviert.'], 403);
        }

        $templateId = $themePayload['catalog_pdf_template_id'] ?? null;
        if (!$templateId) {
            return response()->json(['message' => 'Keine PDF-Vorlage konfiguriert.'], 422);
        }

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:50',
            'product_ids.*' => 'required|string',
            'lang' => 'sometimes|string|max:5',
        ]);

        $template = PdfTemplate::find($templateId);
        if (!$template) {
            return response()->json(['message' => 'PDF-Vorlage nicht gefunden.'], 404);
        }

        $lang = $validated['lang'] ?? 'de';
        $products = Product::where('status', 'active')
            ->whereIn('id', $validated['product_ids'])
            ->get();

        if ($products->isEmpty()) {
            return response()->json(['message' => 'Keine Produkte gefunden.'], 404);
        }

        $result = $pdfTemplateService->generateForProducts($template, $products, 'combined', $lang);

        $content = file_get_contents($result['path']);
        @unlink($result['path']);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="merkliste-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    /**
     * POST /api/v1/catalog/wishlist/excel
     *
     * Generate an Excel export for multiple products (client-side wishlist).
     */
    public function wishlistExcel(Request $request): StreamedResponse|JsonResponse
    {
        $themePayload = Setting::getPayload('catalog_theme') ?? [];

        if (empty($themePayload['catalog_excel_export_enabled'])) {
            return response()->json(['message' => 'Excel-Export ist nicht aktiviert.'], 403);
        }

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:50',
            'product_ids.*' => 'required|string',
        ]);

        $products = Product::where('status', 'active')
            ->with('productType')
            ->whereIn('id', $validated['product_ids'])
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Merkliste');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
        ];

        $sheet->setCellValue('A1', 'SKU');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Status');
        $sheet->setCellValue('D1', 'Produkttyp');
        $sheet->setCellValue('E1', 'EAN');
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($products as $p) {
            $sheet->setCellValue("A{$row}", $p->sku ?? '-');
            $sheet->setCellValue("B{$row}", $p->name ?? '-');
            $sheet->setCellValue("C{$row}", $p->status ?? '-');
            $sheet->setCellValue("D{$row}", $p->productType?->name_de ?? '-');
            $sheet->setCellValue("E{$row}", $p->ean ?? '-');
            $row++;
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'merkliste-' . now()->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * POST /api/v1/catalog/products/compare
     *
     * Compare 2-3 products across all attributes.
     */
    public function compareProducts(Request $request): JsonResponse
    {
        $themePayload = Setting::getPayload('catalog_theme') ?? [];

        if (empty($themePayload['catalog_compare_enabled'])) {
            return response()->json(['message' => 'Produktvergleich ist nicht aktiviert.'], 403);
        }

        $maxProducts = (int) ($themePayload['catalog_compare_max_products'] ?? 3);

        $validated = $request->validate([
            'product_ids' => "required|array|min:2|max:{$maxProducts}",
            'product_ids.*' => 'required|string',
            'lang' => 'sometimes|string|max:5',
        ]);

        $lang = $validated['lang'] ?? 'de';
        $ids = $validated['product_ids'];

        $products = Product::where('status', 'active')
            ->with('productType')
            ->whereIn('id', $ids)
            ->get();

        if ($products->count() < 2) {
            return response()->json(['message' => 'Mindestens 2 aktive Produkte benötigt.'], 404);
        }

        // Load attribute values for all products
        $productMaps = [];
        foreach ($products as $product) {
            $vals = $product->attributeValues()
                ->with('attribute')
                ->where(function ($q) use ($lang) {
                    $q->whereNull('language')->orWhere('language', $lang);
                })
                ->get();

            $map = [];
            foreach ($vals as $v) {
                $value = $v->value_string ?? $v->value_number ?? $v->value_date ?? $v->value_flag ?? $v->value_selection_id;
                $map[$v->attribute_id] = [
                    'value' => $value,
                    'attribute_name' => $v->attribute?->name_de ?? $v->attribute?->technical_name ?? 'Unknown',
                    'technical_name' => $v->attribute?->technical_name ?? '',
                    'data_type' => $v->attribute?->data_type ?? '',
                ];
            }
            $productMaps[$product->id] = $map;
        }

        // Merge all attribute IDs
        $allAttrKeys = [];
        foreach ($productMaps as $map) {
            foreach (array_keys($map) as $k) {
                $allAttrKeys[$k] = true;
            }
        }
        $allAttrIds = array_keys($allAttrKeys);

        $rows = [];

        // Base fields
        $baseFields = [
            ['field' => 'sku', 'label' => 'SKU'],
            ['field' => 'name', 'label' => 'Name'],
            ['field' => 'ean', 'label' => 'EAN'],
            ['field' => 'status', 'label' => 'Status'],
        ];

        foreach ($baseFields as $bf) {
            $values = $products->map(fn($p) => (string) ($p->{$bf['field']} ?? ''))->toArray();
            $rows[] = [
                'attribute_name' => $bf['label'],
                'technical_name' => $bf['field'],
                'data_type' => 'base',
                'values' => $values,
                'is_different' => count(array_unique($values)) > 1,
            ];
        }

        // Attribute values
        foreach ($allAttrIds as $attrId) {
            $name = 'Unknown';
            $techName = '';
            $dataType = '';
            $values = [];

            foreach ($products as $product) {
                $entry = $productMaps[$product->id][$attrId] ?? null;
                if ($entry) {
                    $name = $entry['attribute_name'];
                    $techName = $entry['technical_name'];
                    $dataType = $entry['data_type'];
                }
                $values[] = $entry['value'] ?? null;
            }

            $stringValues = array_map(fn($v) => (string) ($v ?? ''), $values);
            $rows[] = [
                'attribute_id' => $attrId,
                'attribute_name' => $name,
                'technical_name' => $techName,
                'data_type' => $dataType,
                'values' => $values,
                'is_different' => count(array_unique($stringValues)) > 1,
            ];
        }

        $productSummaries = $products->map(fn($p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
        ])->values()->toArray();

        return response()->json([
            'data' => [
                'products' => $productSummaries,
                'rows' => $rows,
                'total_differences' => collect($rows)->where('is_different', true)->count(),
                'total_attributes' => count($rows),
            ],
        ]);
    }
}
