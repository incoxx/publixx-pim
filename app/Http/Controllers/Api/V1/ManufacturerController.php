<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreManufacturerRequest;
use App\Http\Requests\Api\V1\UpdateManufacturerRequest;
use App\Http\Resources\Api\V1\ManufacturerResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ManufacturerController extends Controller
{
    use ChecksDeletionConstraints;

    private const ALLOWED_INCLUDES = ['products', 'logoMedia'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Manufacturer::class);

        $query = Manufacturer::query()
            ->withCount('products')
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySearch($query, $request, ['name', 'city', 'country']);
        $this->applySorting($query, $request, 'name', 'asc');

        return ManufacturerResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreManufacturerRequest $request): JsonResponse
    {
        $this->authorize('create', Manufacturer::class);

        $manufacturer = Manufacturer::create($request->validated());

        return (new ManufacturerResource($manufacturer))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Manufacturer $manufacturer): ManufacturerResource
    {
        $this->authorize('view', $manufacturer);

        $manufacturer->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new ManufacturerResource($manufacturer);
    }

    public function update(UpdateManufacturerRequest $request, Manufacturer $manufacturer): ManufacturerResource
    {
        $this->authorize('update', $manufacturer);

        $manufacturer->update($request->validated());

        return new ManufacturerResource($manufacturer->fresh());
    }

    public function dependencies(Manufacturer $manufacturer): JsonResponse
    {
        $this->authorize('view', $manufacturer);

        return $this->dependenciesResponse($manufacturer);
    }

    public function destroy(Request $request, Manufacturer $manufacturer): JsonResponse
    {
        $this->authorize('delete', $manufacturer);

        return $this->destroyWithConstraintCheck($request, $manufacturer);
    }
}
