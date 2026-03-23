<?php

declare(strict_types=1);

namespace App\Services\Inheritance;

use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\OutputHierarchyProductAssignment;
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
     * Get all output hierarchy attributes for a product, grouped by hierarchy.
     *
     * For each output hierarchy the product is assigned to, computes the effective
     * attributes from the assigned node(s). If a product is assigned to multiple
     * nodes within the same hierarchy, attributes are merged (deepest node wins).
     *
     * @return Collection<string, array{hierarchy: Hierarchy, attributes: Collection}>
     *         Keyed by hierarchy ID
     */
    public function getProductOutputHierarchyAttributes(Product $product): Collection
    {
        $assignments = OutputHierarchyProductAssignment::where('product_id', $product->id)
            ->with(['hierarchyNode.hierarchy'])
            ->get();

        if ($assignments->isEmpty()) {
            return collect();
        }

        // Group assignments by hierarchy
        $byHierarchy = $assignments->groupBy(fn ($a) => $a->hierarchyNode->hierarchy_id);

        return $byHierarchy->map(function (Collection $hierarchyAssignments, string $hierarchyId) {
            $hierarchy = $hierarchyAssignments->first()->hierarchyNode->hierarchy;

            // Collect effective attributes from all assigned nodes within this hierarchy
            $allAttributes = collect();
            foreach ($hierarchyAssignments as $assignment) {
                $nodeAttributes = $this->getEffectiveAttributes($assignment->hierarchyNode);
                foreach ($nodeAttributes as $attr) {
                    // Deepest node wins for same attribute_id
                    $existing = $allAttributes->get($attr->attribute_id);
                    if (!$existing || ($attr->node_depth ?? 0) >= ($existing->node_depth ?? 0)) {
                        $allAttributes->put($attr->attribute_id, $attr);
                    }
                }
            }

            return [
                'hierarchy' => $hierarchy,
                'attributes' => $allAttributes->values()
                    ->sortBy([
                        ['collection_sort', 'asc'],
                        ['attribute_sort', 'asc'],
                    ])
                    ->values(),
            ];
        });
    }

    /**
     * Get the ancestor node IDs for a given node by walking the parent_node_id chain.
     *
     * @return array<int, string> Ancestor node IDs (unordered)
     */
    public function getAncestorIds(HierarchyNode $node): array
    {
        $ids = [];
        $current = $node->parent_node_id;

        while ($current !== null) {
            $ids[] = $current;
            $parent = HierarchyNode::select('id', 'parent_node_id')->find($current);
            $current = $parent?->parent_node_id;
        }

        return $ids;
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
        // Left join attribute_views to use view name as fallback for collection_name
        $attributes = DB::table('hierarchy_node_attribute_assignments as hnaa')
            ->join('attributes as a', 'a.id', '=', 'hnaa.attribute_id')
            ->join('hierarchy_nodes as hn', 'hn.id', '=', 'hnaa.hierarchy_node_id')
            ->leftJoin('attribute_view_assignments as ava', 'ava.attribute_id', '=', 'a.id')
            ->leftJoin('attribute_views as av', 'av.id', '=', 'ava.attribute_view_id')
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
                'hnaa.is_required',
                'a.technical_name as attribute_technical_name',
                'a.name_de as attribute_name_de',
                'a.name_en as attribute_name_en',
                'a.data_type',
                'a.is_translatable',
                'a.is_mandatory',
                'a.is_inheritable',
                'a.is_variant_attribute',
                'a.is_internal',
                'a.is_multipliable',
                'a.max_multiplied',
                'a.attribute_type_id',
                'a.parent_attribute_id',
                'a.value_list_id',
                'a.unit_group_id',
                'a.default_unit_id',
                'a.composite_format',
                'hnaa.parent_assignment_id',
                'hn.depth as node_depth',
                DB::raw('MIN(av.name_de) as attribute_view_name_de'),
            ])
            ->groupBy([
                'hnaa.id', 'hnaa.hierarchy_node_id', 'hnaa.attribute_id',
                'hnaa.collection_name', 'hnaa.collection_sort', 'hnaa.attribute_sort',
                'hnaa.dont_inherit', 'hnaa.is_required', 'hnaa.access_hierarchy', 'hnaa.access_product',
                'hnaa.access_variant', 'a.technical_name', 'a.name_de', 'a.name_en',
                'a.data_type', 'a.is_translatable', 'a.is_mandatory', 'a.is_inheritable',
                'a.is_variant_attribute', 'a.is_internal', 'a.is_multipliable', 'a.max_multiplied',
                'a.attribute_type_id', 'a.parent_attribute_id',
                'a.value_list_id', 'a.unit_group_id', 'a.default_unit_id',
                'a.composite_format', 'hnaa.parent_assignment_id', 'hn.depth',
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
