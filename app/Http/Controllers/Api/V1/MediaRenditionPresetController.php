<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMediaRenditionPresetRequest;
use App\Http\Requests\Api\V1\UpdateMediaRenditionPresetRequest;
use App\Http\Resources\Api\V1\MediaRenditionPresetResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\MediaRenditionPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaRenditionPresetController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MediaRenditionPreset::class);

        $query = MediaRenditionPreset::query();
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return MediaRenditionPresetResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreMediaRenditionPresetRequest $request): JsonResponse
    {
        $this->authorize('create', MediaRenditionPreset::class);

        $preset = MediaRenditionPreset::create($request->validated());

        return (new MediaRenditionPresetResource($preset))
            ->response()
            ->setStatusCode(201);
    }

    public function show(MediaRenditionPreset $mediaRenditionPreset): MediaRenditionPresetResource
    {
        $this->authorize('view', $mediaRenditionPreset);

        return new MediaRenditionPresetResource($mediaRenditionPreset);
    }

    public function update(UpdateMediaRenditionPresetRequest $request, MediaRenditionPreset $mediaRenditionPreset): MediaRenditionPresetResource
    {
        $this->authorize('update', $mediaRenditionPreset);

        $mediaRenditionPreset->update($request->validated());

        return new MediaRenditionPresetResource($mediaRenditionPreset->fresh());
    }

    public function dependencies(MediaRenditionPreset $mediaRenditionPreset): JsonResponse
    {
        $this->authorize('view', $mediaRenditionPreset);

        return $this->dependenciesResponse($mediaRenditionPreset);
    }

    public function destroy(Request $request, MediaRenditionPreset $mediaRenditionPreset): JsonResponse
    {
        $this->authorize('delete', $mediaRenditionPreset);

        return $this->destroyWithConstraintCheck($request, $mediaRenditionPreset);
    }
}
