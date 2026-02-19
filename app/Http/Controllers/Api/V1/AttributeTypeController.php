<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeTypeRequest;
use App\Http\Requests\Api\V1\UpdateAttributeTypeRequest;
use App\Http\Resources\Api\V1\AttributeTypeResource;
use App\Models\AttributeType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttributeTypeController extends Controller
{
    private const ALLOWED_INCLUDES = ['attributes'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AttributeType::class);

        $query = AttributeType::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySorting($query, $request, 'sort_order', 'asc');

        return AttributeTypeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreAttributeTypeRequest $request): JsonResponse
    {
        $this->authorize('create', AttributeType::class);

        $type = AttributeType::create($request->validated());

        return (new AttributeTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, AttributeType $attributeType): AttributeTypeResource
    {
        $this->authorize('view', $attributeType);

        $attributeType->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new AttributeTypeResource($attributeType);
    }

    public function update(UpdateAttributeTypeRequest $request, AttributeType $attributeType): AttributeTypeResource
    {
        $this->authorize('update', $attributeType);

        $attributeType->update($request->validated());

        return new AttributeTypeResource($attributeType->fresh());
    }

    public function destroy(AttributeType $attributeType): JsonResponse
    {
        $this->authorize('delete', $attributeType);

        $attributeType->delete();

        return response()->json(null, 204);
    }
}
