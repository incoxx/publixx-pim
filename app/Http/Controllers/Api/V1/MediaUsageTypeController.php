<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMediaUsageTypeRequest;
use App\Http\Requests\Api\V1\UpdateMediaUsageTypeRequest;
use App\Http\Resources\Api\V1\MediaUsageTypeResource;
use App\Models\MediaUsageType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaUsageTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MediaUsageType::class);

        $query = MediaUsageType::query();
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return MediaUsageTypeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreMediaUsageTypeRequest $request): JsonResponse
    {
        $this->authorize('create', MediaUsageType::class);

        $type = MediaUsageType::create($request->validated());

        return (new MediaUsageTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function show(MediaUsageType $mediaUsageType): MediaUsageTypeResource
    {
        $this->authorize('view', $mediaUsageType);

        return new MediaUsageTypeResource($mediaUsageType);
    }

    public function update(UpdateMediaUsageTypeRequest $request, MediaUsageType $mediaUsageType): MediaUsageTypeResource
    {
        $this->authorize('update', $mediaUsageType);

        $mediaUsageType->update($request->validated());

        return new MediaUsageTypeResource($mediaUsageType->fresh());
    }

    public function destroy(MediaUsageType $mediaUsageType): JsonResponse
    {
        $this->authorize('delete', $mediaUsageType);

        $mediaUsageType->delete();

        return response()->json(null, 204);
    }
}
