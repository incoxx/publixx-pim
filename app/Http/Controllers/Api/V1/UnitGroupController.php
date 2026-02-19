<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreUnitGroupRequest;
use App\Http\Requests\Api\V1\UpdateUnitGroupRequest;
use App\Http\Requests\Api\V1\StoreUnitRequest;
use App\Http\Resources\Api\V1\UnitGroupResource;
use App\Http\Resources\Api\V1\UnitResource;
use App\Models\UnitGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitGroupController extends Controller
{
    private const ALLOWED_INCLUDES = ['units'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', UnitGroup::class);

        $query = UnitGroup::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applySearch($query, $request, ['name_de', 'name_en', 'technical_name']);
        $this->applySorting($query, $request, 'name_de', 'asc');

        return UnitGroupResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreUnitGroupRequest $request): JsonResponse
    {
        $this->authorize('create', UnitGroup::class);

        $group = UnitGroup::create($request->validated());

        return (new UnitGroupResource($group))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, UnitGroup $unitGroup): UnitGroupResource
    {
        $this->authorize('view', $unitGroup);

        $unitGroup->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new UnitGroupResource($unitGroup);
    }

    public function update(UpdateUnitGroupRequest $request, UnitGroup $unitGroup): UnitGroupResource
    {
        $this->authorize('update', $unitGroup);

        $unitGroup->update($request->validated());

        return new UnitGroupResource($unitGroup->fresh());
    }

    public function destroy(UnitGroup $unitGroup): JsonResponse
    {
        $this->authorize('delete', $unitGroup);

        $unitGroup->delete();

        return response()->json(null, 204);
    }
}
