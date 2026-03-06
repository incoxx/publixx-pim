<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreOutputHierarchyProductAssignmentRequest;
use App\Http\Resources\Api\V1\OutputHierarchyProductAssignmentResource;
use App\Models\HierarchyNode;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OutputHierarchyProductAssignmentController extends Controller
{
    /**
     * GET /hierarchy-nodes/{hierarchy_node}/output-products
     */
    public function index(Request $request, HierarchyNode $hierarchyNode): AnonymousResourceCollection
    {
        $this->authorize('view', $hierarchyNode);

        $query = $hierarchyNode->outputProductAssignments()
            ->with(['product'])
            ->orderBy('sort_order', 'asc');

        return OutputHierarchyProductAssignmentResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /hierarchy-nodes/{hierarchy_node}/output-products
     */
    public function store(StoreOutputHierarchyProductAssignmentRequest $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        $data = $request->validated();
        $data['hierarchy_node_id'] = $hierarchyNode->id;

        // Auto-assign sort_order if not provided
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ($hierarchyNode->outputProductAssignments()->max('sort_order') ?? -1) + 1;
        }

        $assignment = OutputHierarchyProductAssignment::create($data);

        return (new OutputHierarchyProductAssignmentResource($assignment->load('product')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /output-hierarchy-product-assignments/{assignment}
     */
    public function destroy(OutputHierarchyProductAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment->hierarchyNode);

        $assignment->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /products/{product}/output-hierarchy-assignments
     */
    public function productAssignments(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = $product->outputHierarchyAssignments()
            ->with(['hierarchyNode.hierarchy'])
            ->orderBy('sort_order', 'asc');

        return OutputHierarchyProductAssignmentResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * PUT /hierarchy-nodes/{hierarchy_node}/output-products/sort
     */
    public function bulkSort(Request $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|uuid',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->input('items') as $item) {
            OutputHierarchyProductAssignment::where('id', $item['id'])
                ->where('hierarchy_node_id', $hierarchyNode->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Sort order updated']);
    }
}
