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
        $perPage = min((int) $request->query('per_page', '24'), 100);
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
                // Get all descendant node IDs (including the node itself)
                $descendantIds = HierarchyNode::where('path', 'like', $node->path . '%')
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

        return response()->json([
            'data' => CatalogProductResource::collection($paginated->items())
                ->additional(['lang' => $lang])
                ->resolve(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
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
     */
    public function media(string $filename): BinaryFileResponse
    {
        $media = Media::where('file_name', $filename)->firstOrFail();

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
