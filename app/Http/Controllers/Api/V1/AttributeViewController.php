<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeViewRequest;
use App\Http\Requests\Api\V1\UpdateAttributeViewRequest;
use App\Http\Requests\Api\V1\AssignAttributeToViewRequest;
use App\Http\Resources\Api\V1\AttributeViewResource;
use App\Models\AttributeView;
use App\Models\AttributeViewAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttributeViewController extends Controller
{
    private const ALLOWED_INCLUDES = ['attributes'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AttributeView::class);

        $query = AttributeView::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

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

        $attributeView->update($request->validated());

        return new AttributeViewResource($attributeView->fresh());
    }

    public function destroy(AttributeView $attributeView): JsonResponse
    {
        $this->authorize('delete', $attributeView);

        $attributeView->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /attribute-views/{id}/attributes — assign attribute to view.
     */
    public function assignAttribute(AssignAttributeToViewRequest $request, AttributeView $attributeView): JsonResponse
    {
        $this->authorize('update', $attributeView);

        $assignment = $attributeView->assignments()->create([
            'attribute_id' => $request->validated('attribute_id'),
        ]);

        return response()->json([
            'id' => $assignment->id,
            'attribute_id' => $assignment->attribute_id,
            'attribute_view_id' => $assignment->attribute_view_id,
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
}
