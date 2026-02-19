<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreRelationTypeRequest;
use App\Http\Requests\Api\V1\UpdateRelationTypeRequest;
use App\Http\Resources\Api\V1\RelationTypeResource;
use App\Models\ProductRelationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RelationTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductRelationType::class);

        $query = ProductRelationType::query();
        $this->applySorting($query, $request, 'name_de', 'asc');

        return RelationTypeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreRelationTypeRequest $request): JsonResponse
    {
        $this->authorize('create', ProductRelationType::class);

        $type = ProductRelationType::create($request->validated());

        return (new RelationTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductRelationType $relationType): RelationTypeResource
    {
        $this->authorize('view', $relationType);

        return new RelationTypeResource($relationType);
    }

    public function update(UpdateRelationTypeRequest $request, ProductRelationType $relationType): RelationTypeResource
    {
        $this->authorize('update', $relationType);

        $relationType->update($request->validated());

        return new RelationTypeResource($relationType->fresh());
    }

    public function destroy(ProductRelationType $relationType): JsonResponse
    {
        $this->authorize('delete', $relationType);

        $relationType->delete();

        return response()->json(null, 204);
    }
}
