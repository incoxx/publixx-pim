<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreComparisonOperatorGroupRequest;
use App\Http\Requests\Api\V1\UpdateComparisonOperatorGroupRequest;
use App\Http\Resources\Api\V1\ComparisonOperatorGroupResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\ComparisonOperatorGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComparisonOperatorGroupController extends Controller
{
    use ChecksDeletionConstraints;

    private const ALLOWED_INCLUDES = ['operators'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ComparisonOperatorGroup::class);

        $query = ComparisonOperatorGroup::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySearch($query, $request, ['name_de', 'name_en', 'technical_name']);
        $this->applySorting($query, $request, 'name_de', 'asc');

        return ComparisonOperatorGroupResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreComparisonOperatorGroupRequest $request): JsonResponse
    {
        $this->authorize('create', ComparisonOperatorGroup::class);

        $group = ComparisonOperatorGroup::create($request->validated());

        return (new ComparisonOperatorGroupResource($group))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ComparisonOperatorGroup $comparisonOperatorGroup): ComparisonOperatorGroupResource
    {
        $this->authorize('view', $comparisonOperatorGroup);

        $comparisonOperatorGroup->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new ComparisonOperatorGroupResource($comparisonOperatorGroup);
    }

    public function update(UpdateComparisonOperatorGroupRequest $request, ComparisonOperatorGroup $comparisonOperatorGroup): ComparisonOperatorGroupResource
    {
        $this->authorize('update', $comparisonOperatorGroup);

        $comparisonOperatorGroup->update($request->validated());

        return new ComparisonOperatorGroupResource($comparisonOperatorGroup->fresh());
    }

    public function dependencies(ComparisonOperatorGroup $comparisonOperatorGroup): JsonResponse
    {
        $this->authorize('view', $comparisonOperatorGroup);

        return $this->dependenciesResponse($comparisonOperatorGroup);
    }

    public function destroy(Request $request, ComparisonOperatorGroup $comparisonOperatorGroup): JsonResponse
    {
        $this->authorize('delete', $comparisonOperatorGroup);

        return $this->destroyWithConstraintCheck($request, $comparisonOperatorGroup);
    }
}
