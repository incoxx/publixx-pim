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
use Illuminate\Support\Facades\DB;

class HierarchyNodeController extends Controller
{
    private const ALLOWED_INCLUDES = ['children', 'parent', 'attributeAssignments', 'attributeValues'];

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

        $node = DB::transaction(function () use ($request, $hierarchy) {
            $data = $request->validated();
            $data['hierarchy_id'] = $hierarchy->id;

            // Calculate path and depth from parent
            if (!empty($data['parent_node_id'])) {
                $parent = HierarchyNode::lockForUpdate()->findOrFail($data['parent_node_id']);
                $data['depth'] = $parent->depth + 1;
            } else {
                $data['depth'] = 0;
            }

            $node = HierarchyNode::create($data);

            // Build materialized path
            $node->path = $this->buildPath($node);
            $node->save();

            return $node;
        });

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

        DB::transaction(function () use ($hierarchyNode) {
            HierarchyNode::where('path', 'LIKE', $hierarchyNode->path . '%')
                ->where('id', '!=', $hierarchyNode->id)
                ->delete();

            $hierarchyNode->delete();
        });

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

        DB::transaction(function () use ($data, $hierarchyNode, $oldPath) {
            $hierarchyNode->parent_node_id = $data['parent_node_id'] ?? null;
            $hierarchyNode->sort_order = $data['sort_order'] ?? $hierarchyNode->sort_order;

            if ($hierarchyNode->parent_node_id) {
                $parent = HierarchyNode::lockForUpdate()->findOrFail($hierarchyNode->parent_node_id);
                $hierarchyNode->depth = $parent->depth + 1;
            } else {
                $hierarchyNode->depth = 0;
            }

            $hierarchyNode->save();

            $newPath = $this->buildPath($hierarchyNode);
            $hierarchyNode->path = $newPath;
            $hierarchyNode->save();

            // Batch-update all descendants' paths
            $descendants = HierarchyNode::where('path', 'LIKE', $oldPath . '%')
                ->where('id', '!=', $hierarchyNode->id)
                ->lockForUpdate()
                ->get();

            foreach ($descendants as $child) {
                $child->path = str_replace($oldPath, $newPath, $child->path);
                $child->depth = substr_count(trim($child->path, '/'), '/');
                $child->save();
            }
        });

        try {
            $newPath = $hierarchyNode->path;
            event(new \App\Events\HierarchyNodeMoved($hierarchyNode, $oldPath !== $newPath ? $hierarchyNode->parent_node_id : null, $oldPath));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('HierarchyNodeMoved event failed', ['error' => $e->getMessage()]);
        }

        return new HierarchyNodeResource($hierarchyNode->fresh());
    }

    /**
     * POST /hierarchy-nodes/{node}/duplicate — deep-copy a node and all descendants.
     */
    public function duplicate(HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('create', HierarchyNode::class);

        $clone = DB::transaction(function () use ($hierarchyNode) {
            return $this->deepCopyNode($hierarchyNode, $hierarchyNode->parent_node_id, $hierarchyNode->hierarchy_id);
        });

        return (new HierarchyNodeResource($clone->load('children')))
            ->response()
            ->setStatusCode(201);
    }

    private function deepCopyNode(HierarchyNode $source, ?string $parentNodeId, string $hierarchyId): HierarchyNode
    {
        $clone = HierarchyNode::create([
            'hierarchy_id'   => $hierarchyId,
            'parent_node_id' => $parentNodeId,
            'name_de'        => $source->name_de . ' (Kopie)',
            'name_en'        => $source->name_en ? $source->name_en . ' (Copy)' : null,
            'name_json'      => $source->name_json,
            'sort_order'     => $source->sort_order + 1,
            'is_active'      => $source->is_active,
            'depth'          => $parentNodeId
                ? (HierarchyNode::find($parentNodeId)?->depth ?? 0) + 1
                : 0,
        ]);

        $clone->path = $this->buildPath($clone);
        $clone->save();

        // Copy attribute assignments
        foreach ($source->attributeAssignments()->whereNull('parent_assignment_id')->get() as $assignment) {
            $newAssignment = $clone->attributeAssignments()->create([
                'attribute_id'      => $assignment->attribute_id,
                'collection_name'   => $assignment->collection_name,
                'collection_sort'   => $assignment->collection_sort,
                'attribute_sort'    => $assignment->attribute_sort,
                'dont_inherit'      => $assignment->dont_inherit,
                'access_hierarchy'  => $assignment->access_hierarchy,
                'access_product'    => $assignment->access_product,
                'access_variant'    => $assignment->access_variant,
            ]);

            // Copy child assignments (for composite attributes)
            foreach ($assignment->childAssignments as $childAssignment) {
                $clone->attributeAssignments()->create([
                    'attribute_id'         => $childAssignment->attribute_id,
                    'collection_name'      => $childAssignment->collection_name,
                    'collection_sort'      => $childAssignment->collection_sort,
                    'attribute_sort'       => $childAssignment->attribute_sort,
                    'dont_inherit'         => $childAssignment->dont_inherit,
                    'access_hierarchy'     => $childAssignment->access_hierarchy,
                    'access_product'       => $childAssignment->access_product,
                    'access_variant'       => $childAssignment->access_variant,
                    'parent_assignment_id' => $newAssignment->id,
                ]);
            }
        }

        // Recursively copy children
        foreach ($source->children()->orderBy('sort_order')->get() as $child) {
            $this->deepCopyNode($child, $clone->id, $hierarchyId);
        }

        return $clone;
    }

    private function buildPath(HierarchyNode $node): string
    {
        if ($node->parent_node_id === null) {
            return "/{$node->id}/";
        }

        $parent = HierarchyNode::findOrFail($node->parent_node_id);
        return "{$parent->path}{$node->id}/";
    }
}
