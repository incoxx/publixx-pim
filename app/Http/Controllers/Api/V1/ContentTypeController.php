<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreContentTypeRequest;
use App\Http\Requests\Api\V1\UpdateContentTypeRequest;
use App\Http\Resources\Api\V1\ContentTypeResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Http\Traits\Filterable;
use App\Models\ContentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContentTypeController extends Controller
{
    use ChecksDeletionConstraints, Filterable;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContentType::class);

        $query = ContentType::query()->withCount('pages');

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(['is_active', 'layout_hint'])
        ));
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return ContentTypeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreContentTypeRequest $request): JsonResponse
    {
        $this->authorize('create', ContentType::class);

        $type = ContentType::create($request->validated());

        return (new ContentTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ContentType $contentType): ContentTypeResource
    {
        $this->authorize('view', $contentType);

        return new ContentTypeResource($contentType->loadCount('pages'));
    }

    public function update(UpdateContentTypeRequest $request, ContentType $contentType): ContentTypeResource
    {
        $this->authorize('update', $contentType);

        $contentType->update($request->validated());

        return new ContentTypeResource($contentType->fresh());
    }

    public function destroy(Request $request, ContentType $contentType): JsonResponse
    {
        $this->authorize('delete', $contentType);

        if (!$request->boolean('force')) {
            return $this->destroyWithConstraintCheck($request, $contentType);
        }

        // content_pages.content_type_id ist FK restrict — auch mit force nicht
        // kaskadierbar; sonst QueryException → 500. Sauber als 409 abweisen.
        if ($contentType->pages()->exists()) {
            return response()->json([
                'message' => 'Seitentyp wird noch von Content-Seiten verwendet.',
            ], 409);
        }

        $contentType->delete();

        return response()->json(null, 204);
    }
}
