<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ExportProfile;
use App\Services\Export\ExportProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProfileController extends Controller
{
    public function __construct(
        private readonly ExportProfileService $exportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $profiles = ExportProfile::visibleTo($request->user()->id)
            ->with('searchProfile')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_shared' => 'boolean',
            'search_profile_id' => 'nullable|string|uuid|exists:search_profiles,id',
            'include_products' => 'boolean',
            'include_attributes' => 'boolean',
            'include_hierarchies' => 'boolean',
            'include_prices' => 'boolean',
            'include_relations' => 'boolean',
            'include_media' => 'boolean',
            'include_variants' => 'boolean',
            'attribute_ids' => 'nullable|array',
            'attribute_ids.*' => 'string|uuid',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:5',
            'format' => 'nullable|string|in:excel,csv,json,xml',
            'file_name_template' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = $request->user()->id;

        $profile = ExportProfile::create($validated);

        return response()->json(['data' => $profile->load('searchProfile')], 201);
    }

    public function update(Request $request, ExportProfile $exportProfile): JsonResponse
    {
        if ($exportProfile->user_id !== $request->user()->id) {
            abort(403, 'Nur eigene Profile können bearbeitet werden.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_shared' => 'boolean',
            'search_profile_id' => 'nullable|string|uuid|exists:search_profiles,id',
            'include_products' => 'boolean',
            'include_attributes' => 'boolean',
            'include_hierarchies' => 'boolean',
            'include_prices' => 'boolean',
            'include_relations' => 'boolean',
            'include_media' => 'boolean',
            'include_variants' => 'boolean',
            'attribute_ids' => 'nullable|array',
            'attribute_ids.*' => 'string|uuid',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:5',
            'format' => 'nullable|string|in:excel,csv,json,xml',
            'file_name_template' => 'nullable|string|max:255',
        ]);

        $exportProfile->update($validated);

        return response()->json(['data' => $exportProfile->load('searchProfile')]);
    }

    public function destroy(Request $request, ExportProfile $exportProfile): JsonResponse
    {
        if ($exportProfile->user_id !== $request->user()->id) {
            abort(403, 'Nur eigene Profile können gelöscht werden.');
        }

        $exportProfile->delete();

        return response()->json(null, 204);
    }

    public function execute(Request $request, ExportProfile $exportProfile): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'file_name' => 'nullable|string|max:255',
        ]);

        $fileName = $validated['file_name'] ?? null;

        return $this->exportService->execute($exportProfile, $fileName);
    }
}
