<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CatalogCategoryNodeResource;
use App\Http\Resources\Api\V1\CatalogProductDetailResource;
use App\Http\Resources\Api\V1\CatalogProductResource;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Media;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductSearchIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        // Category filter
        if ($categoryId) {
            $node = HierarchyNode::find($categoryId);
            if ($node) {
                // Build descendant path prefix that includes this node's own ID
                $descendantPrefix = $node->path === '/'
                    ? "/{$node->id}/"
                    : "{$node->path}{$node->id}/";

                // Get descendant node IDs + the node itself
                $descendantIds = HierarchyNode::where('path', 'like', $descendantPrefix . '%')
                    ->pluck('id')
                    ->toArray();
                $descendantIds[] = $node->id;

                if ($hierarchyType === 'output') {
                    $productIds = OutputHierarchyProductAssignment::whereIn('hierarchy_node_id', $descendantIds)
                        ->pluck('product_id');
                    $query->whereIn('products_search_index.product_id', $productIds);
                } else {
                    $query->whereIn('products.master_hierarchy_node_id', $descendantIds);
                }
            }
        }

        // Search
        if ($search && trim($search) !== '') {
            $term = trim($search);
            if (DB::getDriverName() === 'mysql') {
                $query->where(function ($q) use ($term) {
                    $q->whereRaw(
                        'MATCH(products_search_index.name_de, products_search_index.name_en) AGAINST(? IN BOOLEAN MODE)',
                        [$term . '*']
                    )
                    ->orWhere('products_search_index.sku', 'like', '%' . $term . '%')
                    ->orWhere('products_search_index.ean', 'like', '%' . $term . '%')
                    ->orWhere('products_search_index.description_de', 'like', '%' . $term . '%');
                });
            } else {
                $likeTerm = '%' . $term . '%';
                $query->where(function ($q) use ($likeTerm) {
                    $q->where('products_search_index.name_de', 'like', $likeTerm)
                      ->orWhere('products_search_index.name_en', 'like', $likeTerm)
                      ->orWhere('products_search_index.sku', 'like', $likeTerm)
                      ->orWhere('products_search_index.ean', 'like', $likeTerm)
                      ->orWhere('products_search_index.description_de', 'like', $likeTerm);
                });
            }
        }

        // Sorting
        $sortColumn = match ($sortField) {
            'price' => 'products_search_index.list_price',
            'sku' => 'products_search_index.sku',
            'updated_at' => 'products_search_index.updated_at',
            default => $lang === 'en' ? 'products_search_index.name_en' : 'products_search_index.name_de',
        };
        $query->orderBy($sortColumn, $sortOrder);

        $query->select([
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
        ]);

        $paginated = $query->paginate($perPage);

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
                'attributeValues.unit',
                'variants',
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

        return response()->json([
            'data' => (new CatalogProductDetailResource($product))
                ->additional(['lang' => $lang, 'breadcrumb' => $breadcrumb])
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

        return response()->json(
            (new CatalogProductDetailResource($product))
                ->additional(['lang' => $lang, 'breadcrumb' => $breadcrumb])
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
     * Resolve attribute value for flat export.
     */
    private function resolveExportAttributeValue($attrValue, $attr, string $lang): ?string
    {
        return match ($attr->data_type) {
            'String' => $attrValue->value_string,
            'Number', 'Float' => $attrValue->value_number !== null ? rtrim(rtrim((string) $attrValue->value_number, '0'), '.') : null,
            'Date' => $attrValue->value_date?->format('Y-m-d'),
            'Flag' => $attrValue->value_flag !== null ? ($attrValue->value_flag ? 'true' : 'false') : null,
            'Selection', 'Dictionary' => $this->resolveExportSelectionValue($attrValue, $lang),
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
}
