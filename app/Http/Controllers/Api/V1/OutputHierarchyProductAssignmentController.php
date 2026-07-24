<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreOutputHierarchyProductAssignmentRequest;
use App\Http\Resources\Api\V1\OutputHierarchyProductAssignmentResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Traits\ChecksTabPermissions;
use App\Models\Attribute;
use App\Models\HierarchyAttributeAssignment;
use App\Models\HierarchyNode;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\OutputHierarchyProductAttributeValue;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OutputHierarchyProductAssignmentController extends Controller
{
    use ChecksTabPermissions;
    /**
     * GET /hierarchy-nodes/{hierarchy_node}/output-products
     */
    public function index(Request $request, HierarchyNode $hierarchyNode): AnonymousResourceCollection
    {
        $this->authorize('view', $hierarchyNode);

        $query = $hierarchyNode->outputProductAssignments()
            ->with(['product'])
            ->orderBy('sort_order', 'asc');

        return OutputHierarchyProductAssignmentResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * GET /hierarchy-nodes/{hierarchy_node}/products
     *
     * Produkte eines Knotens — Master-Klassifizierung (master_hierarchy_node_id),
     * Output-Zuordnung (OutputHierarchyProductAssignment) oder beide.
     * Query: ?type=master|output|both (Standard: both)
     */
    public function products(Request $request, HierarchyNode $hierarchyNode): AnonymousResourceCollection
    {
        $this->authorize('view', $hierarchyNode);

        $type = $request->query('type', 'both');

        $query = Product::query();

        if ($type === 'master') {
            $query->where('master_hierarchy_node_id', $hierarchyNode->id);
        } elseif ($type === 'output') {
            $outputIds = OutputHierarchyProductAssignment::where('hierarchy_node_id', $hierarchyNode->id)->pluck('product_id');
            $query->whereIn('id', $outputIds);
        } else {
            $outputIds = OutputHierarchyProductAssignment::where('hierarchy_node_id', $hierarchyNode->id)->pluck('product_id');
            $query->where(function ($q) use ($hierarchyNode, $outputIds) {
                $q->where('master_hierarchy_node_id', $hierarchyNode->id)
                    ->orWhereIn('id', $outputIds);
            });
        }

        return ProductResource::collection(
            $query->orderBy('sku')->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /hierarchy-nodes/{hierarchy_node}/output-products
     */
    public function store(StoreOutputHierarchyProductAssignmentRequest $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);
        $this->assertTabWriteAccess('output-hierarchies');

        $data = $request->validated();
        $data['hierarchy_node_id'] = $hierarchyNode->id;

        // Auto-assign sort_order if not provided
        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ($hierarchyNode->outputProductAssignments()->max('sort_order') ?? -1) + 1;
        }

        $assignment = OutputHierarchyProductAssignment::create($data);

        return (new OutputHierarchyProductAssignmentResource($assignment->load('product')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /output-hierarchy-product-assignments/{assignment}
     */
    public function destroy(OutputHierarchyProductAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment->hierarchyNode);
        $this->assertTabWriteAccess('output-hierarchies');

        $assignment->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /products/{product}/output-hierarchy-assignments
     */
    public function productAssignments(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = $product->outputHierarchyAssignments()
            ->with(['hierarchyNode.hierarchy'])
            ->orderBy('sort_order', 'asc');

        return OutputHierarchyProductAssignmentResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /hierarchy-nodes/{hierarchy_node}/master-products
     *
     * Weist ein Produkt diesem Master-Hierarchie-Knoten zu (setzt master_hierarchy_node_id).
     */
    public function assignMasterProduct(Request $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
        ]);

        $product = Product::findOrFail($request->input('product_id'));
        $product->update(['master_hierarchy_node_id' => $hierarchyNode->id]);

        return (new ProductResource($product->fresh()))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * DELETE /hierarchy-nodes/{hierarchy_node}/master-products/{product}
     *
     * Entfernt die Zuordnung eines Produkts von diesem Master-Knoten (setzt master_hierarchy_node_id = null).
     */
    public function removeMasterProduct(HierarchyNode $hierarchyNode, Product $product): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        if ($product->master_hierarchy_node_id === $hierarchyNode->id) {
            $product->update(['master_hierarchy_node_id' => null]);
        }

        return response()->json(null, 204);
    }

    /**
     * POST /hierarchy-nodes/{hierarchy_node}/output-products/bulk-assign
     *
     * Bulk-assign multiple products to an output hierarchy node.
     */
    public function bulkAssign(Request $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);
        $this->assertTabWriteAccess('output-hierarchies');

        $request->validate([
            'product_ids' => 'required|array|min:1|max:10000',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        $productIds = $request->input('product_ids');

        // Get already assigned product IDs to skip
        $existingIds = $hierarchyNode->outputProductAssignments()
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->toArray();

        $newIds = array_diff($productIds, $existingIds);
        $maxSort = $hierarchyNode->outputProductAssignments()->max('sort_order') ?? -1;
        $created = 0;

        foreach ($newIds as $productId) {
            $maxSort++;
            OutputHierarchyProductAssignment::create([
                'hierarchy_node_id' => $hierarchyNode->id,
                'product_id' => $productId,
                'sort_order' => $maxSort,
            ]);
            $created++;
        }

        return response()->json([
            'message' => "{$created} Produkt(e) zugeordnet.",
            'assigned' => $created,
            'skipped' => count($existingIds),
        ]);
    }

    /**
     * POST /products/{product}/output-hierarchy-assignments/bulk-assign
     *
     * Weist ein Produkt mehreren Ausgabehierarchie-Knoten gleichzeitig zu
     * (Mehrfachauswahl im Knoten-Baum, z.B. "alle Länder Europas außer Island").
     * Bereits zugeordnete oder nicht-berechtigte Knoten werden übersprungen statt
     * die gesamte Anfrage abzulehnen — Autorisierung erfolgt wie bei store() pro
     * Knoten (respektiert Instanz-Restriktionen einzelner Knoten).
     */
    public function bulkAssignNodes(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);
        $this->assertTabWriteAccess('output-hierarchies');

        $request->validate([
            'node_ids' => 'required|array|min:1|max:1000',
            'node_ids.*' => 'uuid|exists:hierarchy_nodes,id',
        ]);

        $nodeIds = array_unique($request->input('node_ids'));
        $nodes = HierarchyNode::whereIn('id', $nodeIds)->get()->keyBy('id');

        $existingNodeIds = OutputHierarchyProductAssignment::where('product_id', $product->id)
            ->whereIn('hierarchy_node_id', $nodeIds)
            ->pluck('hierarchy_node_id')
            ->toArray();

        $created = 0;
        $unauthorized = 0;

        DB::transaction(function () use ($nodeIds, $nodes, $existingNodeIds, $product, &$created, &$unauthorized) {
            foreach ($nodeIds as $nodeId) {
                if (in_array($nodeId, $existingNodeIds, true)) {
                    continue;
                }

                $node = $nodes->get($nodeId);
                if (!$node || !\Illuminate\Support\Facades\Gate::allows('update', $node)) {
                    $unauthorized++;
                    continue;
                }

                $maxSort = OutputHierarchyProductAssignment::where('hierarchy_node_id', $nodeId)->max('sort_order') ?? -1;
                OutputHierarchyProductAssignment::create([
                    'hierarchy_node_id' => $nodeId,
                    'product_id' => $product->id,
                    'sort_order' => $maxSort + 1,
                ]);
                $created++;
            }
        });

        return response()->json([
            'message' => "{$created} Zuordnung(en) erstellt.",
            'assigned' => $created,
            'skipped_existing' => count($existingNodeIds),
            'skipped_unauthorized' => $unauthorized,
        ]);
    }

    /**
     * POST /hierarchy-nodes/{hierarchy_node}/master-products/bulk-assign
     *
     * Bulk-assign multiple products to a master hierarchy node.
     */
    public function bulkAssignMaster(Request $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);

        $request->validate([
            'product_ids' => 'required|array|min:1|max:10000',
            'product_ids.*' => 'uuid|exists:products,id',
        ]);

        $count = Product::whereIn('id', $request->input('product_ids'))
            ->update(['master_hierarchy_node_id' => $hierarchyNode->id]);

        return response()->json([
            'message' => "{$count} Produkt(e) zugeordnet.",
            'assigned' => $count,
        ]);
    }

    /**
     * PUT /hierarchy-nodes/{hierarchy_node}/output-products/sort
     */
    public function bulkSort(Request $request, HierarchyNode $hierarchyNode): JsonResponse
    {
        $this->authorize('update', $hierarchyNode);
        $this->assertTabWriteAccess('output-hierarchies');

        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|uuid',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->input('items') as $item) {
            OutputHierarchyProductAssignment::where('id', $item['id'])
                ->where('hierarchy_node_id', $hierarchyNode->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Sort order updated']);
    }

    /**
     * GET /output-hierarchy-product-assignments/{assignment}/relationship-attributes
     *
     * Liefert Beziehungs-Attribute (scope=relationship|both) der Hierarchie
     * inkl. aktueller Werte für diese Zuordnung.
     */
    public function relationshipAttributes(Request $request, OutputHierarchyProductAssignment $assignment): JsonResponse
    {
        $this->authorize('view', $assignment->hierarchyNode);

        $language = $request->query('language', 'de');
        $hierarchyId = $assignment->hierarchyNode->hierarchy_id;

        // Beziehungs-Attribute der Hierarchie laden (scope = relationship oder both)
        $attrs = HierarchyAttributeAssignment::where('hierarchy_id', $hierarchyId)
            ->whereIn('scope', ['relationship', 'both'])
            ->with('attribute')
            ->orderBy('sort_order')
            ->get();

        if ($attrs->isEmpty()) {
            return response()->json(['data' => []]);
        }

        // Aktuelle Werte für diese Zuordnung laden
        $valuesRaw = $assignment->attributeValues()
            ->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            })
            ->get();
        $valuesByAttr = $valuesRaw->groupBy('attribute_id');

        $result = $attrs->map(function (HierarchyAttributeAssignment $haa) use ($valuesByAttr) {
            $attr = $haa->attribute;
            $attrValues = $valuesByAttr->get($attr->id, collect());
            $firstValue = $attrValues->first();

            return [
                'assignment_attribute_id' => $haa->id,
                'attribute_id' => $attr->id,
                'attribute_technical_name' => $attr->technical_name,
                'attribute_name_de' => $attr->name_de,
                'attribute_name_en' => $attr->name_en,
                'data_type' => $attr->data_type,
                'value_list_id' => $attr->value_list_id,
                'is_translatable' => (bool) $attr->is_translatable,
                'is_multipliable' => (bool) $attr->is_multipliable,
                'max_multiplied' => $attr->max_multiplied,
                'value' => $firstValue
                    ? ($firstValue->value_string ?? $firstValue->value_number ?? $firstValue->value_date ?? $firstValue->value_flag ?? $firstValue->value_selection_id)
                    : null,
                'value_selection_id' => $firstValue?->value_selection_id,
                'unit_id' => $firstValue?->unit_id,
                'multiplied_values' => $attr->is_multipliable
                    ? $attrValues->map(fn ($v) => [
                        'value' => $v->value_string ?? $v->value_number ?? $v->value_date ?? $v->value_flag ?? $v->value_selection_id,
                        'value_selection_id' => $v->value_selection_id,
                        'unit_id' => $v->unit_id,
                        'multiplied_index' => $v->multiplied_index,
                    ])->values()->all()
                    : null,
                'scope' => $haa->scope,
                'sort_order' => $haa->sort_order,
            ];
        });

        return response()->json(['data' => $result->values()]);
    }

    /**
     * PUT /output-hierarchy-product-assignments/{assignment}/relationship-attributes
     *
     * Speichert Beziehungs-Attributwerte für eine Produkt↔Knoten-Zuordnung.
     */
    public function updateRelationshipAttributes(Request $request, OutputHierarchyProductAssignment $assignment): JsonResponse
    {
        $this->authorize('update', $assignment->hierarchyNode);
        $this->assertTabWriteAccess('output-hierarchies');

        $request->validate([
            'values' => 'required|array',
            'values.*.attribute_id' => 'required|uuid|exists:attributes,id',
            'values.*.value' => 'nullable',
            'values.*.value_selection_id' => 'sometimes|nullable|uuid',
            'values.*.unit_id' => 'sometimes|nullable|uuid',
            'values.*.language' => 'sometimes|nullable|string|max:5',
            'values.*.multiplied_index' => 'sometimes|integer|min:0',
        ]);

        $values = $request->input('values');
        $hierarchyId = $assignment->hierarchyNode->hierarchy_id;

        // Sicherstellen, dass nur Beziehungs-Attribute gespeichert werden
        $allowedAttrIds = HierarchyAttributeAssignment::where('hierarchy_id', $hierarchyId)
            ->whereIn('scope', ['relationship', 'both'])
            ->pluck('attribute_id')
            ->toArray();

        DB::transaction(function () use ($assignment, $values, $allowedAttrIds) {
            foreach ($values as $entry) {
                $attribute = Attribute::findOrFail($entry['attribute_id']);

                if (!in_array($attribute->id, $allowedAttrIds)) {
                    continue;
                }

                if ($attribute->data_type === 'Composite' || $attribute->is_readonly) {
                    continue;
                }

                $language = $entry['language'] ?? null;
                $multipliedIndex = $entry['multiplied_index'] ?? 0;

                if ($attribute->is_translatable && $language === null) {
                    abort(422, "Attribut '{$attribute->technical_name}' ist übersetzbar — 'language' ist erforderlich.");
                }

                $valueData = $this->resolveValueColumns($attribute, $entry);

                OutputHierarchyProductAttributeValue::updateOrCreate(
                    [
                        'assignment_id' => $assignment->id,
                        'attribute_id' => $attribute->id,
                        'language' => $language,
                        'multiplied_index' => $multipliedIndex,
                    ],
                    array_merge($valueData, [
                        'unit_id' => $entry['unit_id'] ?? null,
                    ])
                );
            }
        });

        return response()->json(['message' => 'Beziehungs-Attributwerte gespeichert.', 'count' => count($values)]);
    }

    /**
     * Ermittelt die korrekten Wert-Spalten basierend auf dem Attribut-Datentyp.
     */
    private function resolveValueColumns(Attribute $attribute, array $entry): array
    {
        $columns = [
            'value_string' => null,
            'value_number' => null,
            'value_date' => null,
            'value_flag' => null,
            'value_selection_id' => null,
        ];

        $value = $entry['value'] ?? null;

        return match ($attribute->data_type) {
            'String' => array_merge($columns, ['value_string' => $value !== null ? (string) $value : null]),
            'Number', 'Float' => array_merge($columns, ['value_number' => $value !== null ? (float) $value : null]),
            'Date' => array_merge($columns, ['value_date' => $value]),
            'Flag' => array_merge($columns, ['value_flag' => $value !== null ? (bool) $value : null]),
            'Selection', 'Dictionary' => array_merge($columns, [
                'value_string' => $value,
                'value_selection_id' => $entry['value_selection_id'] ?? null,
            ]),
            'RichText', 'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink' => array_merge($columns, ['value_string' => $value !== null ? (string) $value : null]),
            'Composite' => $columns,
            default => array_merge($columns, ['value_string' => $value !== null ? (string) $value : null]),
        };
    }
}
