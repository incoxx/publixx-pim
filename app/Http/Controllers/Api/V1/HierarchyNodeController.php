<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHierarchyNodeRequest;
use App\Http\Requests\Api\V1\UpdateHierarchyNodeRequest;
use App\Http\Requests\Api\V1\MoveHierarchyNodeRequest;
use App\Http\Resources\Api\V1\HierarchyNodeResource;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HierarchyNodeController extends Controller
{
    private const ALLOWED_INCLUDES = ['children', 'parent', 'attributeAssignments'];

    /**
     * GET /hierarchies/{hierarchy}/nodes — flat list of nodes.
     */
    public function index(Request $request, Hierarchy $hierarchy): AnonymousResourceCollection
    {
        $this->authorize('viewAny', HierarchyNode::class);

        $query = $hierarchy->nodes()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySorting($query, $request, 'path', 'asc');

        return HierarchyNodeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /hierarchies/{hierarchy}/nodes — create a new node.
     */
    public function store(StoreHierarchyNodeRequest $request, Hierarchy $hierarchy): JsonResponse
    {
        $this->authorize('create', HierarchyNode::class);

        $data = $request->validated();
        $data['hierarchy_id'] = $hierarchy->id;

        // Calculate path and depth from parent
        if (!empty($data['parent_node_id'])) {
            $parent = HierarchyNode::findOrFail($data['parent_node_id']);
            $data['depth'] = $parent->depth + 1;
            // path will be set in model boot or observer if needed
        } else {
            $data['depth'] = 0;
        }

        $node = HierarchyNode::create($data);

        // Build materialized path
        $node->path = $this->buildPath($node);
        $node->save();

        return (new HierarchyNodeResource($node))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, HierarchyNode $hierarchyNode): HierarchyNodeResource
    {
        $this->authorize('view', $hierarchyNode);

        $hierarchyNode->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new HierarchyNodeResource($hierarchyNode);
    }

    public function update(UpdateHierarchyNodeRequest $request, HierarchyNode $hierarchyNode): HierarchyNodeResource
    {
        $this->authorize('update', $hierarchyNode);

        $hierarchyNode->update($request->validated());

        return new HierarchyNodeResource($hierarchyNode->fresh());
    }

    public function destroy(HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('delete', $hierarchyNode);

        // Delete cascades to children via DB constraint or manual deletion
        HierarchyNode::where('path', 'LIKE', $hierarchyNode->path . '%')
            ->where('id', '!=', $hierarchyNode->id)
            ->delete();

        $hierarchyNode->delete();

        return response()->json(null, 204);
    }

    /**
     * PUT /hierarchy-nodes/{node}/move — move node to a new parent / sort position.
     */
    public function move(MoveHierarchyNodeRequest $request, HierarchyNode $hierarchyNode): HierarchyNodeResource
    {
        $this->authorize('update', $hierarchyNode);

        $data = $request->validated();

        $oldPath = $hierarchyNode->path;

        $hierarchyNode->parent_node_id = $data['parent_node_id'] ?? null;
        $hierarchyNode->sort_order = $data['sort_order'] ?? $hierarchyNode->sort_order;

        if ($hierarchyNode->parent_node_id) {
            $parent = HierarchyNode::findOrFail($hierarchyNode->parent_node_id);
            $hierarchyNode->depth = $parent->depth + 1;
        } else {
            $hierarchyNode->depth = 0;
        }

        $hierarchyNode->save();

        $newPath = $this->buildPath($hierarchyNode);
        $hierarchyNode->path = $newPath;
        $hierarchyNode->save();

        // Update all descendants' paths
        HierarchyNode::where('path', 'LIKE', $oldPath . '%')
            ->where('id', '!=', $hierarchyNode->id)
            ->get()
            ->each(function (HierarchyNode $child) use ($oldPath, $newPath) {
                $child->path = str_replace($oldPath, $newPath, $child->path);
                $child->depth = substr_count(trim($child->path, '/'), '/');
                $child->save();
            });

        // Dispatch event for Inheritance Agent
        event(new \App\Events\HierarchyNodeMoved($hierarchyNode));

        return new HierarchyNodeResource($hierarchyNode->fresh());
    }

    private function buildPath(HierarchyNode $node): string
    {
        if ($node->parent_node_id === null) {
            return "/{$node->id}/";
        }

        $parent = HierarchyNode::find($node->parent_node_id);
        return $parent ? "{$parent->path}{$node->id}/" : "/{$node->id}/";
    }
}
