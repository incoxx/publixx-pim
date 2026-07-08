<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCollectionRequest;
use App\Http\Requests\Api\V1\UpdateCollectionRequest;
use App\Http\Resources\Api\V1\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionController extends Controller
{
    private const ALLOWED_INCLUDES = ['collectionType', 'organization', 'items', 'items.product'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Collection::class);

        $query = Collection::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES))
            ->withCount('items');

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(['collection_type_id', 'organization_id', 'status'])
        ));
        $this->applySearch($query, $request, ['name', 'reference']);
        $this->applySorting($query, $request, 'created_at', 'desc');

        return CollectionResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $this->authorize('create', Collection::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;
        $data['updated_by'] = $request->user()?->id;

        $collection = Collection::create($data);

        return (new CollectionResource($collection))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Collection $collection): CollectionResource
    {
        $this->authorize('view', $collection);

        $collection->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new CollectionResource($collection);
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): CollectionResource
    {
        $this->authorize('update', $collection);

        $data = $request->validated();
        $data['updated_by'] = $request->user()?->id;

        $collection->update($data);

        return new CollectionResource($collection->fresh());
    }

    public function destroy(Collection $collection): JsonResponse
    {
        $this->authorize('delete', $collection);

        $collection->delete();

        return response()->json(null, 204);
    }
}
