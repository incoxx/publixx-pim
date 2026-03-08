<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyAttributeAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class HierarchyAttributeAssignmentController extends Controller
{
    /**
     * GET /hierarchies/{hierarchy}/attributes
     */
    public function index(Request $request, Hierarchy $hierarchy): AnonymousResourceCollection
    {
        $this->authorize('view', $hierarchy);

        $assignments = $hierarchy->attributeAssignments()
            ->with('attribute')
            ->orderBy('sort_order')
            ->get();

        return JsonResource::collection($assignments);
    }

    /**
     * POST /hierarchies/{hierarchy}/attributes
     */
    public function store(Request $request, Hierarchy $hierarchy): JsonResponse
    {
        $this->authorize('update', $hierarchy);

        $data = $request->validate([
            'attribute_id' => 'required|uuid|exists:attributes,id',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $exists = $hierarchy->attributeAssignments()
            ->where('attribute_id', $data['attribute_id'])
            ->exists();

        if ($exists) {
            abort(422, 'Dieses Attribut ist bereits dieser Hierarchie zugeordnet.');
        }

        $maxSort = $hierarchy->attributeAssignments()->max('sort_order') ?? -1;

        $assignment = HierarchyAttributeAssignment::create([
            'hierarchy_id' => $hierarchy->id,
            'attribute_id' => $data['attribute_id'],
            'sort_order' => $data['sort_order'] ?? $maxSort + 1,
        ]);

        $assignment->load('attribute');

        return (new JsonResource($assignment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /hierarchy-attribute-assignments/{hierarchy_attribute_assignment}
     */
    public function destroy(HierarchyAttributeAssignment $hierarchyAttributeAssignment): JsonResponse
    {
        $this->authorize('update', $hierarchyAttributeAssignment->hierarchy);

        $hierarchyAttributeAssignment->delete();

        return response()->json(null, 204);
    }
}
