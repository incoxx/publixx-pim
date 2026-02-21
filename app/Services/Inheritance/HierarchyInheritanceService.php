<?php

declare(strict_types=1);

namespace App\Services\Inheritance;

use App\Models\HierarchyNode;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HierarchyInheritanceService
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Get all effective attributes of a hierarchy node (including inherited from ancestors).
     *
     * Uses Materialized Path for efficient ancestor lookup.
     * Respects dont_inherit flag: ancestors' dont_inherit=true attributes are excluded,
     * but the node's own dont_inherit=true attributes ARE included.
     *
     * Sorting: collection_sort → attribute_sort
     *
     * @return Collection<int, object> Collection of attribute assignments with metadata
     */
    public function getEffectiveAttributes(HierarchyNode $node): Collection
    {
        $cacheKey = "hierarchy_node_attributes:{$node->id}";

        try {
            return Cache::tags(["hierarchy_node:{$node->id}"])->remember(
                $cacheKey,
                self::CACHE_TTL,
                fn () => $this->computeEffectiveAttributes($node)
            );
        } catch (\Throwable $e) {
            // Cache driver may not support tags (e.g. file/array) — compute without cache
            return $this->computeEffectiveAttributes($node);
        }
    }

    /**
     * Get all attributes a product inherits via its master hierarchy node.
     *
     * @return Collection<int, object> Empty collection if no master hierarchy node assigned
     */
    public function getProductAttributes(Product $product): Collection
    {
        if (!$product->master_hierarchy_node_id) {
            return collect();
        }

        $node = $product->masterHierarchyNode;

        if (!$node) {
            return collect();
        }

        return $this->getEffectiveAttributes($node);
    }

    /**
     * Get the ancestor node IDs for a given node (ordered by depth, root first).
     *
     * Uses the materialized path column: the node's path contains all ancestor IDs.
     * Path format: /root-id/child-id/grandchild-id/
     *
     * @return array<int, string> Ancestor node IDs ordered by depth
     */
    public function getAncestorIds(HierarchyNode $node): array
    {
        if (empty($node->path)) {
            return [];
        }

        // Path format: /root-id/child-id/this-node-id/
        // Extract all IDs from the path, excluding the node itself
        $pathSegments = array_filter(explode('/', trim($node->path, '/')));

        // Remove the last segment (which is the node itself)
        array_pop($pathSegments);

        return array_values($pathSegments);
    }

    /**
     * Get all descendant node IDs (nodes whose path starts with this node's path).
     *
     * @return Collection<int, string>
     */
    public function getDescendantNodeIds(HierarchyNode $node): Collection
    {
        return HierarchyNode::where('path', 'LIKE', $node->path . '%')
            ->where('id', '!=', $node->id)
            ->pluck('id');
    }

    /**
     * Get all product IDs affected by a hierarchy node change.
     * Includes products assigned to this node AND all descendant nodes.
     *
     * @return Collection<int, string>
     */
    public function getAffectedProductIds(HierarchyNode $node): Collection
    {
        return Product::whereIn('master_hierarchy_node_id', function ($query) use ($node) {
            $query->select('id')
                ->from('hierarchy_nodes')
                ->where('path', 'LIKE', $node->path . '%');
        })->pluck('id');
    }

    /**
     * Invalidate cached attributes for a node and all its descendants.
     */
    public function invalidateNodeCache(HierarchyNode $node): void
    {
        try {
            // Invalidate this node
            Cache::tags(["hierarchy_node:{$node->id}"])->flush();

            // Invalidate all descendant nodes
            $descendantIds = $this->getDescendantNodeIds($node);
            foreach ($descendantIds as $descendantId) {
                Cache::tags(["hierarchy_node:{$descendantId}"])->flush();
            }

            // Invalidate affected products
            $productIds = $this->getAffectedProductIds($node);
            foreach ($productIds as $productId) {
                Cache::tags(["product:{$productId}"])->flush();
            }
        } catch (\Throwable $e) {
            // Cache driver may not support tags — skip invalidation
        }
    }

    /**
     * Compute effective attributes without caching.
     *
     * Algorithm:
     * 1. Load all ancestor nodes (via materialized path) ordered by depth
     * 2. For each ancestor: collect attributes WHERE dont_inherit = false
     * 3. For the node itself: collect ALL attributes (including dont_inherit = true)
     * 4. Later nodes override earlier ones (same attribute_id)
     * 5. Sort by collection_sort → attribute_sort
     */
    private function computeEffectiveAttributes(HierarchyNode $node): Collection
    {
        $ancestorIds = $this->getAncestorIds($node);

        // Build a single query for all ancestor + own node attributes
        $attributes = DB::table('hierarchy_node_attribute_assignments as hnaa')
            ->join('attributes as a', 'a.id', '=', 'hnaa.attribute_id')
            ->join('hierarchy_nodes as hn', 'hn.id', '=', 'hnaa.hierarchy_node_id')
            ->where(function ($query) use ($ancestorIds, $node) {
                // Ancestor attributes: only those with dont_inherit = false
                if (!empty($ancestorIds)) {
                    $query->where(function ($q) use ($ancestorIds) {
                        $q->whereIn('hnaa.hierarchy_node_id', $ancestorIds)
                            ->where('hnaa.dont_inherit', false);
                    });
                }

                // Own node attributes: ALL (including dont_inherit = true)
                $query->orWhere('hnaa.hierarchy_node_id', $node->id);
            })
            ->select([
                'hnaa.id as assignment_id',
                'hnaa.hierarchy_node_id',
                'hnaa.attribute_id',
                'hnaa.collection_name',
                'hnaa.collection_sort',
                'hnaa.attribute_sort',
                'hnaa.dont_inherit',
                'hnaa.access_hierarchy',
                'hnaa.access_product',
                'hnaa.access_variant',
                'a.technical_name as attribute_technical_name',
                'a.name_de as attribute_name_de',
                'a.name_en as attribute_name_en',
                'a.data_type',
                'a.is_translatable',
                'a.is_mandatory',
                'a.is_inheritable',
                'a.is_variant_attribute',
                'a.is_internal',
                'hn.depth as node_depth',
            ])
            ->orderBy('hn.depth', 'asc')
            ->get();

        // Deduplicate: later (deeper) node overrides earlier for same attribute_id
        $attributeMap = [];
        foreach ($attributes as $attr) {
            $attributeMap[$attr->attribute_id] = $attr;
        }

        // Sort by collection_sort → attribute_sort
        return collect(array_values($attributeMap))
            ->sortBy([
                ['collection_sort', 'asc'],
                ['attribute_sort', 'asc'],
            ])
            ->values();
    }
}
