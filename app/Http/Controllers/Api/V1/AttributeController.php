<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeRequest;
use App\Http\Requests\Api\V1\UpdateAttributeRequest;
use App\Http\Resources\Api\V1\AttributeResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Attribute;
use App\Models\AttributeMetadataDefinition;
use App\Models\HierarchyNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Models\AttributeViewAssignment;
use App\Services\Attributes\AttributeMetadataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttributeController extends Controller
{
    use ChecksDeletionConstraints;
    private const ALLOWED_INCLUDES = [
        'attributeType', 'unitGroup', 'defaultUnit', 'valueList', 'formattingRule',
        'children', 'parent', 'comparisonOperatorGroup', 'attributeViews',
        'dictionaryEntries', 'metadataValues',
    ];

    public function __construct(
        private readonly AttributeMetadataService $metadataService,
    ) {
    }

    private const ALLOWED_FILTERS = [
        'status', 'data_type', 'attribute_type_id', 'is_translatable',
        'is_searchable', 'is_mandatory', 'is_inheritable', 'is_variant_attribute',
        'is_internal', 'is_primary', 'source_system',
    ];

    // Felder, die per Präfix-Suche (LIKE 'wert%') statt Exakt-Match gefiltert werden,
    // z.B. für das Quick-Lookup im Attribute-Menü.
    private const ALLOWED_PREFIX_FILTERS = ['technical_name', 'name_de'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Attribute::class);

        $query = Attribute::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $rawFilters = $request->query('filter', []);

        $this->applyPrefixFilters($query, $rawFilters, self::ALLOWED_PREFIX_FILTERS);

        $this->applyFilters($query, array_intersect_key(
            $rawFilters,
            array_flip(self::ALLOWED_FILTERS)
        ));

        $this->applyMetadataFilters($query, $rawFilters);
        $this->applyHierarchyNodeFilter($query, $request);
        $this->applyAttributeViewFilter($query, $request);

        $this->applySearch($query, $request, ['name_de', 'name_en', 'technical_name']);
        $this->applySorting($query, $request, 'position', 'asc');

        return AttributeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreAttributeRequest $request): JsonResponse
    {
        $this->authorize('create', Attribute::class);

        $validated = $request->validated();

        // Composite-Nesting: max. Tiefe 2
        if (!empty($validated['parent_attribute_id'])) {
            $parent = Attribute::find($validated['parent_attribute_id']);
            if ($parent && $parent->parent_attribute_id !== null) {
                abort(422, 'Maximale Composite-Tiefe erreicht: Ein Kind-Composite darf nicht selbst Kind eines anderen Composites sein.');
            }
        }

        $metadata = $this->extractMetadata($validated);

        $attribute = DB::transaction(function () use ($validated, $metadata) {
            $attribute = Attribute::create($validated);

            if ($metadata !== null) {
                $this->metadataService->sync($attribute, $metadata);
            }

            return $attribute;
        });

        return (new AttributeResource($attribute))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Attribute $attribute): AttributeResource
    {
        $this->authorize('view', $attribute);

        $attribute->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new AttributeResource($attribute);
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): AttributeResource
    {
        $this->authorize('update', $attribute);

        // Composite-Nesting: max. Tiefe 2 (Root-Composite → Kind-Composite → einfach)
        $validated = $request->validated();
        if (isset($validated['parent_attribute_id']) && $validated['parent_attribute_id'] !== null) {
            $parent = Attribute::find($validated['parent_attribute_id']);
            if ($parent && $parent->parent_attribute_id !== null) {
                abort(422, 'Maximale Composite-Tiefe erreicht: Ein Kind-Composite darf nicht selbst Kind eines anderen Composites sein.');
            }
            // Wenn dieses Attribut ein Composite ist und einem Composite zugewiesen wird,
            // darf es selbst keine Kind-Composites haben (sonst Tiefe > 2)
            if ($attribute->data_type === 'Composite') {
                $hasCompositeChildren = Attribute::where('parent_attribute_id', $attribute->id)
                    ->where('data_type', 'Composite')
                    ->exists();
                if ($hasCompositeChildren) {
                    abort(422, 'Dieses Composite hat bereits Kind-Composites und kann nicht als Kind eines anderen Composites verwendet werden.');
                }
            }
        }

        $metadata = $this->extractMetadata($validated);

        DB::transaction(function () use ($attribute, $validated, $metadata) {
            $attribute->update($validated);

            if ($metadata !== null) {
                $this->metadataService->sync($attribute, $metadata);
            }
        });

        return new AttributeResource($attribute->fresh());
    }

    /**
     * Trennt die Metadaten-Map aus den validierten Daten heraus.
     *
     * Liefert `null`, wenn der Payload gar keinen `metadata`-Key enthält — dann
     * bleiben die gespeicherten Werte unangetastet. Das ist wichtig, weil
     * `PUT /attributes/{id}` auch mit Teil-Payloads aufgerufen wird
     * (z.B. nur `parent_attribute_id` beim Composite-Handling).
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>|null
     */
    private function extractMetadata(array &$validated): ?array
    {
        if (!array_key_exists('metadata', $validated)) {
            return null;
        }

        $metadata = $validated['metadata'];
        unset($validated['metadata']);

        return is_array($metadata) ? $metadata : [];
    }

    public function copy(Attribute $attribute): JsonResponse
    {
        $this->authorize('create', Attribute::class);

        $copy = DB::transaction(function () use ($attribute) {
            $baseName = $attribute->technical_name . '_copy';
            $suffix = '';
            $counter = 1;

            while (Attribute::where('technical_name', $baseName . $suffix)->exists()) {
                $suffix = '_' . $counter;
                $counter++;
            }

            $data = $attribute->replicate([
                'id', 'created_at', 'updated_at',
            ])->toArray();

            $data['technical_name'] = $baseName . $suffix;
            if ($data['name_de']) {
                $data['name_de'] .= ' (Kopie)';
            }
            if ($data['name_en']) {
                $data['name_en'] .= ' (Copy)';
            }

            $copy = Attribute::create($data);

            // Governance-Metadaten gehören fachlich zur Kopie dazu;
            // replicate() erfasst nur Spalten, keine Relationen.
            $this->metadataService->copyValues($attribute, $copy);

            return $copy;
        });

        return (new AttributeResource($copy))
            ->response()
            ->setStatusCode(201);
    }

    public function dependencies(Attribute $attribute): JsonResponse
    {
        $this->authorize('view', $attribute);

        return $this->dependenciesResponse($attribute);
    }

    public function destroy(Request $request, Attribute $attribute): JsonResponse
    {
        $this->authorize('delete', $attribute);

        return $this->destroyWithConstraintCheck($request, $attribute);
    }

    /** Präfix für Metadaten-Filter, z.B. filter[meta:datenherkunft]=ERP */
    private const METADATA_FILTER_PREFIX = 'meta:';

    /** Sonderwerte für Data-Quality-Auswertungen. */
    private const METADATA_FILTER_EMPTY = '__none__';
    private const METADATA_FILTER_ANY = '__any__';

    /**
     * Filtert nach Metadatenwerten (Data Quality & Ownership).
     *
     * `filter[meta:<technical_name>]=<Wert>` — exakter Wert.
     * `__none__` liefert Attribute ohne Wert für diese Definition (Lückenanalyse),
     * `__any__` alle mit irgendeinem Wert.
     *
     * Unbekannte technische Namen werden still ignoriert; ein veralteter
     * Filter im Frontend soll die Liste nicht mit einem Fehler blockieren.
     *
     * @param array<string, mixed> $rawFilters
     */
    private function applyMetadataFilters($query, array $rawFilters): void
    {
        $wanted = [];
        foreach ($rawFilters as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, self::METADATA_FILTER_PREFIX)) {
                continue;
            }

            $technicalName = substr($key, strlen(self::METADATA_FILTER_PREFIX));
            if ($technicalName === '' || $value === '' || $value === null || is_array($value)) {
                continue;
            }

            $wanted[$technicalName] = (string) $value;
        }

        if ($wanted === []) {
            return;
        }

        $definitions = AttributeMetadataDefinition::whereIn('technical_name', array_keys($wanted))
            ->get()
            ->keyBy('technical_name');

        foreach ($wanted as $technicalName => $value) {
            $definition = $definitions->get($technicalName);
            if (!$definition instanceof AttributeMetadataDefinition) {
                continue;
            }

            if ($value === self::METADATA_FILTER_EMPTY) {
                $query->whereDoesntHave(
                    'metadataValues',
                    fn ($q) => $q->where('definition_id', $definition->id)
                );

                continue;
            }

            if ($value === self::METADATA_FILTER_ANY) {
                $query->whereHas(
                    'metadataValues',
                    fn ($q) => $q->where('definition_id', $definition->id)
                );

                continue;
            }

            $query->whereHas('metadataValues', function ($q) use ($definition, $value) {
                $q->where('definition_id', $definition->id);

                if ($definition->isMultiValue()) {
                    $q->whereJsonContains('value_json', $value);
                } else {
                    $q->where('value', $value);
                }
            });
        }
    }

    /**
     * Apply hierarchy node filter: find attributes assigned to a given node and all its descendants.
     */
    private function applyHierarchyNodeFilter($query, Request $request): void
    {
        $filters = $request->query('filter', []);
        $nodeId = $filters['hierarchy_node_id'] ?? null;

        if ($nodeId) {
            $node = HierarchyNode::find($nodeId);
            if ($node) {
                // Find all descendant node IDs (including the node itself)
                $nodeIds = HierarchyNode::where('hierarchy_id', $node->hierarchy_id)
                    ->where('path', 'LIKE', $node->path . '%')
                    ->pluck('id')
                    ->push($node->id)
                    ->unique();

                $query->whereHas('hierarchyNodeAssignments', function ($q) use ($nodeIds) {
                    $q->whereIn('hierarchy_node_id', $nodeIds);
                });
            }
        }
    }

    /**
     * filter[attribute_view]=technical_name1,technical_name2 -- z.B. genutzt von Collections,
     * um Kopfdaten-/Positions-Attribute auf die dem jeweiligen CollectionType zugeordnete(n)
     * Attributsicht(en) zu beschraenken (siehe CollectionType.default_attribute_groups/
     * default_item_attribute_groups).
     */
    private function applyAttributeViewFilter($query, Request $request): void
    {
        $viewNames = $request->query('filter', [])['attribute_view'] ?? null;

        if (!$viewNames) {
            return;
        }

        $names = is_array($viewNames) ? $viewNames : explode(',', $viewNames);

        $query->whereHas('attributeViews', function ($q) use ($names) {
            $q->whereIn('technical_name', $names);
        });
    }

    /**
     * POST /attributes/all-ids — return all attribute IDs matching current filters.
     */
    public function allIds(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attribute::class);

        $query = Attribute::query();

        $rawFilters = $request->input('filter', []);

        $this->applyPrefixFilters($query, $rawFilters, self::ALLOWED_PREFIX_FILTERS);

        $this->applyFilters($query, array_intersect_key(
            $rawFilters,
            array_flip(self::ALLOWED_FILTERS)
        ));

        // Apply hierarchy node filter from POST body
        if ($request->input('filter.hierarchy_node_id')) {
            $nodeId = $request->input('filter.hierarchy_node_id');
            $node = HierarchyNode::find($nodeId);
            if ($node) {
                $nodeIds = HierarchyNode::where('hierarchy_id', $node->hierarchy_id)
                    ->where('path', 'LIKE', $node->path . '%')
                    ->pluck('id')
                    ->push($node->id)
                    ->unique();

                $query->whereHas('hierarchyNodeAssignments', function ($q) use ($nodeIds) {
                    $q->whereIn('hierarchy_node_id', $nodeIds);
                });
            }
        }

        if ($request->input('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name_de', 'LIKE', "%{$search}%")
                  ->orWhere('name_en', 'LIKE', "%{$search}%")
                  ->orWhere('technical_name', 'LIKE', "%{$search}%");
            });
        }

        return response()->json(['ids' => $query->pluck('id')]);
    }

    /**
     * POST /attributes/bulk-delete — delete multiple attributes at once.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('bulkDelete', Attribute::class);

        $request->validate([
            'attribute_ids' => 'required|array|min:1|max:5000',
            'attribute_ids.*' => 'uuid',
        ]);

        $ids = $request->input('attribute_ids');

        $count = DB::transaction(function () use ($ids) {
            // Delete related data first
            DB::table('attribute_metadata_values')->whereIn('attribute_id', $ids)->delete();
            DB::table('product_attribute_values')->whereIn('attribute_id', $ids)->delete();
            DB::table('hierarchy_node_attribute_values')->whereIn('attribute_id', $ids)->delete();
            DB::table('media_attribute_values')->whereIn('attribute_id', $ids)->delete();
            DB::table('attribute_view_assignments')->whereIn('attribute_id', $ids)->delete();
            DB::table('variant_inheritance_rules')->whereIn('attribute_id', $ids)->delete();

            // Delete child attribute assignments in hierarchy nodes (including child assignments)
            DB::table('hierarchy_node_attribute_assignments')->whereIn('attribute_id', $ids)->delete();

            // Nullify parent references for child attributes
            Attribute::whereIn('parent_attribute_id', $ids)->update(['parent_attribute_id' => null]);

            // Delete the attributes themselves
            return Attribute::whereIn('id', $ids)->delete();
        });

        Log::info('Bulk delete attributes', ['count' => $count, 'user_id' => $request->user()?->id]);

        return response()->json([
            'message' => "{$count} Attribute gelöscht.",
            'deleted' => $count,
        ]);
    }

    /**
     * POST /attributes/{attribute}/migrate-language
     *
     * Migrate existing non-translatable values (language=NULL) to a specific language
     * when an attribute is switched to translatable.
     */
    public function migrateLanguage(Request $request, Attribute $attribute): JsonResponse
    {
        $this->authorize('update', $attribute);

        $request->validate([
            'target_language' => 'required|string|max:5',
        ]);

        $targetLanguage = $request->input('target_language');
        $batchSize = 5000;

        try {
            $counts = DB::transaction(function () use ($attribute, $targetLanguage, $batchSize) {
                $tables = [
                    'product_values' => 'product_attribute_values',
                    'hierarchy_node_values' => 'hierarchy_node_attribute_values',
                    'media_values' => 'media_attribute_values',
                ];

                $counts = [];
                foreach ($tables as $key => $table) {
                    $total = 0;
                    do {
                        $updated = DB::table($table)
                            ->where('attribute_id', $attribute->id)
                            ->whereNull('language')
                            ->limit($batchSize)
                            ->update(['language' => $targetLanguage]);
                        $total += $updated;
                    } while ($updated >= $batchSize);
                    $counts[$key] = $total;
                }

                return $counts;
            });

            $total = array_sum($counts);

            return response()->json([
                'message' => "{$total} Werte auf Sprache '{$targetLanguage}' migriert.",
                'migrated' => $total,
                'details' => $counts,
            ]);
        } catch (\Throwable $e) {
            Log::error('Attribute language migration failed', [
                'attribute_id' => $attribute->id,
                'target_language' => $targetLanguage,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Fehler bei der Migration: ' . $e->getMessage(),
            ], 500);
        }
    }

    private const BULK_ALLOWED_FIELDS = [
        'is_translatable', 'is_multipliable', 'is_searchable', 'is_mandatory',
        'is_unique', 'is_inheritable', 'is_variant_attribute', 'is_internal',
        'is_readonly', 'is_hidden', 'is_quick_search', 'is_primary', 'attribute_type_id', 'status',
    ];

    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('update', Attribute::class);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid|exists:attributes,id',
            'fields' => 'required|array|min:1',
            'fields.is_translatable' => 'boolean',
            'fields.is_multipliable' => 'boolean',
            'fields.is_searchable' => 'boolean',
            'fields.is_mandatory' => 'boolean',
            'fields.is_unique' => 'boolean',
            'fields.is_inheritable' => 'boolean',
            'fields.is_variant_attribute' => 'boolean',
            'fields.is_internal' => 'boolean',
            'fields.is_readonly' => 'boolean',
            'fields.is_hidden' => 'boolean',
            'fields.is_quick_search' => 'boolean',
            'fields.is_primary' => 'boolean',
            'fields.attribute_type_id' => 'nullable|uuid|exists:attribute_types,id',
            'fields.status' => 'in:active,inactive',
            'fields.metadata' => 'sometimes|array',
        ]);

        $fields = array_intersect_key(
            $request->input('fields'),
            array_flip(self::BULK_ALLOWED_FIELDS)
        );

        $metadata = $request->input('fields.metadata');
        $metadata = is_array($metadata) ? $metadata : [];

        if (empty($fields) && $metadata === []) {
            return response()->json(['message' => 'No valid fields provided.'], 422);
        }

        if ($metadata !== []) {
            $this->validateBulkMetadata($metadata);
        }

        $ids = $request->input('ids');

        $count = DB::transaction(function () use ($ids, $fields, $metadata) {
            $count = empty($fields)
                ? count($ids)
                : Attribute::whereIn('id', $ids)->update($fields);

            if ($metadata !== []) {
                // Metadatenwerte hängen an eigenen Zeilen und lassen sich nicht per
                // Massen-UPDATE setzen. Gechunkt, weil bis zu 5000 IDs erlaubt sind.
                Attribute::whereIn('id', $ids)
                    ->select('id')
                    ->chunkById(500, function ($attributes) use ($metadata) {
                        foreach ($attributes as $attribute) {
                            $this->metadataService->sync($attribute, $metadata);
                        }
                    });
            }

            return $count;
        });

        return response()->json([
            'message' => "{$count} Attribute aktualisiert.",
            'updated' => $count,
        ]);
    }

    /**
     * Prüft die Metadaten einer Massenbearbeitung und wirft bei Fehlern 422.
     *
     * @param array<string, mixed> $metadata
     */
    private function validateBulkMetadata(array $metadata): void
    {
        $validator = ValidatorFacade::make([], []);
        $this->metadataService->validate($metadata, $validator, 'fields.metadata');

        if ($validator->errors()->isNotEmpty()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    public function bulkAssignViews(Request $request): JsonResponse
    {
        $this->authorize('update', Attribute::class);

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid|exists:attributes,id',
            'mode' => 'required|in:add,remove,set',
            'attribute_view_ids' => 'required|array|min:1',
            'attribute_view_ids.*' => 'uuid|exists:attribute_views,id',
        ]);

        $attributeIds = $request->input('ids');
        $viewIds = $request->input('attribute_view_ids');
        $mode = $request->input('mode');

        DB::beginTransaction();
        try {
            if ($mode === 'remove') {
                AttributeViewAssignment::whereIn('attribute_id', $attributeIds)
                    ->whereIn('attribute_view_id', $viewIds)
                    ->delete();
            } elseif ($mode === 'set') {
                AttributeViewAssignment::whereIn('attribute_id', $attributeIds)->delete();
                $this->insertViewAssignments($attributeIds, $viewIds);
            } else {
                $this->insertViewAssignments($attributeIds, $viewIds, skipExisting: true);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([
            'message' => 'Attributsichten für ' . count($attributeIds) . ' Attribute aktualisiert.',
        ]);
    }

    private function insertViewAssignments(array $attributeIds, array $viewIds, bool $skipExisting = false): void
    {
        $existing = [];
        if ($skipExisting) {
            $existing = AttributeViewAssignment::whereIn('attribute_id', $attributeIds)
                ->whereIn('attribute_view_id', $viewIds)
                ->get(['attribute_id', 'attribute_view_id'])
                ->map(fn ($a) => $a->attribute_id . '|' . $a->attribute_view_id)
                ->toArray();
        }

        $now = now();
        $rows = [];
        foreach ($attributeIds as $attrId) {
            foreach ($viewIds as $viewId) {
                if ($skipExisting && in_array($attrId . '|' . $viewId, $existing)) {
                    continue;
                }
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'attribute_id' => $attrId,
                    'attribute_view_id' => $viewId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('attribute_view_assignments')->insert($chunk);
        }
    }
}
