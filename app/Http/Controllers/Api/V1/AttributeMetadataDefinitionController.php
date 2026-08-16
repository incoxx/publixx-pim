<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeMetadataDefinitionRequest;
use App\Http\Requests\Api\V1\UpdateAttributeMetadataDefinitionRequest;
use App\Http\Resources\Api\V1\AttributeMetadataDefinitionResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\AttributeMetadataDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttributeMetadataDefinitionController extends Controller
{
    use ChecksDeletionConstraints;

    private const ALLOWED_INCLUDES = ['values'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AttributeMetadataDefinition::class);

        $query = AttributeMetadataDefinition::query()
            ->withCount('values')
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySearch($query, $request, ['name_de', 'name_en', 'technical_name']);
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return AttributeMetadataDefinitionResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreAttributeMetadataDefinitionRequest $request): JsonResponse
    {
        $this->authorize('create', AttributeMetadataDefinition::class);

        $definition = AttributeMetadataDefinition::create($request->validated());

        return (new AttributeMetadataDefinitionResource($definition))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, AttributeMetadataDefinition $attributeMetadataDefinition): AttributeMetadataDefinitionResource
    {
        $this->authorize('view', $attributeMetadataDefinition);

        $attributeMetadataDefinition->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));
        $attributeMetadataDefinition->loadCount('values');

        return new AttributeMetadataDefinitionResource($attributeMetadataDefinition);
    }

    public function update(UpdateAttributeMetadataDefinitionRequest $request, AttributeMetadataDefinition $attributeMetadataDefinition): AttributeMetadataDefinitionResource
    {
        $this->authorize('update', $attributeMetadataDefinition);

        $attributeMetadataDefinition->update($request->validated());

        return new AttributeMetadataDefinitionResource($attributeMetadataDefinition->fresh());
    }

    public function dependencies(AttributeMetadataDefinition $attributeMetadataDefinition): JsonResponse
    {
        $this->authorize('view', $attributeMetadataDefinition);

        return $this->dependenciesResponse($attributeMetadataDefinition);
    }

    public function destroy(Request $request, AttributeMetadataDefinition $attributeMetadataDefinition): JsonResponse
    {
        $this->authorize('delete', $attributeMetadataDefinition);

        return $this->destroyWithConstraintCheck($request, $attributeMetadataDefinition);
    }
}
