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
use App\Support\TechnicalName;
use Illuminate\Support\Facades\DB;

class TagController extends Controller
{
    use ChecksDeletionConstraints;

    private const ALLOWED_FILTERS = ['is_active', 'tag_group_id'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tag::class);

        $query = Tag::query()->with('group')->withCount(['products', 'media']);

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
        $data['technical_name'] = TechnicalName::resolve(
            $data['technical_name'] ?? null,
            $data['name_de'],
            fn (string $candidate) => Tag::where('technical_name', $candidate)->exists(),
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
        $this->authorize('viewAny', Tag::class);

        return $this->syncTags($request, $product, 'tags');
    }

    /**
     * PUT /media/{medium}/tags — Tags eines Mediums komplett setzen.
     */
    public function syncMediaTags(Request $request, Media $medium): JsonResponse
    {
        $this->authorize('update', $medium);
        $this->authorize('viewAny', Tag::class);

        return $this->syncTags($request, $medium, 'tags');
    }

    /**
     * POST /products/bulk-tags — Tags für viele Produkte auf einmal setzen.
     *
     * `mode` entscheidet über die Semantik, weil bei einer Massenoperation alle
     * drei Fälle vorkommen und stillschweigendes Überschreiben teuer wäre:
     *  - add     (Standard): Tags ergänzen, bestehende bleiben
     *  - remove:            nur die genannten Tags entfernen
     *  - replace:           genau diese Tags setzen, alle anderen entfernen
     */
    public function bulkAssignProducts(Request $request): JsonResponse
    {
        // Tags vergeben heißt Produkte ändern — dieselbe Berechtigung wie die
        // übrigen Massenoperationen (BulkUpdateController).
        $this->authorize('bulkUpdate', Product::class);
        $this->authorize('viewAny', Tag::class);

        $validated = $request->validate([
            // Obergrenze wie beim BulkUpdateController: "Alle Seiten auswählen"
            // kann sonst den kompletten Katalog in einen Request kippen.
            'product_ids' => 'required|array|min:1|max:5000',
            // Bewusst ohne exists: die Regel läuft je Element einmal gegen die
            // Datenbank — bei 5000 IDs also 5000 Abfragen. Unbekannte IDs fallen
            // ohnehin durch das whereIn unten raus.
            'product_ids.*' => 'uuid|distinct',
            'tag_ids' => 'required|array|min:1|max:50',
            'tag_ids.*' => 'uuid|distinct|exists:tags,id',
            'mode' => 'sometimes|string|in:add,remove,replace',
        ]);

        $productIds = $validated['product_ids'];
        $tagIds = $validated['tag_ids'];
        $mode = $validated['mode'] ?? 'add';

        // Chunking: Massenoperationen laufen auch über mehrere tausend Produkte,
        // die IDs dürfen nicht als eine einzige riesige IN-Liste in die Query.
        $affected = 0;

        DB::transaction(function () use ($productIds, $tagIds, $mode, &$affected) {
            foreach (array_chunk($productIds, 500) as $chunk) {
                foreach (Product::whereIn('id', $chunk)->get() as $product) {
                    match ($mode) {
                        // syncWithoutDetaching statt sync: bestehende Tags bleiben
                        'add' => $product->tags()->syncWithoutDetaching($tagIds),
                        'remove' => $product->tags()->detach($tagIds),
                        'replace' => $product->tags()->sync($tagIds),
                    };
                    $affected++;
                }
            }
        });

        // Tatsächlich geänderte Produkte melden, nicht die übergebenen IDs —
        // inzwischen gelöschte IDs sollen die Rückmeldung nicht aufblähen.
        return response()->json([
            'message' => $affected.' Produkt(e) aktualisiert.',
            'products_count' => $affected,
            'mode' => $mode,
        ]);
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

}
