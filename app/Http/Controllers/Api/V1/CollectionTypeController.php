<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCollectionTypeRequest;
use App\Http\Requests\Api\V1\UpdateCollectionTypeRequest;
use App\Http\Resources\Api\V1\CollectionTypeResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\CollectionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionTypeController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CollectionType::class);

        $query = CollectionType::query();

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(['is_active'])
        ));
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return CollectionTypeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreCollectionTypeRequest $request): JsonResponse
    {
        $this->authorize('create', CollectionType::class);

        $type = CollectionType::create($request->validated());

        return (new CollectionTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CollectionType $collectionType): CollectionTypeResource
    {
        $this->authorize('view', $collectionType);

        return new CollectionTypeResource($collectionType);
    }

    public function update(UpdateCollectionTypeRequest $request, CollectionType $collectionType): CollectionTypeResource
    {
        $this->authorize('update', $collectionType);

        $collectionType->update($request->validated());

        return new CollectionTypeResource($collectionType->fresh());
    }

    public function dependencies(CollectionType $collectionType): JsonResponse
    {
        $this->authorize('view', $collectionType);

        return $this->dependenciesResponse($collectionType);
    }

    public function destroy(Request $request, CollectionType $collectionType): JsonResponse
    {
        $this->authorize('delete', $collectionType);

        return $this->destroyWithConstraintCheck($request, $collectionType);
    }
}
