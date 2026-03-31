<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMediaUsageTypeRequest;
use App\Http\Requests\Api\V1\UpdateMediaUsageTypeRequest;
use App\Http\Resources\Api\V1\MediaUsageTypeResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Http\Traits\FiltersByInstanceRestrictions;
use App\Models\MediaUsageType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaUsageTypeController extends Controller
{
    use ChecksDeletionConstraints, FiltersByInstanceRestrictions;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MediaUsageType::class);

        $query = MediaUsageType::with('defaultAttributes');
        $this->applyInstanceRestrictionFilter($query, MediaUsageType::class);
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

    public function updateDefaultAttributes(Request $request, MediaUsageType $mediaUsageType): MediaUsageTypeResource
    {
        $this->authorize('update', $mediaUsageType);

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

        $mediaUsageType->defaultAttributes()->sync($syncData);

        return new MediaUsageTypeResource($mediaUsageType->load('defaultAttributes'));
    }

    public function dependencies(MediaUsageType $mediaUsageType): JsonResponse
    {
        $this->authorize('view', $mediaUsageType);

        return $this->dependenciesResponse($mediaUsageType);
    }

    public function destroy(Request $request, MediaUsageType $mediaUsageType): JsonResponse
    {
        $this->authorize('delete', $mediaUsageType);

        return $this->destroyWithConstraintCheck($request, $mediaUsageType);
    }
}
