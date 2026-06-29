<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreNavigationRequest;
use App\Http\Requests\Api\V1\UpdateNavigationRequest;
use App\Http\Resources\Api\V1\NavigationResource;
use App\Http\Traits\Filterable;
use App\Models\Navigation;
use App\Models\NavigationNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NavigationController extends Controller
{
    use Filterable;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Navigation::class);

        $query = Navigation::query()->withCount('nodes');
        $this->applySorting($query, $request, 'name_de', 'asc');

        return NavigationResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreNavigationRequest $request): JsonResponse
    {
        $this->authorize('create', Navigation::class);

        $navigation = Navigation::create($request->validated());

        return (new NavigationResource($navigation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Navigation $navigation): NavigationResource
    {
        $this->authorize('view', $navigation);

        return new NavigationResource($navigation->loadCount('nodes'));
    }

    public function update(UpdateNavigationRequest $request, Navigation $navigation): NavigationResource
    {
        $this->authorize('update', $navigation);

        $navigation->update($request->validated());

        return new NavigationResource($navigation->fresh());
    }

    public function destroy(Navigation $navigation): JsonResponse
    {
        $this->authorize('delete', $navigation);

        // navigation_nodes hängen per ON DELETE CASCADE.
        $navigation->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /navigations/{navigation}/tree — kompletter Baum als verschachteltes JSON.
     */
    public function tree(Navigation $navigation): JsonResponse
    {
        $this->authorize('view', $navigation);

        // Verwaiste Knoten (Ziel gelöscht → tote Referenz) bereinigen, damit
        // keine „Artefakte" im Baum hängen bleiben.
        NavigationNode::pruneOrphans($navigation->id);

        $rootNodes = NavigationNode::where('navigation_id', $navigation->id)
            ->whereNull('parent_node_id')
            ->orderBy('sort_order')
            ->get();

        $build = function ($nodes) use (&$build) {
            return $nodes->map(function (NavigationNode $node) use (&$build) {
                $children = $node->children()->orderBy('sort_order')->get();

                return [
                    'id' => $node->id,
                    'navigation_id' => $node->navigation_id,
                    'parent_node_id' => $node->parent_node_id,
                    'label_de' => $node->label_de,
                    'label_en' => $node->label_en,
                    'slug' => $node->slug,
                    'icon' => $node->icon,
                    'is_visible' => $node->is_visible,
                    'target_type' => $node->target_type,
                    'content_page_id' => $node->content_page_id,
                    'hierarchy_node_id' => $node->hierarchy_node_id,
                    'product_id' => $node->product_id,
                    'external_url' => $node->external_url,
                    'auto_expand' => $node->auto_expand,
                    'expand_depth' => $node->expand_depth,
                    'depth' => $node->depth,
                    'sort_order' => $node->sort_order,
                    'children' => $build($children),
                ];
            })->values();
        };

        return response()->json(['data' => $build($rootNodes)]);
    }
}
