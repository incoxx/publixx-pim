<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\SearchProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profiles = SearchProfile::visibleTo($request->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_shared' => 'boolean',
            'search_text' => 'nullable|string|max:500',
            'search_mode' => 'nullable|string|in:like,soundex,regex',
            'status_filter' => 'nullable|string|in:active,draft,inactive,discontinued',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|uuid',
            'attribute_filters' => 'nullable|array',
            'include_descendants' => 'nullable|boolean',
            'sort_field' => 'nullable|string|max:50',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $validated['user_id'] = $request->user()->id;

        $profile = SearchProfile::create($validated);

        return response()->json(['data' => $profile], 201);
    }

    public function update(Request $request, SearchProfile $searchProfile): JsonResponse
    {
        if ($searchProfile->user_id !== $request->user()->id) {
            abort(403, 'Nur eigene Profile können bearbeitet werden.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_shared' => 'boolean',
            'search_text' => 'nullable|string|max:500',
            'search_mode' => 'nullable|string|in:like,soundex,regex',
            'status_filter' => 'nullable|string|in:active,draft,inactive,discontinued',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string|uuid',
            'attribute_filters' => 'nullable|array',
            'include_descendants' => 'nullable|boolean',
            'sort_field' => 'nullable|string|max:50',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        $searchProfile->update($validated);

        return response()->json(['data' => $searchProfile]);
    }

    public function destroy(Request $request, SearchProfile $searchProfile): JsonResponse
    {
        if ($searchProfile->user_id !== $request->user()->id) {
            abort(403, 'Nur eigene Profile können gelöscht werden.');
        }

        $searchProfile->delete();

        return response()->json(null, 204);
    }
}
