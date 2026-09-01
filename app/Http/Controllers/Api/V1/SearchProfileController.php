<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Attribute;
use App\Models\HierarchyNode;
use App\Models\Manufacturer;
use App\Models\ProductType;
use App\Models\SearchProfile;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SearchProfileController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SearchProfile::class);

        $profiles = SearchProfile::visibleTo($request->user()->id)
            ->orderBy('name')
            ->get();

        $this->stripStaleReferences($profiles);

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SearchProfile::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_shared' => 'boolean',
            'search_text' => 'nullable|string|max:500',
            'search_mode' => 'nullable|string|in:like,soundex,regex',
            'status_filter' => 'nullable|string|in:active,draft,inactive,discontinued',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|uuid',
            'product_type_ids' => 'nullable|array',
            'product_type_ids.*' => 'string|uuid',
            'manufacturer_ids' => 'nullable|array',
            'manufacturer_ids.*' => 'string|uuid',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'string|uuid',
            'tag_match' => 'nullable|string|in:any,all',
            'attribute_filters' => 'nullable|array',
            'attribute_filter_groups' => 'nullable|array',
            'include_descendants' => 'nullable|boolean',
            'sort_field' => 'nullable|string|max:50',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $validated['user_id'] = $request->user()->id;

        $profile = SearchProfile::create($validated);

        // refresh(), damit Datenbank-Defaults (z.B. tag_match) in der Antwort stehen
        // und der Client sie nicht als null in seinen Zustand uebernimmt.
        return response()->json(['data' => $profile->refresh()], 201);
    }

    public function update(Request $request, SearchProfile $searchProfile): JsonResponse
    {
        $this->authorize('update', $searchProfile);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_shared' => 'boolean',
            'search_text' => 'nullable|string|max:500',
            'search_mode' => 'nullable|string|in:like,soundex,regex',
            'status_filter' => 'nullable|string|in:active,draft,inactive,discontinued',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|uuid',
            'product_type_ids' => 'nullable|array',
            'product_type_ids.*' => 'string|uuid',
            'manufacturer_ids' => 'nullable|array',
            'manufacturer_ids.*' => 'string|uuid',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'string|uuid',
            'tag_match' => 'nullable|string|in:any,all',
            'attribute_filters' => 'nullable|array',
            'attribute_filter_groups' => 'nullable|array',
            'include_descendants' => 'nullable|boolean',
            'sort_field' => 'nullable|string|max:50',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $searchProfile->update($validated);

        return response()->json(['data' => $searchProfile]);
    }

    public function dependencies(SearchProfile $searchProfile): JsonResponse
    {
        $this->authorize('view', $searchProfile);

        return $this->dependenciesResponse($searchProfile);
    }

    public function destroy(Request $request, SearchProfile $searchProfile): JsonResponse
    {
        $this->authorize('delete', $searchProfile);

        return $this->destroyWithConstraintCheck($request, $searchProfile);
    }

    /**
     * Entfernt Verweise auf geloeschte Entitaeten aus den Profilen, damit ein
     * geloeschter Knoten/Typ/Hersteller/Tag/Attribut nicht als unsichtbarer
     * Filter stehen bleibt und die Treffermenge stillschweigend einschraenkt.
     *
     * Bewusst ueber die gesamte Collection statt je Profil: pro Profil je eine
     * Existenzabfrage pro Feld waere ein N+1 auf einer Listen-Route. So bleibt
     * es bei einer Abfrage je Feld, unabhaengig von der Anzahl der Profile.
     *
     * @param \Illuminate\Support\Collection<int, SearchProfile> $profiles
     */
    private function stripStaleReferences(Collection $profiles): void
    {
        $idFields = [
            'category_ids' => HierarchyNode::class,
            'product_type_ids' => ProductType::class,
            'manufacturer_ids' => Manufacturer::class,
            'tag_ids' => Tag::class,
        ];

        $validIds = [];
        foreach ($idFields as $field => $model) {
            $ids = $profiles->flatMap(fn ($p) => $p->{$field} ?? [])->unique()->values();
            $validIds[$field] = $ids->isEmpty()
                ? []
                : array_flip($model::whereIn('id', $ids)->pluck('id')->all());
        }

        $attributeIds = $profiles->flatMap(fn ($p) => array_keys($p->attribute_filters ?? []))->unique()->values();
        $validAttributeIds = $attributeIds->isEmpty()
            ? []
            : array_flip(Attribute::whereIn('id', $attributeIds)->pluck('id')->all());

        foreach ($profiles as $profile) {
            $dirty = false;

            foreach (array_keys($idFields) as $field) {
                $ids = $profile->{$field} ?? [];
                if (empty($ids)) {
                    continue;
                }
                // Reihenfolge der Auswahl beibehalten
                $kept = array_values(array_filter($ids, fn ($id) => isset($validIds[$field][$id])));
                if (count($kept) !== count($ids)) {
                    $profile->{$field} = $kept;
                    $dirty = true;
                }
            }

            $filters = $profile->attribute_filters ?? [];
            if (!empty($filters)) {
                $kept = array_filter(
                    $filters,
                    fn ($value, $key) => isset($validAttributeIds[$key]),
                    ARRAY_FILTER_USE_BOTH,
                );
                if (count($kept) !== count($filters)) {
                    $profile->attribute_filters = $kept;
                    $dirty = true;
                }
            }

            // Bereinigung persistieren, damit sie sich nicht ansammelt
            if ($dirty) {
                $profile->saveQuietly();
            }
        }
    }
}
