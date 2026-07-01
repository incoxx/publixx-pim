<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeFormattingRuleRequest;
use App\Http\Requests\Api\V1\UpdateAttributeFormattingRuleRequest;
use App\Http\Resources\Api\V1\AttributeFormattingRuleResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\AttributeFormattingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttributeFormattingRuleController extends Controller
{
    use ChecksDeletionConstraints;
    private const ALLOWED_INCLUDES = ['attributes'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AttributeFormattingRule::class);

        $query = AttributeFormattingRule::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySearch($query, $request, ['name']);
        $this->applySorting($query, $request, 'name', 'asc');

        return AttributeFormattingRuleResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreAttributeFormattingRuleRequest $request): JsonResponse
    {
        $this->authorize('create', AttributeFormattingRule::class);

        $rule = AttributeFormattingRule::create($request->validated());

        return (new AttributeFormattingRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, AttributeFormattingRule $attributeFormattingRule): AttributeFormattingRuleResource
    {
        $this->authorize('view', $attributeFormattingRule);

        $attributeFormattingRule->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new AttributeFormattingRuleResource($attributeFormattingRule);
    }

    public function update(UpdateAttributeFormattingRuleRequest $request, AttributeFormattingRule $attributeFormattingRule): AttributeFormattingRuleResource
    {
        $this->authorize('update', $attributeFormattingRule);

        $attributeFormattingRule->update($request->validated());

        return new AttributeFormattingRuleResource($attributeFormattingRule->fresh());
    }

    public function dependencies(AttributeFormattingRule $attributeFormattingRule): JsonResponse
    {
        $this->authorize('view', $attributeFormattingRule);

        return $this->dependenciesResponse($attributeFormattingRule);
    }

    public function destroy(Request $request, AttributeFormattingRule $attributeFormattingRule): JsonResponse
    {
        $this->authorize('delete', $attributeFormattingRule);

        return $this->destroyWithConstraintCheck($request, $attributeFormattingRule);
    }
}
