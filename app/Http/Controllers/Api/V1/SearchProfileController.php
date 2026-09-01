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

class SearchProfileController extends Controller
{
    use ChecksDeletionConstraints;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SearchProfile::class);

        $profiles = SearchProfile::visibleTo($request->user()->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $this->stripStaleReferences($p));

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
     * Strip stale category/attribute references from a search profile
     * so deleted entities don't cause broken filters in the frontend.
     */
    private function stripStaleReferences(SearchProfile $profile): SearchProfile
    {
        $dirty = false;

        if (!empty($profile->category_ids)) {
            $validNodeIds = HierarchyNode::whereIn('id', $profile->category_ids)->pluck('id')->toArray();
            if (count($validNodeIds) !== count($profile->category_ids)) {
                $profile->category_ids = array_values($validNodeIds);
                $dirty = true;
            }
        }

        // Gelöschte Produkttypen/Hersteller/Tags würden sonst als unsichtbarer
        // Filter im Profil stehen bleiben und die Treffermenge stillschweigend
        // einschränken — gleiche Behandlung wie bei den Kategorien oben.
        $referenceLists = [
            'product_type_ids' => ProductType::class,
            'manufacturer_ids' => Manufacturer::class,
            'tag_ids' => Tag::class,
        ];

        foreach ($referenceLists as $field => $model) {
            $ids = $profile->{$field} ?? [];
            if (empty($ids)) {
                continue;
            }
            $validIds = $model::whereIn('id', $ids)->pluck('id')->toArray();
            if (count($validIds) !== count($ids)) {
                // Reihenfolge der Auswahl beibehalten
                $validSet = array_flip($validIds);
                $profile->{$field} = array_values(array_filter($ids, fn ($id) => isset($validSet[$id])));
                $dirty = true;
            }
        }

        if (!empty($profile->attribute_filters)) {
            $filterAttrIds = array_keys($profile->attribute_filters);
            $validAttrIds = Attribute::whereIn('id', $filterAttrIds)->pluck('id')->toArray();
            if (count($validAttrIds) !== count($filterAttrIds)) {
                $validSet = array_flip($validAttrIds);
                $cleaned = array_filter(
                    $profile->attribute_filters,
                    fn ($v, $k) => isset($validSet[$k]),
                    ARRAY_FILTER_USE_BOTH,
                );
                $profile->attribute_filters = $cleaned;
                $dirty = true;
            }
        }

        // Persist cleanup so stale data doesn't accumulate
        if ($dirty) {
            $profile->saveQuietly();
        }

        return $profile;
    }
}
