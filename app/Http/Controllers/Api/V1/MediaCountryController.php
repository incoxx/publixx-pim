<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMediaCountryRequest;
use App\Http\Requests\Api\V1\UpdateMediaCountryRequest;
use App\Http\Resources\Api\V1\MediaCountryResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\MediaCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaCountryController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MediaCountry::class);

        $query = MediaCountry::query();
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return MediaCountryResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreMediaCountryRequest $request): JsonResponse
    {
        $this->authorize('create', MediaCountry::class);

        $country = MediaCountry::create($request->validated());

        return (new MediaCountryResource($country))
            ->response()
            ->setStatusCode(201);
    }

    public function show(MediaCountry $mediaCountry): MediaCountryResource
    {
        $this->authorize('view', $mediaCountry);

        return new MediaCountryResource($mediaCountry);
    }

    public function update(UpdateMediaCountryRequest $request, MediaCountry $mediaCountry): MediaCountryResource
    {
        $this->authorize('update', $mediaCountry);

        $mediaCountry->update($request->validated());

        return new MediaCountryResource($mediaCountry->fresh());
    }

    public function dependencies(MediaCountry $mediaCountry): JsonResponse
    {
        $this->authorize('view', $mediaCountry);

        return $this->dependenciesResponse($mediaCountry);
    }

    public function destroy(Request $request, MediaCountry $mediaCountry): JsonResponse
    {
        $this->authorize('delete', $mediaCountry);

        return $this->destroyWithConstraintCheck($request, $mediaCountry);
    }
}
