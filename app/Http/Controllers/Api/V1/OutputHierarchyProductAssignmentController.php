<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreOutputHierarchyProductAssignmentRequest;
use App\Http\Resources\Api\V1\OutputHierarchyProductAssignmentResource;
use App\Http\Resources\Api\V1\ProductResource;
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
     * POST /hierarchy-nodes/{hierarchy_node}/master-products
     *
     * Weist ein Produkt diesem Master-Hierarchie-Knoten zu (setzt master_hierarchy_node_id).
     */
    public function assignMasterProduct(Request $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
        ]);

        $product = Product::findOrFail($request->input('product_id'));
        $product->update(['master_hierarchy_node_id' => $hierarchyNode->id]);

        return (new ProductResource($product->fresh()))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * DELETE /hierarchy-nodes/{hierarchy_node}/master-products/{product}
     *
     * Entfernt die Zuordnung eines Produkts von diesem Master-Knoten (setzt master_hierarchy_node_id = null).
     */
    public function removeMasterProduct(HierarchyNode $hierarchyNode, Product $product): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        if ($product->master_hierarchy_node_id === $hierarchyNode->id) {
            $product->update(['master_hierarchy_node_id' => null]);
        }

        return response()->json(null, 204);
    }

    /**
     * POST /hierarchy-nodes/{hierarchy_node}/output-products/bulk-assign
     *
     * Bulk-assign multiple products to an output hierarchy node.
     */
    public function bulkAssign(Request $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        $request->validate([
            'product_ids' => 'required|array|min:1|max:10000',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        $productIds = $request->input('product_ids');

        // Get already assigned product IDs to skip
        $existingIds = $hierarchyNode->outputProductAssignments()
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->toArray();

        $newIds = array_diff($productIds, $existingIds);
        $maxSort = $hierarchyNode->outputProductAssignments()->max('sort_order') ?? -1;
        $created = 0;

        foreach ($newIds as $productId) {
            $maxSort++;
            OutputHierarchyProductAssignment::create([
                'hierarchy_node_id' => $hierarchyNode->id,
                'product_id' => $productId,
                'sort_order' => $maxSort,
            ]);
            $created++;
        }

        return response()->json([
            'message' => "{$created} Produkt(e) zugeordnet.",
            'assigned' => $created,
            'skipped' => count($existingIds),
        ]);
    }

    /**
     * POST /hierarchy-nodes/{hierarchy_node}/master-products/bulk-assign
     *
     * Bulk-assign multiple products to a master hierarchy node.
     */
    public function bulkAssignMaster(Request $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        $request->validate([
            'product_ids' => 'required|array|min:1|max:10000',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        $count = Product::whereIn('id', $request->input('product_ids'))
            ->update(['master_hierarchy_node_id' => $hierarchyNode->id]);

        return response()->json([
            'message' => "{$count} Produkt(e) zugeordnet.",
            'assigned' => $count,
        ]);
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
