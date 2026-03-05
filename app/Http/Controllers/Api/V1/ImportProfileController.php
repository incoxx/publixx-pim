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
        private readonly ImportProfileService $importService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ImportProfile::class);

        $profiles = ImportProfile::visibleTo($request->user()->id)
            ->with('productType')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ImportProfile::class);

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
        $this->authorize('update', $importProfile);

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
        $this->authorize('delete', $importProfile);

        $importProfile->delete();

        return response()->json(null, 204);
    }

    /**
     * Analysiert eine hochgeladene Excel-Datei und gibt Sheet-Namen + Spalten-Header zurück.
     */
    public function analyze(Request $request): JsonResponse
    {
        $this->authorize('create', ImportProfile::class);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:51200',
        ]);

        $analysis = $this->importService->analyzeFile($request->file('file'));

        return response()->json(['data' => $analysis]);
    }

    /**
     * Vorschau: Wendet ein Import-Mapping auf eine Datei an und zeigt die ersten Zeilen.
     */
    public function preview(Request $request, ImportProfile $importProfile): JsonResponse
    {
        $this->authorize('preview', $importProfile);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:51200',
            'max_rows' => 'nullable|integer|min:1|max:100',
        ]);

        $maxRows = $request->integer('max_rows', 20);
        $preview = $this->importService->preview($importProfile, $request->file('file'), $maxRows);

        return response()->json(['data' => $preview]);
    }

    /**
     * Auto-Generate: Erstellt fehlende Attribute aus Excel-Spalten,
     * ordnet sie einer AttributeView und einer Kategorie (HierarchyNode) zu.
     */
    public function autoGenerateAttributes(Request $request): JsonResponse
    {
        $this->authorize('create', ImportProfile::class);

        $validated = $request->validate([
            'hierarchy_node_id' => 'required|string|uuid|exists:hierarchy_nodes,id',
            'attribute_view_id' => 'required|string|uuid|exists:attribute_views,id',
            'attribute_type_id' => 'nullable|string|uuid|exists:attribute_types,id',
            'columns' => 'required|array|min:1',
            'columns.*.header' => 'required|string|max:255',
            'columns.*.auto_generate' => 'required|boolean',
            'columns.*.detected_type' => 'required|string|in:String,Number,Float,Date,Flag,Selection',
            'columns.*.override_type' => 'nullable|string|in:String,Number,Float,Date,Flag,Selection',
        ]);

        $result = $this->importService->autoGenerateAttributes(
            $validated['columns'],
            $validated['hierarchy_node_id'],
            $validated['attribute_view_id'],
            $validated['attribute_type_id'] ?? null,
        );

        return response()->json(['data' => $result]);
    }
}
