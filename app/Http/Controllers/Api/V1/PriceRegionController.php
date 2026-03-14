<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StorePriceRegionRequest;
use App\Http\Requests\Api\V1\UpdatePriceRegionRequest;
use App\Http\Resources\Api\V1\PriceRegionResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\PriceRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriceRegionController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PriceRegion::class);

        $query = PriceRegion::query();
        $this->applySorting($query, $request, 'name', 'asc');

        return PriceRegionResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StorePriceRegionRequest $request): JsonResponse
    {
        $this->authorize('create', PriceRegion::class);

        $region = PriceRegion::create($request->validated());

        return (new PriceRegionResource($region))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PriceRegion $priceRegion): PriceRegionResource
    {
        $this->authorize('view', $priceRegion);

        return new PriceRegionResource($priceRegion);
    }

    public function update(UpdatePriceRegionRequest $request, PriceRegion $priceRegion): PriceRegionResource
    {
        $this->authorize('update', $priceRegion);

        $priceRegion->update($request->validated());

        return new PriceRegionResource($priceRegion->fresh());
    }

    public function dependencies(PriceRegion $priceRegion): JsonResponse
    {
        $this->authorize('view', $priceRegion);

        return $this->dependenciesResponse($priceRegion);
    }

    public function destroy(Request $request, PriceRegion $priceRegion): JsonResponse
    {
        $this->authorize('delete', $priceRegion);

        return $this->destroyWithConstraintCheck($request, $priceRegion);
    }
}
