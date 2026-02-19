<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreNodeAttributeAssignmentRequest;
use App\Http\Requests\Api\V1\UpdateNodeAttributeAssignmentRequest;
use App\Http\Requests\Api\V1\BulkSortNodeAttributeAssignmentRequest;
use App\Http\Resources\Api\V1\NodeAttributeAssignmentResource;
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

        $nodeAttributeAssignment->delete();

        event(new \App\Events\HierarchyAttributeChanged($nodeId, $attrId));

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

    private function getAncestorNodeIds(HierarchyNode $node): array
    {
        $ids = [];
        $pathSegments = array_filter(explode('/', trim($node->path, '/')));

        // Remove the last segment (current node)
        array_pop($pathSegments);

        return $pathSegments;
    }
}
