<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\MoveNavigationNodeRequest;
use App\Http\Requests\Api\V1\StoreNavigationNodeRequest;
use App\Http\Requests\Api\V1\UpdateNavigationNodeRequest;
use App\Http\Resources\Api\V1\NavigationNodeResource;
use App\Models\Navigation;
use App\Models\NavigationNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        // Zyklus verhindern: Knoten darf nicht unter sich selbst oder einen
        // eigenen Nachfahren gehängt werden (sonst korrupter Baum / Endlos-Rekursion).
        $newParentId = $data['parent_node_id'] ?? null;
        if ($newParentId !== null) {
            if ($newParentId === $navigationNode->id) {
                throw ValidationException::withMessages(['parent_node_id' => 'Ein Knoten kann nicht sein eigenes Elternelement sein.']);
            }
            $parent = NavigationNode::find($newParentId);
            if ($parent && str_starts_with($parent->path, $navigationNode->path)) {
                throw ValidationException::withMessages(['parent_node_id' => 'Ein Knoten kann nicht unter einen eigenen Unterknoten verschoben werden.']);
            }
        }

        DB::transaction(function () use ($data, $navigationNode, $oldPath, $newParentId) {
            $navigationNode->parent_node_id = $newParentId;
            $navigationNode->save();

            $navigationNode->refreshTreePath();
            $navigationNode->reattachSubtree($oldPath);

            // Geschwister unter dem neuen Elternknoten lückenlos neu nummerieren
            // und den verschobenen Knoten an der Zielposition einfügen. Damit ist
            // auch das Umsortieren auf gleicher Ebene konsistent.
            $siblings = NavigationNode::query()
                ->where('navigation_id', $navigationNode->navigation_id)
                ->where('id', '!=', $navigationNode->id)
                ->when($newParentId === null,
                    fn ($q) => $q->whereNull('parent_node_id'),
                    fn ($q) => $q->where('parent_node_id', $newParentId))
                ->orderBy('sort_order')
                ->get()
                ->all();

            $index = max(0, min((int) ($data['sort_order'] ?? count($siblings)), count($siblings)));
            array_splice($siblings, $index, 0, [$navigationNode]);

            foreach ($siblings as $position => $sibling) {
                if ((int) $sibling->sort_order !== $position) {
                    $sibling->sort_order = $position;
                    $sibling->save();
                }
            }
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
