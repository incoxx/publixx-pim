<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreRelationTypeRequest;
use App\Http\Requests\Api\V1\UpdateRelationTypeRequest;
use App\Http\Resources\Api\V1\RelationTypeResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\ProductRelationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RelationTypeController extends Controller
{
    use ChecksDeletionConstraints;
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductRelationType::class);

        $query = ProductRelationType::with('defaultAttributes');
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

        return new RelationTypeResource($relationType->load('defaultAttributes'));
    }

    public function update(UpdateRelationTypeRequest $request, ProductRelationType $relationType): RelationTypeResource
    {
        $this->authorize('update', $relationType);

        $relationType->update($request->validated());

        return new RelationTypeResource($relationType->fresh());
    }

    public function dependencies(ProductRelationType $relationType): JsonResponse
    {
        $this->authorize('view', $relationType);

        return $this->dependenciesResponse($relationType);
    }

    public function destroy(Request $request, ProductRelationType $relationType): JsonResponse
    {
        $this->authorize('delete', $relationType);

        return $this->destroyWithConstraintCheck($request, $relationType);
    }

    public function updateDefaultAttributes(Request $request, ProductRelationType $relationType): RelationTypeResource
    {
        $this->authorize('update', $relationType);

        $request->validate([
            'attributes' => 'present|array',
            'attributes.*.attribute_id' => 'required|uuid|exists:attributes,id',
            'attributes.*.sort_order' => 'integer|min:0',
        ]);

        $syncData = [];
        foreach ($request->input('attributes', []) as $item) {
            $syncData[$item['attribute_id']] = [
                'sort_order' => $item['sort_order'] ?? 0,
            ];
        }

        $relationType->defaultAttributes()->sync($syncData);

        return new RelationTypeResource($relationType->load('defaultAttributes'));
    }
}
