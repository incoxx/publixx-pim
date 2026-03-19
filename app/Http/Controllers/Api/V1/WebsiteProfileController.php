<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\WebsiteProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsiteProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WebsiteProfile::class);

        $profiles = WebsiteProfile::visibleTo($request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'user_id', 'is_shared', 'is_active', 'created_at', 'updated_at']);

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WebsiteProfile::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_shared' => 'boolean',
            'payload' => 'required|array',
        ]);

        $profile = WebsiteProfile::create([
            'name' => $validated['name'],
            'user_id' => $request->user()->id,
            'is_shared' => $validated['is_shared'] ?? false,
            'is_active' => false,
            'payload' => $validated['payload'],
        ]);

        return response()->json(['data' => $profile], 201);
    }

    public function update(Request $request, WebsiteProfile $websiteProfile): JsonResponse
    {
        $this->authorize('update', $websiteProfile);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'is_shared' => 'sometimes|boolean',
            'payload' => 'sometimes|required|array',
        ]);

        $websiteProfile->update($validated);

        return response()->json(['data' => $websiteProfile]);
    }

    public function destroy(Request $request, WebsiteProfile $websiteProfile): JsonResponse
    {
        $this->authorize('delete', $websiteProfile);

        if ($websiteProfile->is_active) {
            return response()->json([
                'message' => 'Das aktive Profil kann nicht gelöscht werden.',
            ], 409);
        }

        $websiteProfile->delete();

        return response()->json(null, 204);
    }

    public function activate(Request $request, WebsiteProfile $websiteProfile): JsonResponse
    {
        $this->authorize('update', $websiteProfile);

        DB::transaction(function () use ($websiteProfile) {
            WebsiteProfile::where('is_active', true)->update(['is_active' => false]);
            $websiteProfile->update(['is_active' => true]);
        });

        return response()->json(['data' => $websiteProfile->fresh()]);
    }
}
