<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreComparisonOperatorRequest;
use App\Http\Requests\Api\V1\UpdateComparisonOperatorRequest;
use App\Http\Resources\Api\V1\ComparisonOperatorResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\ComparisonOperator;
use App\Models\ComparisonOperatorGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComparisonOperatorController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request, ComparisonOperatorGroup $comparisonOperatorGroup): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ComparisonOperator::class);

        $query = $comparisonOperatorGroup->operators();
        $this->applySorting($query, $request, 'technical_name', 'asc');

        return ComparisonOperatorResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreComparisonOperatorRequest $request, ComparisonOperatorGroup $comparisonOperatorGroup): JsonResponse
    {
        $this->authorize('create', ComparisonOperator::class);

        $operator = $comparisonOperatorGroup->operators()->create($request->validated());

        return (new ComparisonOperatorResource($operator))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ComparisonOperator $comparisonOperator): ComparisonOperatorResource
    {
        $this->authorize('view', $comparisonOperator);

        return new ComparisonOperatorResource($comparisonOperator);
    }

    public function update(UpdateComparisonOperatorRequest $request, ComparisonOperator $comparisonOperator): ComparisonOperatorResource
    {
        $this->authorize('update', $comparisonOperator);

        $comparisonOperator->update($request->validated());

        return new ComparisonOperatorResource($comparisonOperator->fresh());
    }

    public function dependencies(ComparisonOperator $comparisonOperator): JsonResponse
    {
        $this->authorize('view', $comparisonOperator);

        return $this->dependenciesResponse($comparisonOperator);
    }

    public function destroy(Request $request, ComparisonOperator $comparisonOperator): JsonResponse
    {
        $this->authorize('delete', $comparisonOperator);

        return $this->destroyWithConstraintCheck($request, $comparisonOperator);
    }
}
