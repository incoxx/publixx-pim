<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StorePriceTypeRequest;
use App\Http\Requests\Api\V1\UpdatePriceTypeRequest;
use App\Http\Resources\Api\V1\PriceTypeResource;
use App\Models\PriceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriceTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PriceType::class);

        $query = PriceType::query();
        $this->applySorting($query, $request, 'name_de', 'asc');

        return PriceTypeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StorePriceTypeRequest $request): JsonResponse
    {
        $this->authorize('create', PriceType::class);

        $type = PriceType::create($request->validated());

        return (new PriceTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PriceType $priceType): PriceTypeResource
    {
        $this->authorize('view', $priceType);

        return new PriceTypeResource($priceType);
    }

    public function update(UpdatePriceTypeRequest $request, PriceType $priceType): PriceTypeResource
    {
        $this->authorize('update', $priceType);

        $priceType->update($request->validated());

        return new PriceTypeResource($priceType->fresh());
    }

    public function destroy(PriceType $priceType): JsonResponse
    {
        $this->authorize('delete', $priceType);

        $priceType->delete();

        return response()->json(null, 204);
    }
}
