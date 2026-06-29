<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreContentPageRequest;
use App\Http\Requests\Api\V1\UpdateContentPageRequest;
use App\Http\Resources\Api\V1\ContentPageResource;
use App\Http\Traits\Filterable;
use App\Models\ContentPage;
use App\Services\Content\ContentDuplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ContentPageController extends Controller
{
    use Filterable;

    private const ALLOWED_INCLUDES = ['contentType', 'sections', 'sections.sectionType'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContentPage::class);

        $query = ContentPage::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(['content_type_id', 'status'])
        ));

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $this->applySorting($query, $request, 'updated_at', 'desc');

        return ContentPageResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreContentPageRequest $request): JsonResponse
    {
        $this->authorize('create', ContentPage::class);

        $data = $request->validated();
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $page = ContentPage::create($data);

        return (new ContentPageResource($page->load('contentType')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Seite duplizieren (inkl. aller Sektionen) — als neuen Entwurf.
     */
    public function duplicate(ContentPage $contentPage, ContentDuplicator $duplicator): JsonResponse
    {
        $this->authorize('view', $contentPage);
        $this->authorize('create', ContentPage::class);

        $copy = $duplicator->duplicatePage($contentPage);

        return (new ContentPageResource($copy->load('contentType')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ContentPage $contentPage): ContentPageResource
    {
        $this->authorize('view', $contentPage);

        $contentPage->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new ContentPageResource($contentPage);
    }

    public function update(UpdateContentPageRequest $request, ContentPage $contentPage): ContentPageResource
    {
        $this->authorize('update', $contentPage);

        $data = $request->validated();
        $data['updated_by'] = Auth::id();

        $contentPage->update($data);

        return new ContentPageResource($contentPage->fresh()->load('contentType'));
    }

    public function destroy(ContentPage $contentPage): JsonResponse
    {
        $this->authorize('delete', $contentPage);

        // content_sections hängen per ON DELETE CASCADE.
        $contentPage->delete();

        return response()->json(null, 204);
    }
}
