<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeMappingRequest;
use App\Http\Requests\Api\V1\UpdateAttributeMappingRequest;
use App\Http\Resources\Api\V1\AttributeMappingResource;
use App\Models\AttributeMapping;
use App\Models\AttributeMappingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttributeMappingController extends Controller
{
    /**
     * Alle Mappings auflisten (optional gefiltert nach Hierarchie).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AttributeMapping::class);

        $query = AttributeMapping::with(['sourceAttribute', 'targetAttribute', 'outputHierarchy']);

        if ($request->filled('output_hierarchy_id')) {
            $query->where('output_hierarchy_id', $request->input('output_hierarchy_id'));
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
        $mapping->load(['sourceAttribute', 'targetAttribute', 'outputHierarchy']);

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

        $attributeMapping->load(['sourceAttribute', 'targetAttribute', 'outputHierarchy']);

        return new AttributeMappingResource($attributeMapping);
    }

    /**
     * Mapping aktualisieren.
     */
    public function update(UpdateAttributeMappingRequest $request, AttributeMapping $attributeMapping): AttributeMappingResource
    {
        $this->authorize('update', $attributeMapping);

        $attributeMapping->update($request->validated());

        return new AttributeMappingResource($attributeMapping->fresh(['sourceAttribute', 'targetAttribute', 'outputHierarchy']));
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
            'mappings' => 'required|array|min:1',
            'mappings.*.source_attribute_id' => 'required|uuid|exists:attributes,id',
            'mappings.*.target_attribute_id' => 'required|uuid|exists:attributes,id',
            'mappings.*.output_hierarchy_id' => 'required|uuid|exists:hierarchies,id',
            'mappings.*.transform_type' => 'sometimes|string|in:direct,unit_convert,value_map',
            'mappings.*.transform_config' => 'nullable|array',
        ]);

        $created = [];
        $updated = [];

        foreach ($request->input('mappings') as $data) {
            $mapping = AttributeMapping::updateOrCreate(
                [
                    'source_attribute_id' => $data['source_attribute_id'],
                    'target_attribute_id' => $data['target_attribute_id'],
                    'output_hierarchy_id' => $data['output_hierarchy_id'],
                ],
                [
                    'transform_type' => $data['transform_type'] ?? 'direct',
                    'transform_config' => $data['transform_config'] ?? null,
                ]
            );

            if ($mapping->wasRecentlyCreated) {
                $created[] = $mapping->id;
            } else {
                $updated[] = $mapping->id;
            }
        }

        return response()->json([
            'created' => count($created),
            'updated' => count($updated),
        ]);
    }

    // ─── Bedingte Regeln ─────────────────────────────────────

    /**
     * Alle Regeln für eine Hierarchie auflisten.
     */
    public function rules(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttributeMapping::class);

        $query = AttributeMappingRule::with('conditionAttribute');

        if ($request->filled('output_hierarchy_id')) {
            $query->where('output_hierarchy_id', $request->input('output_hierarchy_id'));
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
            'output_hierarchy_id' => 'required|uuid|exists:hierarchies,id',
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
            'output_hierarchy_id', 'name', 'condition_attribute_id',
            'condition_operator', 'condition_value', 'actions',
            'is_active', 'sort_order',
        ]));

        return response()->json(['data' => $rule->load('conditionAttribute')], 201);
    }

    /**
     * Bedingte Regel aktualisieren.
     */
    public function updateRule(Request $request, AttributeMappingRule $rule): JsonResponse
    {
        $this->authorize('update', AttributeMapping::first() ?? new AttributeMapping());

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
        $this->authorize('delete', AttributeMapping::first() ?? new AttributeMapping());

        $rule->delete();

        return response()->json(null, 204);
    }
}
