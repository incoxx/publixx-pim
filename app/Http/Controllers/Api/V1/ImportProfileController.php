<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ImportProfile;
use App\Services\Import\ImportProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportProfileController extends Controller
{
    public function __construct(
        private readonly ImportProfileService $profileService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $profiles = ImportProfile::visibleTo($request->user()->id)
            ->with('productType')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_shared' => 'boolean',
            'product_type_id' => 'nullable|string|uuid|exists:product_types,id',
            'sku_column' => 'nullable|string|max:100',
            'column_mappings' => 'required|array',
            'column_mappings.*.source' => 'required|string',
            'column_mappings.*.target_attribute_id' => 'required|string|uuid|exists:attributes,id',
            'column_mappings.*.language' => 'nullable|string|max:5',
            'price_mappings' => 'nullable|array',
            'relation_mappings' => 'nullable|array',
        ]);

        $validated['user_id'] = $request->user()->id;

        $profile = ImportProfile::create($validated);

        return response()->json(['data' => $profile->load('productType')], 201);
    }

    public function update(Request $request, ImportProfile $importProfile): JsonResponse
    {
        if ($importProfile->user_id !== $request->user()->id) {
            abort(403, 'Nur eigene Profile können bearbeitet werden.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_shared' => 'boolean',
            'product_type_id' => 'nullable|string|uuid|exists:product_types,id',
            'sku_column' => 'nullable|string|max:100',
            'column_mappings' => 'sometimes|array',
            'column_mappings.*.source' => 'required|string',
            'column_mappings.*.target_attribute_id' => 'required|string|uuid|exists:attributes,id',
            'column_mappings.*.language' => 'nullable|string|max:5',
            'price_mappings' => 'nullable|array',
            'relation_mappings' => 'nullable|array',
        ]);

        $importProfile->update($validated);

        return response()->json(['data' => $importProfile->load('productType')]);
    }

    public function destroy(Request $request, ImportProfile $importProfile): JsonResponse
    {
        if ($importProfile->user_id !== $request->user()->id) {
            abort(403, 'Nur eigene Profile können gelöscht werden.');
        }

        $importProfile->delete();

        return response()->json(null, 204);
    }

    /**
     * Analysiert eine hochgeladene Excel-Datei und gibt Sheet-Namen + Spalten-Header zurück.
     */
    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:51200',
        ]);

        $analysis = $this->profileService->analyzeFile($request->file('file'));

        return response()->json(['data' => $analysis]);
    }

    /**
     * Vorschau: Wendet ein Import-Mapping auf eine Datei an und zeigt die ersten Zeilen.
     */
    public function preview(Request $request, ImportProfile $importProfile): JsonResponse
    {
        if ($importProfile->user_id !== $request->user()->id) {
            abort(403, 'Nur eigene Profile können in der Vorschau angezeigt werden.');
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:51200',
            'max_rows' => 'nullable|integer|min:1|max:100',
        ]);

        $maxRows = $request->integer('max_rows', 20);
        $preview = $this->profileService->preview($importProfile, $request->file('file'), $maxRows);

        return response()->json(['data' => $preview]);
    }
}
