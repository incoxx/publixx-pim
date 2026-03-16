<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreNodeAttributeAssignmentRequest;
use App\Http\Requests\Api\V1\UpdateNodeAttributeAssignmentRequest;
use App\Http\Requests\Api\V1\BulkSortNodeAttributeAssignmentRequest;
use App\Http\Resources\Api\V1\NodeAttributeAssignmentResource;
use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class NodeAttributeAssignmentController extends Controller
{
    /**
     * GET /hierarchy-nodes/{node}/attributes — assigned attributes (?inherited=true).
     */
    public function index(Request $request, HierarchyNode $hierarchyNode): AnonymousResourceCollection
    {
        $this->authorize('viewAny', HierarchyNodeAttributeAssignment::class);

        $showInherited = filter_var($request->query('inherited', 'false'), FILTER_VALIDATE_BOOLEAN);

        if ($showInherited) {
            // Collect attribute assignments from ancestors (dont_inherit=false) + own node (all)
            $ancestorIds = $this->getAncestorNodeIds($hierarchyNode);

            $all = HierarchyNodeAttributeAssignment::where(function ($q) use ($ancestorIds, $hierarchyNode) {
                    if (!empty($ancestorIds)) {
                        $q->where(function ($sub) use ($ancestorIds) {
                            $sub->whereIn('hierarchy_node_id', $ancestorIds)
                                ->where('dont_inherit', false);
                        });
                    }
                    $q->orWhere('hierarchy_node_id', $hierarchyNode->id);
                })
                ->with(['attribute.valueList.entries', 'hierarchyNode:id,name_de,name_en,depth'])
                ->get();

            // Deduplicate: own node wins; among ancestors, deepest wins
            $ownNodeId = $hierarchyNode->id;
            $ownAttributeIds = $all->where('hierarchy_node_id', $ownNodeId)
                ->pluck('attribute_id')
                ->toArray();

            $ancestorMap = []; // attribute_id => assignment (deepest wins)
            foreach ($all as $assignment) {
                if ($assignment->hierarchy_node_id === $ownNodeId) {
                    continue;
                }
                $attrId = $assignment->attribute_id;
                // Skip if own node has this attribute directly
                if (in_array($attrId, $ownAttributeIds)) {
                    continue;
                }
                $depth = $assignment->hierarchyNode->depth ?? 0;
                if (!isset($ancestorMap[$attrId]) || $depth > ($ancestorMap[$attrId]->hierarchyNode->depth ?? 0)) {
                    $ancestorMap[$attrId] = $assignment;
                }
            }

            // Merge: own assignments (not inherited) + ancestor assignments (inherited)
            $result = collect();
            foreach ($all as $assignment) {
                if ($assignment->hierarchy_node_id === $ownNodeId) {
                    $assignment->is_inherited_assignment = false;
                    $result->push($assignment);
                }
            }
            foreach ($ancestorMap as $assignment) {
                $assignment->is_inherited_assignment = true;
                $result->push($assignment);
            }

            // Sort: own first by collection_sort/attribute_sort, then inherited grouped by source node
            $result = $result->sortBy([
                ['is_inherited_assignment', 'asc'],
                ['collection_sort', 'asc'],
                ['attribute_sort', 'asc'],
            ])->values();

            return NodeAttributeAssignmentResource::collection($result);
        } else {
            $query = $hierarchyNode->attributeAssignments()
                ->with('attribute.valueList.entries')
                ->orderBy('collection_sort', 'asc')
                ->orderBy('attribute_sort', 'asc');
        }

        return NodeAttributeAssignmentResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /hierarchy-nodes/{node}/attributes — assign an attribute.
     */
    public function store(StoreNodeAttributeAssignmentRequest $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('create', HierarchyNodeAttributeAssignment::class);

        $data = $request->validated();
        $data['hierarchy_node_id'] = $hierarchyNode->id;

        // Auto-set is_required for globally mandatory attributes
        $attribute = Attribute::find($data['attribute_id']);
        if ($attribute && $attribute->is_mandatory) {
            $data['is_required'] = true;
        }

        $assignment = HierarchyNodeAttributeAssignment::create($data);

        // Notify Inheritance Agent
        event(new \App\Events\HierarchyAttributeChanged($hierarchyNode->id, $data['attribute_id']));
        if ($attribute && $attribute->data_type === 'Composite') {
            $this->autoAssignCompositeChildren($assignment, $attribute, $hierarchyNode);
        }

        return (new NodeAttributeAssignmentResource($assignment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /node-attribute-assignments/{id} — update assignment.
     */
    public function update(UpdateNodeAttributeAssignmentRequest $request, HierarchyNodeAttributeAssignment $nodeAttributeAssignment): NodeAttributeAssignmentResource
    {
        $this->authorize('update', $nodeAttributeAssignment);

        $nodeAttributeAssignment->update($request->validated());

        return new NodeAttributeAssignmentResource($nodeAttributeAssignment->fresh());
    }

    /**
     * DELETE /node-attribute-assignments/{id} — remove assignment.
     */
    public function destroy(HierarchyNodeAttributeAssignment $nodeAttributeAssignment): JsonResponse
    {
        $this->authorize('delete', $nodeAttributeAssignment);

        $nodeId = $nodeAttributeAssignment->hierarchy_node_id;
        $attrId = $nodeAttributeAssignment->attribute_id;

        // Collect child assignment attribute IDs for events (before cascade delete)
        $childAttrIds = $nodeAttributeAssignment->childAssignments()->pluck('attribute_id')->all();

        $nodeAttributeAssignment->delete();

        event(new \App\Events\HierarchyAttributeChanged($nodeId, $attrId));

        // Fire events for cascade-deleted child assignments
        foreach ($childAttrIds as $childAttrId) {
            event(new \App\Events\HierarchyAttributeChanged($nodeId, $childAttrId));
        }

        return response()->json(null, 204);
    }

    /**
     * PUT /node-attribute-assignments/bulk-sort — reorder assignments via drag & drop.
     */
    public function bulkSort(BulkSortNodeAttributeAssignmentRequest $request): JsonResponse
    {
        $this->authorize('update', HierarchyNodeAttributeAssignment::class);

        $items = $request->validated('items');

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                HierarchyNodeAttributeAssignment::where('id', $item['id'])
                    ->update([
                        'collection_sort' => $item['collection_sort'] ?? 0,
                        'attribute_sort' => $item['attribute_sort'] ?? 0,
                    ]);
            }
        });

        return response()->json(['message' => 'Sort order updated.']);
    }

    /**
     * Auto-assign all child attributes of a Composite to the same hierarchy node.
     */
    private function autoAssignCompositeChildren(
        HierarchyNodeAttributeAssignment $parentAssignment,
        Attribute $compositeAttribute,
        HierarchyNode $node,
    ): void {
        $children = $compositeAttribute->childAttributes()->orderBy('position')->get();
        $sortIndex = 1;

        foreach ($children as $child) {
            HierarchyNodeAttributeAssignment::create([
                'hierarchy_node_id' => $node->id,
                'attribute_id' => $child->id,
                'collection_name' => $parentAssignment->collection_name,
                'collection_sort' => $parentAssignment->collection_sort,
                'attribute_sort' => ($parentAssignment->attribute_sort ?? 0) + $sortIndex,
                'dont_inherit' => $parentAssignment->dont_inherit ?? false,
                'access_hierarchy' => $parentAssignment->access_hierarchy,
                'access_product' => $parentAssignment->access_product,
                'access_variant' => $parentAssignment->access_variant,
                'parent_assignment_id' => $parentAssignment->id,
            ]);

            event(new \App\Events\HierarchyAttributeChanged($node->id, $child->id));

            $sortIndex++;
        }
    }

    private function getAncestorNodeIds(HierarchyNode $node): array
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
}
