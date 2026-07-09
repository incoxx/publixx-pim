<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\HierarchyAttributeAssignmentResource;
use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyAttributeAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class HierarchyAttributeAssignmentController extends Controller
{
    /**
     * GET /hierarchies/{hierarchy}/attributes
     */
    public function index(Request $request, Hierarchy $hierarchy): AnonymousResourceCollection
    {
        $this->authorize('view', $hierarchy);

        $assignments = $hierarchy->attributeAssignments()
            ->with('attribute.valueList.entries')
            ->orderBy('sort_order')
            ->get();

        return HierarchyAttributeAssignmentResource::collection($assignments);
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
            'scope' => 'sometimes|string|in:node,relationship,both',
            'is_facet' => 'sometimes|boolean',
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
            'scope' => $data['scope'] ?? 'node',
            'is_facet' => $data['is_facet'] ?? false,
        ]);

        $assignment->load('attribute.valueList.entries');

        return (new HierarchyAttributeAssignmentResource($assignment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /hierarchies/{hierarchy}/all-node-attributes
     *
     * Returns all unique attributes assigned to any node within this hierarchy,
     * plus hierarchy-level attribute assignments.
     */
    public function allNodeAttributes(Hierarchy $hierarchy): JsonResponse
    {
        $nodeIds = $hierarchy->nodes()->pluck('id');

        $nodeAttributeIds = DB::table('hierarchy_node_attribute_assignments')
            ->whereIn('hierarchy_node_id', $nodeIds)
            ->distinct()
            ->pluck('attribute_id');

        $hierarchyAttributeIds = $hierarchy->attributeAssignments()
            ->pluck('attribute_id');

        $allAttributeIds = $nodeAttributeIds->merge($hierarchyAttributeIds)->unique();

        $attributes = Attribute::whereIn('id', $allAttributeIds)
            ->where('is_internal', false)
            ->orderBy('name_de')
            ->get();

        return response()->json(['data' => $attributes]);
    }

    /**
     * PATCH /hierarchy-attribute-assignments/{hierarchy_attribute_assignment}
     */
    public function update(Request $request, HierarchyAttributeAssignment $hierarchyAttributeAssignment): JsonResponse
    {
        $this->authorize('update', $hierarchyAttributeAssignment->hierarchy);

        $data = $request->validate([
            'sort_order' => 'sometimes|integer|min:0',
            'scope' => 'sometimes|string|in:node,relationship,both',
            'is_facet' => 'sometimes|boolean',
        ]);

        $hierarchyAttributeAssignment->update($data);
        $hierarchyAttributeAssignment->load('attribute.valueList.entries');

        return (new HierarchyAttributeAssignmentResource($hierarchyAttributeAssignment))->response();
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
