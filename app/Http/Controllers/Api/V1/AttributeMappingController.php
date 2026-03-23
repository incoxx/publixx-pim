<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeMappingRequest;
use App\Http\Requests\Api\V1\UpdateAttributeMappingRequest;
use App\Http\Resources\Api\V1\AttributeMappingResource;
use App\Jobs\SyncAttributeMappings;
use App\Models\AttributeMapping;
use App\Models\AttributeMappingRule;
use App\Models\Product;
use App\Services\Export\AttributeMappingSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AttributeMappingController extends Controller
{
    /**
     * Alle Mappings auflisten (gefiltert nach Quell- und/oder Ziel-Hierarchie).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AttributeMapping::class);

        $query = AttributeMapping::with([
            'sourceHierarchy', 'sourceAttribute',
            'targetHierarchy', 'targetAttribute',
        ]);

        if ($request->filled('source_hierarchy_id')) {
            $query->where('source_hierarchy_id', $request->input('source_hierarchy_id'));
        }
        if ($request->filled('target_hierarchy_id')) {
            $query->where('target_hierarchy_id', $request->input('target_hierarchy_id'));
        }

        $this->applySorting($query, $request, 'created_at', 'desc');

        return AttributeMappingResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * Neues Mapping erstellen.
     */
    public function store(StoreAttributeMappingRequest $request): JsonResponse
    {
        $this->authorize('create', AttributeMapping::class);

        $mapping = AttributeMapping::create($request->validated());
        $mapping->load(['sourceHierarchy', 'sourceAttribute', 'targetHierarchy', 'targetAttribute']);

        return (new AttributeMappingResource($mapping))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Einzelnes Mapping anzeigen.
     */
    public function show(AttributeMapping $attributeMapping): AttributeMappingResource
    {
        $this->authorize('view', $attributeMapping);

        $attributeMapping->load(['sourceHierarchy', 'sourceAttribute', 'targetHierarchy', 'targetAttribute']);

        return new AttributeMappingResource($attributeMapping);
    }

    /**
     * Mapping aktualisieren.
     */
    public function update(UpdateAttributeMappingRequest $request, AttributeMapping $attributeMapping): AttributeMappingResource
    {
        $this->authorize('update', $attributeMapping);

        $attributeMapping->update($request->validated());

        return new AttributeMappingResource(
            $attributeMapping->fresh(['sourceHierarchy', 'sourceAttribute', 'targetHierarchy', 'targetAttribute'])
        );
    }

    /**
     * Mapping löschen.
     */
    public function destroy(AttributeMapping $attributeMapping): JsonResponse
    {
        $this->authorize('delete', $attributeMapping);

        $attributeMapping->delete();

        return response()->json(null, 204);
    }

    /**
     * Bulk-Import: Mehrere Mappings gleichzeitig erstellen/aktualisieren.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $this->authorize('create', AttributeMapping::class);

        $request->validate([
            'mappings' => 'required|array|min:1|max:500',
            'mappings.*.source_hierarchy_id' => 'required|uuid|exists:hierarchies,id',
            'mappings.*.source_attribute_id' => 'required|uuid|exists:attributes,id',
            'mappings.*.target_hierarchy_id' => 'required|uuid|exists:hierarchies,id',
            'mappings.*.target_attribute_id' => 'required|uuid|exists:attributes,id',
            'mappings.*.transform_type' => 'sometimes|string|in:direct,unit_convert,value_map',
            'mappings.*.transform_config' => 'nullable|array',
        ]);

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($request, &$created, &$updated) {
            foreach ($request->input('mappings') as $data) {
                $mapping = AttributeMapping::updateOrCreate(
                    [
                        'source_hierarchy_id' => $data['source_hierarchy_id'],
                        'source_attribute_id' => $data['source_attribute_id'],
                        'target_hierarchy_id' => $data['target_hierarchy_id'],
                        'target_attribute_id' => $data['target_attribute_id'],
                    ],
                    [
                        'transform_type' => $data['transform_type'] ?? 'direct',
                        'transform_config' => $data['transform_config'] ?? null,
                    ]
                );

                if ($mapping->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        return response()->json([
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    // ─── Bedingte Regeln ─────────────────────────────────────

    /**
     * Alle Regeln auflisten (gefiltert nach Hierarchie-Paar).
     */
    public function rules(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttributeMapping::class);

        $query = AttributeMappingRule::with('conditionAttribute');

        if ($request->filled('source_hierarchy_id')) {
            $query->where('source_hierarchy_id', $request->input('source_hierarchy_id'));
        }
        if ($request->filled('target_hierarchy_id')) {
            $query->where('target_hierarchy_id', $request->input('target_hierarchy_id'));
        }

        $rules = $query->orderBy('sort_order')->get();

        return response()->json(['data' => $rules]);
    }

    /**
     * Neue bedingte Regel erstellen.
     */
    public function storeRule(Request $request): JsonResponse
    {
        $this->authorize('create', AttributeMapping::class);

        $request->validate([
            'source_hierarchy_id' => 'required|uuid|exists:hierarchies,id',
            'target_hierarchy_id' => 'required|uuid|exists:hierarchies,id',
            'name' => 'required|string|max:255',
            'condition_attribute_id' => 'required|uuid|exists:attributes,id',
            'condition_operator' => 'required|string|in:=,!=,>,<,>=,<=,in,not_in,contains,is_empty,is_not_empty',
            'condition_value' => 'nullable',
            'actions' => 'required|array|min:1',
            'actions.*.target_attribute_id' => 'required|uuid|exists:attributes,id',
            'actions.*.value' => 'required',
            'actions.*.value_type' => 'sometimes|string|in:static,source_attribute',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $rule = AttributeMappingRule::create($request->only([
            'source_hierarchy_id', 'target_hierarchy_id', 'name',
            'condition_attribute_id', 'condition_operator', 'condition_value',
            'actions', 'is_active', 'sort_order',
        ]));

        return response()->json(['data' => $rule->load('conditionAttribute')], 201);
    }

    /**
     * Bedingte Regel aktualisieren.
     */
    public function updateRule(Request $request, AttributeMappingRule $rule): JsonResponse
    {
        $this->authorize('create', AttributeMapping::class);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'condition_attribute_id' => 'sometimes|uuid|exists:attributes,id',
            'condition_operator' => 'sometimes|string|in:=,!=,>,<,>=,<=,in,not_in,contains,is_empty,is_not_empty',
            'condition_value' => 'nullable',
            'actions' => 'sometimes|array|min:1',
            'actions.*.target_attribute_id' => 'required_with:actions|uuid|exists:attributes,id',
            'actions.*.value' => 'required_with:actions',
            'actions.*.value_type' => 'sometimes|string|in:static,source_attribute',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $rule->update($request->only([
            'name', 'condition_attribute_id', 'condition_operator',
            'condition_value', 'actions', 'is_active', 'sort_order',
        ]));

        return response()->json(['data' => $rule->fresh('conditionAttribute')]);
    }

    /**
     * Bedingte Regel löschen.
     */
    public function destroyRule(AttributeMappingRule $rule): JsonResponse
    {
        $this->authorize('delete', AttributeMapping::class);

        $rule->delete();

        return response()->json(null, 204);
    }

    // ─── Sync ────────────────────────────────────────────────

    /**
     * Manueller Sync: Einzelnes Produkt synchronisieren.
     *
     * POST /attribute-mappings/sync/product/{product}
     */
    public function syncProduct(Request $request, Product $product, AttributeMappingSyncService $syncService): JsonResponse
    {
        $this->authorize('create', AttributeMapping::class);

        $request->validate([
            'source_hierarchy_id' => 'nullable|uuid|exists:hierarchies,id',
            'target_hierarchy_id' => 'nullable|uuid|exists:hierarchies,id',
        ]);

        $stats = $syncService->syncProduct(
            $product,
            $request->input('source_hierarchy_id'),
            $request->input('target_hierarchy_id')
        );

        return response()->json([
            'message' => 'Sync abgeschlossen',
            'stats' => $stats,
        ]);
    }

    /**
     * Manueller Sync: Mehrere Produkte synchronisieren (async via Job).
     *
     * POST /attribute-mappings/sync/batch
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $this->authorize('create', AttributeMapping::class);

        $request->validate([
            'source_hierarchy_id' => 'required|uuid|exists:hierarchies,id',
            'target_hierarchy_id' => 'required|uuid|exists:hierarchies,id',
            'product_ids' => 'nullable|array|max:5000',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        SyncAttributeMappings::dispatch(
            productIds: $request->input('product_ids'),
            sourceHierarchyId: $request->input('source_hierarchy_id'),
            targetHierarchyId: $request->input('target_hierarchy_id'),
        );

        return response()->json([
            'message' => 'Sync-Job gestartet',
            'product_count' => $request->input('product_ids')
                ? count($request->input('product_ids'))
                : 'alle aktiven Produkte',
        ]);
    }

    /**
     * Bulk-Aktion: Klassifikation synchronisieren für Produktauswahl.
     *
     * POST /attribute-mappings/sync/bulk
     * Verwendet von Produktliste → Aktion → "Klassifikation synchronisieren"
     */
    public function syncBulk(Request $request, AttributeMappingSyncService $syncService): JsonResponse
    {
        $this->authorize('create', AttributeMapping::class);

        $request->validate([
            'product_ids' => 'required|array|min:1|max:500',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        $productIds = $request->input('product_ids');
        $totalStats = ['products' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];

        // Synchron für kleine Auswahlen (≤500), damit der User sofort Ergebnis sieht
        $products = Product::with([
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.unit',
        ])->whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            $stats = $syncService->syncProduct($product);
            $totalStats['products']++;
            $totalStats['created'] += $stats['created'];
            $totalStats['updated'] += $stats['updated'];
            $totalStats['skipped'] += $stats['skipped'];
        }

        return response()->json([
            'message' => 'Klassifikation synchronisiert',
            'stats' => $totalStats,
        ]);
    }
}
