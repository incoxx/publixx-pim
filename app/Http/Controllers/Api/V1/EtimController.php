<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Etim\EtimClass;
use App\Models\Etim\EtimClassMapping;
use App\Models\Etim\EtimFeature;
use App\Models\Etim\EtimFeatureMapping;
use App\Models\Etim\EtimUnitMapping;
use App\Models\Etim\EtimValueMapping;
use App\Models\Etim\EtimVersion;
use App\Models\HierarchyNode;
use App\Services\Etim\EtimImportService;
use App\Services\Etim\EtimMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EtimController extends Controller
{
    public function __construct(
        private readonly EtimMappingService $mappingService,
    ) {}

    // ─── ETIM-Versionen ─────────────────────────────────────────────

    public function versions(): JsonResponse
    {
        $versions = EtimVersion::orderBy('version', 'desc')->get();
        return response()->json(['data' => $versions]);
    }

    // ─── ETIM-Klassen ───────────────────────────────────────────────

    public function classes(Request $request): JsonResponse
    {
        $request->validate([
            'version_id' => 'required|string|exists:etim_versions,id',
            'search' => 'sometimes|string|max:255',
            'group_id' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:500',
        ]);

        $query = EtimClass::where('etim_version_id', $request->input('version_id'))
            ->with('etimGroup');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name_de', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if ($request->filled('group_id')) {
            $query->where('etim_group_id', $request->input('group_id'));
        }

        $perPage = $request->input('per_page', 50);
        return response()->json($query->orderBy('code')->paginate($perPage));
    }

    public function classFeatures(string $classId): JsonResponse
    {
        $class = EtimClass::findOrFail($classId);
        $features = $class->features()->with('etimUnit')->get();
        return response()->json(['data' => $features]);
    }

    // ─── ETIM-Import ────────────────────────────────────────────────

    public function import(Request $request, EtimImportService $importService): JsonResponse
    {
        $request->validate([
            'version' => 'required|string|max:20',
            'directory' => 'required|string',
        ]);

        $directory = $request->input('directory');
        if (!is_dir($directory)) {
            return response()->json(['error' => "Verzeichnis nicht gefunden: {$directory}"], 422);
        }

        $result = $importService->import($request->input('version'), $directory);

        return response()->json($result);
    }

    // ─── Klassen-Mappings ───────────────────────────────────────────

    public function classMappings(Request $request): JsonResponse
    {
        $query = EtimClassMapping::with(['hierarchyNode', 'etimClass.etimGroup', 'etimVersion']);

        if ($request->filled('etim_version_id')) {
            $query->where('etim_version_id', $request->input('etim_version_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeClassMapping(Request $request): JsonResponse
    {
        $request->validate([
            'hierarchy_node_id' => 'required|string|exists:hierarchy_nodes,id',
            'etim_class_id' => 'required|string|exists:etim_classes,id',
            'etim_version_id' => 'required|string|exists:etim_versions,id',
        ]);

        $mapping = $this->mappingService->createClassMapping(
            $request->input('hierarchy_node_id'),
            $request->input('etim_class_id'),
            $request->input('etim_version_id'),
            'manual',
            1.0,
            $request->user()?->id,
        );

        return response()->json(['data' => $mapping->load(['hierarchyNode', 'etimClass'])], 201);
    }

    public function destroyClassMapping(string $id): JsonResponse
    {
        EtimClassMapping::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ─── Feature-Mappings ───────────────────────────────────────────

    public function featureMappings(Request $request): JsonResponse
    {
        $query = EtimFeatureMapping::with(['attribute', 'etimFeature.etimUnit', 'etimVersion']);

        if ($request->filled('etim_version_id')) {
            $query->where('etim_version_id', $request->input('etim_version_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeFeatureMapping(Request $request): JsonResponse
    {
        $request->validate([
            'attribute_id' => 'required|string|exists:attributes,id',
            'etim_feature_id' => 'required|string|exists:etim_features,id',
            'etim_version_id' => 'required|string|exists:etim_versions,id',
        ]);

        $mapping = $this->mappingService->createFeatureMapping(
            $request->input('attribute_id'),
            $request->input('etim_feature_id'),
            $request->input('etim_version_id'),
            'manual',
            1.0,
            $request->user()?->id,
        );

        return response()->json(['data' => $mapping->load(['attribute', 'etimFeature'])], 201);
    }

    public function destroyFeatureMapping(string $id): JsonResponse
    {
        EtimFeatureMapping::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // ─── Auto-Suggest ───────────────────────────────────────────────

    public function suggestClassMapping(Request $request): JsonResponse
    {
        $request->validate([
            'hierarchy_node_id' => 'required|string|exists:hierarchy_nodes,id',
            'etim_version_id' => 'required|string|exists:etim_versions,id',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $node = HierarchyNode::findOrFail($request->input('hierarchy_node_id'));
        $suggestions = $this->mappingService->suggestClassMapping(
            $node,
            $request->input('etim_version_id'),
            $request->input('limit', 10),
        );

        // EtimClass-Objekte für JSON-Serialisierung vorbereiten
        $result = array_map(fn ($s) => [
            'etim_class' => $s['etim_class'],
            'confidence' => $s['confidence'],
            'reasons' => $s['reasons'],
        ], $suggestions);

        return response()->json(['data' => $result]);
    }

    public function suggestFeatureMappings(Request $request): JsonResponse
    {
        $request->validate([
            'etim_class_id' => 'required|string|exists:etim_classes,id',
            'etim_version_id' => 'required|string|exists:etim_versions,id',
            'attribute_ids' => 'sometimes|array',
            'attribute_ids.*' => 'string|exists:attributes,id',
        ]);

        $etimClass = EtimClass::findOrFail($request->input('etim_class_id'));

        $attributes = $request->has('attribute_ids')
            ? Attribute::whereIn('id', $request->input('attribute_ids'))->get()
            : Attribute::where('status', 'active')->get();

        $suggestions = $this->mappingService->suggestFeatureMappings(
            $etimClass,
            $attributes,
            $request->input('etim_version_id'),
        );

        // Für JSON-Serialisierung
        $result = [];
        foreach ($suggestions as $attributeId => $suggestion) {
            $result[] = [
                'attribute_id' => $attributeId,
                'etim_feature' => $suggestion['etim_feature'],
                'confidence' => $suggestion['confidence'],
                'reasons' => $suggestion['reasons'],
            ];
        }

        return response()->json(['data' => $result]);
    }

    public function autoMap(Request $request): JsonResponse
    {
        $request->validate([
            'etim_version_id' => 'required|string|exists:etim_versions,id',
        ]);

        $stats = $this->mappingService->autoMapFromSourceSystem(
            $request->input('etim_version_id')
        );

        return response()->json(['data' => $stats]);
    }
}
