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
            // Collect attribute assignments from this node and all ancestors
            $nodeIds = $this->getAncestorNodeIds($hierarchyNode);
            $nodeIds[] = $hierarchyNode->id;

            $query = HierarchyNodeAttributeAssignment::whereIn('hierarchy_node_id', $nodeIds)
                ->where('dont_inherit', false)
                ->with('attribute')
                ->orderBy('collection_sort', 'asc')
                ->orderBy('attribute_sort', 'asc');
        } else {
            $query = $hierarchyNode->attributeAssignments()
                ->with('attribute')
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

        $assignment = HierarchyNodeAttributeAssignment::create($data);

        // Notify Inheritance Agent
        event(new \App\Events\HierarchyAttributeChanged($hierarchyNode->id, $data['attribute_id']));

        // Auto-assign child attributes for Composite attributes
        $attribute = Attribute::find($data['attribute_id']);
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
        $pathSegments = array_filter(explode('/', trim($node->path, '/')));

        // Remove the last segment (current node)
        array_pop($pathSegments);

        return $pathSegments;
    }
}
