<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ColumnProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColumnProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ColumnProfile::class);

        $query = ColumnProfile::visibleTo($request->user()->id);

        if ($request->has('context')) {
            $query->where('context', $request->input('context'));
        }

        $profiles = $query->orderBy('name')->get();

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ColumnProfile::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_shared' => 'boolean',
            'context' => 'nullable|string|max:50',
            'visible_keys' => 'required|array',
            'visible_keys.*' => 'string|max:255',
        ]);

        $validated['user_id'] = $request->user()->id;

        $profile = ColumnProfile::create($validated);

        return response()->json(['data' => $profile], 201);
    }

    public function update(Request $request, ColumnProfile $columnProfile): JsonResponse
    {
        $this->authorize('update', $columnProfile);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_shared' => 'boolean',
            'visible_keys' => 'sometimes|array',
            'visible_keys.*' => 'string|max:255',
        ]);

        $columnProfile->update($validated);

        return response()->json(['data' => $columnProfile]);
    }

    public function destroy(Request $request, ColumnProfile $columnProfile): JsonResponse
    {
        $this->authorize('delete', $columnProfile);

        $columnProfile->delete();

        return response()->json(null, 204);
    }
}
