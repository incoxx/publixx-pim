<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MoveNavigationNodeRequest;
use App\Http\Requests\Api\V1\StoreNavigationNodeRequest;
use App\Http\Requests\Api\V1\UpdateNavigationNodeRequest;
use App\Http\Resources\Api\V1\NavigationNodeResource;
use App\Models\Navigation;
use App\Models\NavigationNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Knoten des Navigationsbaums. Schreibrechte richten sich nach der
 * Navigation (navigation.edit via NavigationPolicy::update).
 */
class NavigationNodeController extends Controller
{
    public function store(StoreNavigationNodeRequest $request, Navigation $navigation): JsonResponse
    {
        $this->authorize('update', $navigation);

        $node = DB::transaction(function () use ($request, $navigation) {
            $data = $request->validated();
            $data['navigation_id'] = $navigation->id;
            $data['path'] = '/'; // temporär, NOT NULL erfüllen

            if (empty($data['parent_node_id'])) {
                $data['depth'] = 0;
            }

            $node = NavigationNode::create($data);
            $node->refreshTreePath(); // berechnet path + depth aus parent

            return $node;
        });

        return (new NavigationNodeResource($node))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateNavigationNodeRequest $request, NavigationNode $navigationNode): NavigationNodeResource
    {
        $this->authorize('update', $navigationNode->navigation);

        $navigationNode->update($request->validated());

        return new NavigationNodeResource($navigationNode->fresh());
    }

    /**
     * Knoten verschieben/umsortieren — Pfad + Tiefe inkl. Nachfahren aktualisieren.
     */
    public function move(MoveNavigationNodeRequest $request, NavigationNode $navigationNode): NavigationNodeResource
    {
        $this->authorize('update', $navigationNode->navigation);

        $data = $request->validated();
        $oldPath = $navigationNode->path;

        DB::transaction(function () use ($data, $navigationNode, $oldPath) {
            $navigationNode->parent_node_id = $data['parent_node_id'] ?? null;
            $navigationNode->sort_order = $data['sort_order'];
            $navigationNode->save();

            $navigationNode->refreshTreePath();
            $navigationNode->reattachSubtree($oldPath);
        });

        return new NavigationNodeResource($navigationNode->fresh());
    }

    public function destroy(NavigationNode $navigationNode): JsonResponse
    {
        $this->authorize('update', $navigationNode->navigation);

        // Kindknoten hängen per ON DELETE CASCADE.
        DB::transaction(fn () => $navigationNode->delete());

        return response()->json(null, 204);
    }
}
