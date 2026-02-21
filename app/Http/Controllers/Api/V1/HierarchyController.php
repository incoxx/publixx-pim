<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHierarchyRequest;
use App\Http\Requests\Api\V1\UpdateHierarchyRequest;
use App\Http\Resources\Api\V1\HierarchyResource;
use App\Http\Resources\Api\V1\HierarchyNodeResource;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HierarchyController extends Controller
{
    private const ALLOWED_INCLUDES = ['nodes'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Hierarchy::class);

        $query = Hierarchy::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(['hierarchy_type'])
        ));
        $this->applySorting($query, $request, 'name_de', 'asc');

        return HierarchyResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreHierarchyRequest $request): JsonResponse
    {
        $this->authorize('create', Hierarchy::class);

        $hierarchy = Hierarchy::create($request->validated());

        return (new HierarchyResource($hierarchy))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Hierarchy $hierarchy): HierarchyResource
    {
        $this->authorize('view', $hierarchy);

        $hierarchy->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new HierarchyResource($hierarchy);
    }

    public function update(UpdateHierarchyRequest $request, Hierarchy $hierarchy): HierarchyResource
    {
        $this->authorize('update', $hierarchy);

        $hierarchy->update($request->validated());

        return new HierarchyResource($hierarchy->fresh());
    }

    public function destroy(Hierarchy $hierarchy): JsonResponse
    {
        $this->authorize('delete', $hierarchy);

        if ($hierarchy->hierarchy_type === 'master') {
            return response()->json([
                'message' => 'Die Master-Hierarchie kann nicht gelöscht werden.',
            ], 422);
        }

        $hierarchy->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /hierarchies/{id}/tree — full tree as nested JSON. ?depth=N limits depth.
     */
    public function tree(Request $request, Hierarchy $hierarchy): JsonResponse
    {
        $this->authorize('view', $hierarchy);

        $maxDepth = (int) $request->query('depth', '0');

        $query = $hierarchy->nodes()
            ->whereNull('parent_node_id')
            ->with('children')
            ->orderBy('sort_order', 'asc');

        $rootNodes = $query->get();

        // Recursive tree building
        $buildTree = function ($nodes, int $currentDepth = 0) use (&$buildTree, $maxDepth) {
            return $nodes->map(function ($node) use (&$buildTree, $maxDepth, $currentDepth) {
                $data = (new HierarchyNodeResource($node))->resolve();

                if ($maxDepth > 0 && $currentDepth >= $maxDepth) {
                    $data['children'] = [];
                } else {
                    $children = $node->children()->orderBy('sort_order', 'asc')->with('children')->get();
                    $data['children'] = $buildTree($children, $currentDepth + 1);
                }

                return $data;
            });
        };

        return response()->json([
            'data' => $buildTree($rootNodes),
        ]);
    }
}
