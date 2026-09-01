<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTagRequest;
use App\Http\Requests\Api\V1\UpdateTagRequest;
use App\Http\Resources\Api\V1\TagResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Media;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class TagController extends Controller
{
    use ChecksDeletionConstraints;

    private const ALLOWED_FILTERS = ['is_active'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tag::class);

        $query = Tag::query()->withCount(['products', 'media']);

        $this->applyFilters($query, array_intersect_key(
            (array) $request->query('filter', []),
            array_flip(self::ALLOWED_FILTERS)
        ));
        $this->applySearch($query, $request, ['technical_name', 'name_de', 'name_en']);
        $this->applySorting($query, $request, 'name_de', 'asc');

        return TagResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $this->authorize('create', Tag::class);

        $data = $request->validated();
        $data['technical_name'] = $this->resolveTechnicalName(
            $data['technical_name'] ?? null,
            $data['name_de']
        );

        $tag = Tag::create($data);

        return (new TagResource($tag->loadCount(['products', 'media'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Tag $tag): TagResource
    {
        $this->authorize('view', $tag);

        return new TagResource($tag->loadCount(['products', 'media']));
    }

    public function update(UpdateTagRequest $request, Tag $tag): TagResource
    {
        $this->authorize('update', $tag);

        $tag->update($request->validated());

        return new TagResource($tag->fresh()->loadCount(['products', 'media']));
    }

    public function dependencies(Tag $tag): JsonResponse
    {
        $this->authorize('view', $tag);

        return $this->dependenciesResponse($tag);
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        return $this->destroyWithConstraintCheck($request, $tag);
    }

    /**
     * PUT /products/{product}/tags — Tags eines Produkts komplett setzen.
     * Die Reihenfolge der übergebenen IDs wird als sort_order gespeichert.
     */
    public function syncProductTags(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        return $this->syncTags($request, $product, 'tags');
    }

    /**
     * PUT /media/{medium}/tags — Tags eines Mediums komplett setzen.
     */
    public function syncMediaTags(Request $request, Media $medium): JsonResponse
    {
        $this->authorize('update', $medium);

        return $this->syncTags($request, $medium, 'tags');
    }

    /**
     * Gemeinsame Sync-Logik für Produkte und Medien: validiert die IDs, setzt die
     * Zuordnung neu (sync = entfernt nicht mehr übergebene) und gibt die Tags in
     * der gespeicherten Reihenfolge zurück.
     */
    private function syncTags(Request $request, Product|Media $model, string $relation): JsonResponse
    {
        $request->validate([
            'tag_ids' => 'present|array',
            'tag_ids.*' => 'uuid|distinct|exists:tags,id',
        ]);

        $syncData = [];
        foreach (array_values($request->input('tag_ids', [])) as $index => $tagId) {
            $syncData[$tagId] = ['sort_order' => $index];
        }

        $model->{$relation}()->sync($syncData);

        return response()->json([
            'data' => TagResource::collection($model->load($relation)->{$relation}),
        ]);
    }

    /**
     * Technischer Name als stabiler Schlüssel (Import/Export). Wird er nicht
     * mitgegeben, entsteht er aus dem deutschen Namen; bei Kollision wird ein
     * Zähler angehängt, damit der Nutzer im Dialog kein Pflichtfeld ausfüllen muss.
     */
    private function resolveTechnicalName(?string $given, string $nameDe): string
    {
        if ($given !== null && $given !== '') {
            return $given;
        }

        // Umlaute explizit vor der Transliteration ersetzen (sonst macht Str::slug
        // aus "für" ein "fur") — gleiche Regel wie BmecatFormatImporter::sanitizeTechnicalName().
        $normalized = str_replace(
            ['Ä', 'ä', 'Ö', 'ö', 'Ü', 'ü', 'ß'],
            ['Ae', 'ae', 'Oe', 'oe', 'Ue', 'ue', 'ss'],
            $nameDe,
        );

        $base = Str::limit(Str::slug($normalized), 90, '') ?: 'tag';
        $candidate = $base;
        $suffix = 2;

        while (Tag::where('technical_name', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
