<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreAttributeRequest;
use App\Http\Requests\Api\V1\UpdateAttributeRequest;
use App\Http\Resources\Api\V1\AttributeResource;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Attribute;
use App\Models\HierarchyNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttributeController extends Controller
{
    use ChecksDeletionConstraints;
    private const ALLOWED_INCLUDES = [
        'attributeType', 'unitGroup', 'defaultUnit', 'valueList',
        'children', 'parent', 'comparisonOperatorGroup', 'attributeViews',
        'dictionaryEntries',
    ];

    private const ALLOWED_FILTERS = [
        'status', 'data_type', 'attribute_type_id', 'is_translatable',
        'is_searchable', 'is_mandatory', 'is_inheritable', 'is_variant_attribute',
        'is_internal', 'is_primary', 'source_system',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Attribute::class);

        $query = Attribute::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(self::ALLOWED_FILTERS)
        ));

        $this->applyHierarchyNodeFilter($query, $request);

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

        $attribute = Attribute::create($validated);

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

        $attribute->update($validated);

        return new AttributeResource($attribute->fresh());
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

            return Attribute::create($data);
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
     * POST /attributes/all-ids — return all attribute IDs matching current filters.
     */
    public function allIds(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attribute::class);

        $query = Attribute::query();

        $this->applyFilters($query, array_intersect_key(
            $request->input('filter', []),
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
     * POST /attributes/bulk-delete — delete multiple attributes at once (Admin only).
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== 'Admin') {
            return response()->json(['message' => 'Nur Administratoren können Attribute in Massen löschen.'], 403);
        }

        $request->validate([
            'attribute_ids' => 'required|array|min:1|max:5000',
            'attribute_ids.*' => 'uuid',
        ]);

        $ids = $request->input('attribute_ids');

        $count = DB::transaction(function () use ($ids) {
            // Delete related data first
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

        Log::info('Bulk delete attributes', ['count' => $count, 'user_id' => $user->id]);

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
            'fields.attribute_type_id' => 'nullable|uuid|exists:attribute_types,id',
            'fields.status' => 'in:active,inactive',
        ]);

        $fields = array_intersect_key(
            $request->input('fields'),
            array_flip(self::BULK_ALLOWED_FIELDS)
        );

        if (empty($fields)) {
            return response()->json(['message' => 'No valid fields provided.'], 422);
        }

        $count = Attribute::whereIn('id', $request->input('ids'))->update($fields);

        return response()->json([
            'message' => "{$count} Attribute aktualisiert.",
            'updated' => $count,
        ]);
    }
}
