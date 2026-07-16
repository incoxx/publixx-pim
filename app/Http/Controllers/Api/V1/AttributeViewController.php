<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeViewRequest;
use App\Http\Requests\Api\V1\UpdateAttributeViewRequest;
use App\Http\Requests\Api\V1\AssignAttributeToViewRequest;
use App\Http\Requests\Api\V1\ReorderAttributeViewAttributesRequest;
use App\Http\Requests\Api\V1\UpdateAttributeViewAssignmentRequest;
use App\Http\Resources\Api\V1\AttributeViewResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\AttributeView;
use App\Models\AttributeViewAssignment;
use App\Models\RoleTabPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AttributeViewController extends Controller
{
    use ChecksDeletionConstraints;
    private const ALLOWED_INCLUDES = ['attributes'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AttributeView::class);

        $query = AttributeView::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySearch($query, $request, ['name_de', 'name_en', 'technical_name']);
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return AttributeViewResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreAttributeViewRequest $request): JsonResponse
    {
        $this->authorize('create', AttributeView::class);

        $view = AttributeView::create($request->validated());

        return (new AttributeViewResource($view))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, AttributeView $attributeView): AttributeViewResource
    {
        $this->authorize('view', $attributeView);

        $attributeView->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new AttributeViewResource($attributeView);
    }

    public function update(UpdateAttributeViewRequest $request, AttributeView $attributeView): AttributeViewResource
    {
        $this->authorize('update', $attributeView);

        $wasTab = $attributeView->show_as_tab;
        $attributeView->update($request->validated());

        // Wird der Tab deaktiviert, sind bisherige rollenbasierte Sichtbarkeits-/Zugriffs-
        // Einstellungen für diesen Tab-Key bedeutungslos — ohne Aufräumen würden sie beim
        // erneuten Aktivieren unerwartet (und für den Admin unsichtbar) wieder gelten.
        if ($wasTab && !$attributeView->show_as_tab) {
            RoleTabPermission::where('tab_key', $attributeView->tabKey())->delete();
        }

        return new AttributeViewResource($attributeView->fresh());
    }

    public function dependencies(AttributeView $attributeView): JsonResponse
    {
        $this->authorize('view', $attributeView);

        return $this->dependenciesResponse($attributeView);
    }

    public function destroy(Request $request, AttributeView $attributeView): JsonResponse
    {
        $this->authorize('delete', $attributeView);

        return $this->destroyWithConstraintCheck($request, $attributeView);
    }

    /**
     * POST /attribute-views/{id}/attributes — assign attribute to view.
     */
    public function assignAttribute(AssignAttributeToViewRequest $request, AttributeView $attributeView): JsonResponse
    {
        $this->authorize('update', $attributeView);

        $assignment = DB::transaction(function () use ($attributeView, $request) {
            $nextSortOrder = (int) $attributeView->assignments()->lockForUpdate()->max('sort_order') + 1;

            return $attributeView->assignments()->create([
                'attribute_id' => $request->validated('attribute_id'),
                'sort_order' => $nextSortOrder,
            ]);
        });

        return response()->json([
            'id' => $assignment->id,
            'attribute_id' => $assignment->attribute_id,
            'attribute_view_id' => $assignment->attribute_view_id,
            'sort_order' => $assignment->sort_order,
        ], 201);
    }

    /**
     * DELETE /attribute-views/{id}/attributes/{attrId} — remove assignment.
     */
    public function removeAttribute(AttributeView $attributeView, string $attributeId): JsonResponse
    {
        $this->authorize('update', $attributeView);

        $attributeView->assignments()
            ->where('attribute_id', $attributeId)
            ->delete();

        return response()->json(null, 204);
    }

    /**
     * PATCH /attribute-views/{id}/attributes/{attrId} — Zuordnungs-Einstellungen ändern
     * (aktuell: is_readonly — erzwingt Nur-Lesen für dieses Attribut speziell innerhalb
     * des dieser Sicht zugeordneten Produkteditor-Tabs, unabhängig vom globalen
     * Attribute::is_readonly-Flag und von der rollenbasierten Tab-Zugriffsstufe).
     */
    public function updateAssignment(UpdateAttributeViewAssignmentRequest $request, AttributeView $attributeView, string $attributeId): JsonResponse
    {
        $this->authorize('update', $attributeView);

        $assignment = $attributeView->assignments()
            ->where('attribute_id', $attributeId)
            ->firstOrFail();

        $assignment->update($request->validated());

        return response()->json([
            'id' => $assignment->id,
            'attribute_id' => $assignment->attribute_id,
            'attribute_view_id' => $assignment->attribute_view_id,
            'sort_order' => $assignment->sort_order,
            'is_readonly' => $assignment->is_readonly,
        ]);
    }

    /**
     * PUT /attribute-views/{id}/attributes/reorder — Drag&Drop-Reihenfolge der zugeordneten
     * Attribute persistieren. Erwartet die vollständige, neu geordnete Liste der attribute_ids.
     */
    public function reorderAttributes(ReorderAttributeViewAttributesRequest $request, AttributeView $attributeView): JsonResponse
    {
        $this->authorize('update', $attributeView);

        $attributeIds = $request->validated('attribute_ids');

        $assignments = $attributeView->assignments()
            ->whereIn('attribute_id', $attributeIds)
            ->get()
            ->keyBy('attribute_id');

        $totalAssignmentCount = $attributeView->assignments()->count();
        if ($assignments->count() !== count($attributeIds) || $totalAssignmentCount !== count($attributeIds)) {
            return response()->json([
                'message' => 'Die Reihenfolge muss alle zugeordneten Attribute vollständig und ohne unbekannte IDs enthalten.',
            ], 422);
        }

        DB::transaction(function () use ($attributeIds, $assignments) {
            foreach (array_values($attributeIds) as $sortOrder => $attributeId) {
                $assignments->get($attributeId)?->update(['sort_order' => $sortOrder]);
            }
        });

        return response()->json(null, 204);
    }
}
