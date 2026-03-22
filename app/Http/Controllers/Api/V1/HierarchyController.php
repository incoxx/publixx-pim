<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHierarchyRequest;
use App\Http\Requests\Api\V1\UpdateHierarchyRequest;
use App\Http\Resources\Api\V1\HierarchyResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Http\Traits\FiltersByInstanceRestrictions;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class HierarchyController extends Controller
{
    use ChecksDeletionConstraints, FiltersByInstanceRestrictions;

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

    public function dependencies(Hierarchy $hierarchy): JsonResponse
    {
        $this->authorize('view', $hierarchy);

        return $this->dependenciesResponse($hierarchy);
    }

    public function destroy(Request $request, Hierarchy $hierarchy): JsonResponse
    {
        $this->authorize('delete', $hierarchy);

        if (!$request->boolean('force')) {
            return $this->destroyWithConstraintCheck($request, $hierarchy);
        }

        DB::transaction(function () use ($hierarchy) {
            $this->cascadeDeleteHierarchy($hierarchy);
        });

        return response()->json(null, 204);
    }

    private function cascadeDeleteHierarchy(Hierarchy $hierarchy): void
    {
        $nodeIds = HierarchyNode::where('hierarchy_id', $hierarchy->id)->pluck('id');

        if ($nodeIds->isNotEmpty()) {
            // Nullify products' master_hierarchy_node_id
            Product::whereIn('master_hierarchy_node_id', $nodeIds)
                ->update(['master_hierarchy_node_id' => null]);

            // Delete attribute assignments and values on nodes
            DB::table('hierarchy_node_attribute_assignments')
                ->whereIn('hierarchy_node_id', $nodeIds)
                ->delete();
            DB::table('hierarchy_node_attribute_values')
                ->whereIn('hierarchy_node_id', $nodeIds)
                ->delete();

            // Delete output product assignments
            DB::table('output_hierarchy_product_assignments')
                ->whereIn('hierarchy_node_id', $nodeIds)
                ->delete();

            // Delete all nodes
            HierarchyNode::where('hierarchy_id', $hierarchy->id)->delete();
        }

        // Delete hierarchy-level attribute assignments
        DB::table('hierarchy_attribute_assignments')
            ->where('hierarchy_id', $hierarchy->id)
            ->delete();

        $hierarchy->delete();
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
            ->orderBy('sort_order', 'asc');

        $this->applyNodeInstanceRestrictionFilter($query);

        $rootNodes = $query->get();

        $buildTree = function ($nodes, int $currentDepth = 0) use (&$buildTree, $maxDepth) {
            return $nodes->map(function (HierarchyNode $node) use (&$buildTree, $maxDepth, $currentDepth) {
                $data = [
                    'id' => $node->id,
                    'hierarchy_id' => $node->hierarchy_id,
                    'parent_node_id' => $node->parent_node_id,
                    'name_de' => $node->name_de,
                    'name_en' => $node->name_en,
                    'name_json' => $node->name_json,
                    'path' => $node->path,
                    'depth' => $node->depth,
                    'sort_order' => $node->sort_order,
                    'is_active' => $node->is_active,
                    'created_at' => $node->created_at,
                    'updated_at' => $node->updated_at,
                ];

                if ($maxDepth > 0 && $currentDepth >= $maxDepth) {
                    $data['children'] = [];
                } else {
                    $children = $node->children()->orderBy('sort_order', 'asc')->get();
                    $data['children'] = $buildTree($children, $currentDepth + 1);
                }

                return $data;
            })->values();
        };

        return response()->json([
            'data' => $buildTree($rootNodes),
        ]);
    }
}
