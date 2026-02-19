<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreUnitRequest;
use App\Http\Requests\Api\V1\UpdateUnitRequest;
use App\Http\Resources\Api\V1\UnitResource;
use App\Models\Unit;
use App\Models\UnitGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function index(Request $request, UnitGroup $unitGroup): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Unit::class);

        $query = $unitGroup->units();
        $this->applySorting($query, $request, 'is_base_unit', 'desc');

        return UnitResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreUnitRequest $request, UnitGroup $unitGroup): JsonResponse
    {
        $this->authorize('create', Unit::class);

        $unit = $unitGroup->units()->create($request->validated());

        return (new UnitResource($unit))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Unit $unit): UnitResource
    {
        $this->authorize('view', $unit);

        return new UnitResource($unit);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        $this->authorize('update', $unit);

        $unit->update($request->validated());

        return new UnitResource($unit->fresh());
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $this->authorize('delete', $unit);

        $unit->delete();

        return response()->json(null, 204);
    }
}
