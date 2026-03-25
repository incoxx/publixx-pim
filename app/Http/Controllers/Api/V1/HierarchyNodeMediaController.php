<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHierarchyNodeMediaRequest;
use App\Http\Resources\Api\V1\HierarchyNodeMediaResource;
use App\Http\Traits\AuditsChanges;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeMediaAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HierarchyNodeMediaController extends Controller
{
    use AuditsChanges;

    /**
     * GET /hierarchy-nodes/{hierarchy_node}/media — zugeordnete Medien eines Knotens.
     */
    public function index(Request $request, HierarchyNode $hierarchyNode): AnonymousResourceCollection
    {
        $this->authorize('view', $hierarchyNode);

        $query = $hierarchyNode->mediaAssignments()
            ->with(['media', 'usageType'])
            ->orderBy('sort_order', 'asc');

        return HierarchyNodeMediaResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /hierarchy-nodes/{hierarchy_node}/media — Medium dem Knoten zuordnen.
     */
    public function store(StoreHierarchyNodeMediaRequest $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        $assignment = $hierarchyNode->mediaAssignments()->create($request->validated());

        $this->audit('media_assigned', 'HierarchyNode', $hierarchyNode->id, null, [
            'assignment_id' => $assignment->id,
            'media_id' => $assignment->media_id,
            'usage_type_id' => $assignment->usage_type_id,
        ]);

        return (new HierarchyNodeMediaResource($assignment->load(['media', 'usageType'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /hierarchy-node-media/{id} — Zuordnung entfernen.
     */
    public function destroy(HierarchyNodeMediaAssignment $hierarchyNodeMedium): JsonResponse
    {
        $this->authorize('update', $hierarchyNodeMedium->hierarchyNode);

        $snapshot = [
            'assignment_id' => $hierarchyNodeMedium->id,
            'media_id' => $hierarchyNodeMedium->media_id,
            'usage_type_id' => $hierarchyNodeMedium->usage_type_id,
        ];
        $nodeId = $hierarchyNodeMedium->hierarchy_node_id;

        $hierarchyNodeMedium->delete();

        $this->audit('media_removed', 'HierarchyNode', $nodeId, $snapshot);

        return response()->json(null, 204);
    }
}
