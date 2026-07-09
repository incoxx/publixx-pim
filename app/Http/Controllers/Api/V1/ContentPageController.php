<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreContentPageRequest;
use App\Http\Requests\Api\V1\UpdateContentPageRequest;
use App\Http\Resources\Api\V1\ContentPageResource;
use App\Http\Traits\Filterable;
use App\Models\ContentPage;
use App\Models\ContentSection;
use App\Services\Content\ContentDuplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ContentPageController extends Controller
{
    use Filterable;

    private const ALLOWED_INCLUDES = ['contentType', 'sections', 'sections.sectionType'];

    // Felder, die per Präfix-Suche (LIKE 'wert%') statt Exakt-Match gefiltert werden,
    // für das Quick-Lookup in der Content-Liste.
    private const ALLOWED_PREFIX_FILTERS = ['title', 'slug'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ContentPage::class);

        $query = ContentPage::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $rawFilters = $request->query('filter', []);

        $this->applyPrefixFilters($query, $rawFilters, self::ALLOWED_PREFIX_FILTERS);

        $this->applyFilters($query, array_intersect_key(
            $rawFilters,
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

    /**
     * GET /content-pages-search — Profisuche: durchsucht Titel/Slug sowie den
     * Sektionsinhalt (values_json) aller Seiten und liefert je Treffer einen
     * Textausschnitt mit Fundstelle (analog HierarchyNodeController::searchAll).
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContentPage::class);

        $search = trim((string) $request->query('search', ''));
        $like = '%' . $search . '%';

        $query = ContentPage::query()->with('contentType');

        if ($search !== '') {
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhereHas('sections', function ($sq) use ($like) {
                        $sq->whereRaw('JSON_SEARCH(values_json, "one", ?) IS NOT NULL', [$like]);
                    });
            });
        }

        $this->applySorting($query, $request, 'updated_at', 'desc');

        $pages = $query->paginate($this->getPerPage($request));

        // Treffer-Sektionen für die aktuelle Ergebnisseite in einem Rutsch nachladen (kein N+1).
        $sectionsByPage = $search === '' ? collect() : ContentSection::query()
            ->whereIn('content_page_id', $pages->getCollection()->pluck('id'))
            ->whereRaw('JSON_SEARCH(values_json, "one", ?) IS NOT NULL', [$like])
            ->with('sectionType')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('content_page_id');

        $data = $pages->getCollection()->map(function (ContentPage $page) use ($sectionsByPage, $search) {
            $snippet = null;
            foreach ($sectionsByPage->get($page->id, collect()) as $section) {
                $snippet = $this->extractSectionSnippet($section, $search);
                if ($snippet !== null) {
                    break;
                }
            }

            $titleMatch = $search !== '' && (stripos($page->title, $search) !== false || stripos($page->slug, $search) !== false);

            return [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'content_type' => $page->contentType?->name_de,
                'updated_at' => $page->updated_at,
                'match_in' => $snippet !== null ? 'section' : ($titleMatch ? 'title' : null),
                'matched_section_type' => $snippet['section_type'] ?? null,
                'snippet' => $snippet['snippet'] ?? null,
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'meta' => [
                'total' => $pages->total(),
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
            ],
        ]);
    }

    /**
     * Sucht rekursiv im values_json einer Sektion nach dem Suchbegriff und
     * liefert einen Textausschnitt mit Kontext um die Fundstelle.
     */
    private function extractSectionSnippet(ContentSection $section, string $search): ?array
    {
        $match = $this->findMatchingText($section->values_json ?? [], $search);

        if ($match === null) {
            return null;
        }

        $pos = mb_stripos($match, $search);
        $start = max(0, $pos - 60);
        $excerpt = mb_substr($match, $start, mb_strlen($search) + 120);
        $excerpt = ($start > 0 ? '…' : '') . $excerpt . (($start + mb_strlen($excerpt)) < mb_strlen($match) ? '…' : '');

        return [
            'section_type' => $section->sectionType?->name_de ?? $section->sectionType?->technical_name,
            'snippet' => $excerpt,
        ];
    }

    private function findMatchingText(mixed $value, string $search): ?string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $found = $this->findMatchingText($item, $search);
                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $plain = trim(strip_tags($value));

        return $plain !== '' && str_contains(mb_strtolower($plain), mb_strtolower($search)) ? $plain : null;
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
