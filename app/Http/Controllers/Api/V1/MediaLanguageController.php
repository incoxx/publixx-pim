<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreMediaLanguageRequest;
use App\Http\Requests\Api\V1\UpdateMediaLanguageRequest;
use App\Http\Resources\Api\V1\MediaLanguageResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\MediaLanguage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaLanguageController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MediaLanguage::class);

        $query = MediaLanguage::query();
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return MediaLanguageResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreMediaLanguageRequest $request): JsonResponse
    {
        $this->authorize('create', MediaLanguage::class);

        $language = MediaLanguage::create($request->validated());

        return (new MediaLanguageResource($language))
            ->response()
            ->setStatusCode(201);
    }

    public function show(MediaLanguage $mediaLanguage): MediaLanguageResource
    {
        $this->authorize('view', $mediaLanguage);

        return new MediaLanguageResource($mediaLanguage);
    }

    public function update(UpdateMediaLanguageRequest $request, MediaLanguage $mediaLanguage): MediaLanguageResource
    {
        $this->authorize('update', $mediaLanguage);

        $mediaLanguage->update($request->validated());

        return new MediaLanguageResource($mediaLanguage->fresh());
    }

    public function dependencies(MediaLanguage $mediaLanguage): JsonResponse
    {
        $this->authorize('view', $mediaLanguage);

        return $this->dependenciesResponse($mediaLanguage);
    }

    public function destroy(Request $request, MediaLanguage $mediaLanguage): JsonResponse
    {
        $this->authorize('delete', $mediaLanguage);

        return $this->destroyWithConstraintCheck($request, $mediaLanguage);
    }
}
