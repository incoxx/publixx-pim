<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateAttributeValuesRequest;
use App\Http\Requests\Api\V1\BulkUpdateOutputHierarchyAttributeValuesRequest;
use App\Http\Resources\Api\V1\ProductAttributeValueResource;
use App\Models\Attribute;
use App\Models\ComparisonOperatorGroup;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\UnitGroup;
use App\Models\AttributeMapping;
use App\Services\Export\AttributeMappingSyncService;
use App\Services\Formatting\AttributeFormattingService;
use App\Services\Inheritance\AttributeValueResolver;
use App\Services\Inheritance\HierarchyInheritanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProductAttributeValueController extends Controller
{
    /**
     * GET /products/{product}/attribute-values
     *
     * Query params: ?view=eshop_view, ?lang=de,en
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $languages = $this->getRequestedLanguages($request);
        $viewFilter = $request->query('view');

        $query = $product->attributeValues()
            ->with(['attribute', 'attribute.unitGroup', 'attribute.formattingRule', 'unit', 'valueListEntry'])
            ->whereNull('output_hierarchy_id')
            ->where(function ($q) use ($languages) {
                $q->whereNull('language')
                    ->orWhereIn('language', $languages);
            })
            ->orderBy('attribute_id')
            ->orderBy('multiplied_index');

        // If view is specified, filter to only attributes in that view
        if ($viewFilter) {
            $query->whereHas('attribute.viewAssignments', function ($q) use ($viewFilter) {
                $q->whereHas('attributeView', function ($sq) use ($viewFilter) {
                    $sq->where('technical_name', $viewFilter);
                });
            });
        }

        return ProductAttributeValueResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * GET /products/{product}/resolved-attributes
     *
     * Returns the effective attribute list for a product based on hierarchy inheritance.
     * Includes attribute metadata, resolved values, and inheritance info.
     */
    public function resolved(Request $request, Product $product, HierarchyInheritanceService $hierarchyService): JsonResponse
    {
        $this->authorize('view', $product);

        $language = $this->getPrimaryLanguage($request);

        // Allow overriding hierarchy node via query param (preview before saving)
        $overrideNodeId = $request->query('hierarchy_node_id');

        if ($overrideNodeId) {
            $node = HierarchyNode::find($overrideNodeId);
            $effectiveAttributes = $node
                ? $hierarchyService->getEffectiveAttributes($node)
                : collect();
        } else {
            $effectiveAttributes = $hierarchyService->getProductAttributes($product);
        }

        // Load existing master attribute values for this product (exclude channel-specific)
        // Use groupBy to support multipliable attributes with multiple values
        $allExistingValues = $product->attributeValues()
            ->with(['attribute', 'unit'])
            ->whereNull('output_hierarchy_id')
            ->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            })
            ->orderBy('multiplied_index')
            ->get();

        $existingValues = $allExistingValues->keyBy('attribute_id');
        $multipliedValues = $allExistingValues->groupBy('attribute_id');

        // Ensure child attributes of any Composite in the effective list are included,
        // even when they are not explicitly assigned to the hierarchy node.
        // Supports 2-level nesting: Root-Composite → Kind-Composite → einfache Attribute.
        $compositeIds = $effectiveAttributes
            ->filter(fn ($a) => $a->data_type === 'Composite')
            ->pluck('attribute_id')
            ->all();

        if (!empty($compositeIds)) {
            $existingAttrIds = $effectiveAttributes->pluck('attribute_id')->all();
            $missingChildren = Attribute::whereIn('parent_attribute_id', $compositeIds)
                ->whereNotIn('id', $existingAttrIds)
                ->get();

            // Sammle IDs von Kind-Composites für Ebene 2
            $childCompositeIds = [];

            foreach ($missingChildren as $child) {
                $parent = $effectiveAttributes->firstWhere('attribute_id', $child->parent_attribute_id);
                $effectiveAttributes->push((object) [
                    'attribute_id' => $child->id,
                    'attribute_technical_name' => $child->technical_name,
                    'attribute_name_de' => $child->name_de,
                    'attribute_name_en' => $child->name_en,
                    'data_type' => $child->data_type,
                    'value_list_id' => $child->value_list_id,
                    'is_translatable' => $child->is_translatable,
                    'is_multipliable' => $child->is_multipliable ?? false,
                    'max_multiplied' => $child->max_multiplied,
                    'is_mandatory' => $child->is_mandatory,
                    'is_variant_attribute' => $child->is_variant_attribute ?? false,
                    'is_primary' => $child->is_primary ?? false,
                    'parent_attribute_id' => $child->parent_attribute_id,
                    'composite_format' => $child->composite_format,
                    'composite_expression' => $child->composite_expression,
                    'delimiter' => $child->delimiter ?? null,
                    'textarea_rows' => $child->textarea_rows ?? null,
                    'textarea_cols' => $child->textarea_cols ?? null,
                    'min_value' => $child->min_value ?? null,
                    'max_value' => $child->max_value ?? null,
                    'max_characters' => $child->max_characters ?? null,
                    'is_readonly' => $child->is_readonly ?? false,
                    'collection_name' => $parent->collection_name ?? null,
                    'collection_sort' => $parent->collection_sort ?? 0,
                    'attribute_sort' => $child->position ?? 999,
                    'access_product' => $parent->access_product ?? 'editable',
                    'access_variant' => $parent->access_variant ?? 'editable',
                    'attribute_view_name_de' => $parent->attribute_view_name_de ?? null,
                ]);

                if ($child->data_type === 'Composite') {
                    $childCompositeIds[] = $child->id;
                }

                // Also load existing values for these children (master context only)
                $childValues = $product->attributeValues()
                    ->where('attribute_id', $child->id)
                    ->whereNull('output_hierarchy_id')
                    ->where(function ($q) use ($language) {
                        $q->whereNull('language')->orWhere('language', $language);
                    })
                    ->get();
                if (!$multipliedValues->has($child->id)) {
                    $multipliedValues->put($child->id, collect());
                }
                foreach ($childValues as $cv) {
                    // existingValues: nur den ersten Wert (index 0) für das top-level value Feld
                    if (!$existingValues->has($child->id)) {
                        $existingValues->put($child->id, $cv);
                    }
                    $multipliedValues->get($child->id)->push($cv);
                }
            }

            // Ebene 2: Enkel-Attribute (Kinder von Kind-Composites) laden
            if (!empty($childCompositeIds)) {
                $grandchildren = Attribute::whereIn('parent_attribute_id', $childCompositeIds)
                    ->whereNotIn('id', $effectiveAttributes->pluck('attribute_id')->all())
                    ->get();

                foreach ($grandchildren as $gc) {
                    $gcParent = $effectiveAttributes->firstWhere('attribute_id', $gc->parent_attribute_id);
                    $effectiveAttributes->push((object) [
                        'attribute_id' => $gc->id,
                        'attribute_technical_name' => $gc->technical_name,
                        'attribute_name_de' => $gc->name_de,
                        'attribute_name_en' => $gc->name_en,
                        'data_type' => $gc->data_type,
                        'value_list_id' => $gc->value_list_id,
                        'is_translatable' => $gc->is_translatable,
                        'is_multipliable' => $gc->is_multipliable ?? false,
                        'max_multiplied' => $gc->max_multiplied,
                        'is_mandatory' => $gc->is_mandatory,
                        'is_variant_attribute' => $gc->is_variant_attribute ?? false,
                        'is_primary' => $gc->is_primary ?? false,
                        'parent_attribute_id' => $gc->parent_attribute_id,
                        'composite_format' => $gc->composite_format ?? null,
                        'composite_expression' => $gc->composite_expression ?? null,
                        'delimiter' => $gc->delimiter ?? null,
                        'textarea_rows' => $gc->textarea_rows ?? null,
                        'textarea_cols' => $gc->textarea_cols ?? null,
                        'min_value' => $gc->min_value ?? null,
                        'max_value' => $gc->max_value ?? null,
                        'max_characters' => $gc->max_characters ?? null,
                        'is_readonly' => $gc->is_readonly ?? false,
                        'collection_name' => $gcParent->collection_name ?? null,
                        'collection_sort' => $gcParent->collection_sort ?? 0,
                        'attribute_sort' => $gc->position ?? 999,
                        'access_product' => $gcParent->access_product ?? 'editable',
                        'access_variant' => $gcParent->access_variant ?? 'editable',
                        'attribute_view_name_de' => $gcParent->attribute_view_name_de ?? null,
                    ]);

                    $gcValues = $product->attributeValues()
                        ->where('attribute_id', $gc->id)
                        ->whereNull('output_hierarchy_id')
                        ->where(function ($q) use ($language) {
                            $q->whereNull('language')->orWhere('language', $language);
                        })
                        ->get();
                    if (!$multipliedValues->has($gc->id)) {
                        $multipliedValues->put($gc->id, collect());
                    }
                    foreach ($gcValues as $gv) {
                        if (!$existingValues->has($gc->id)) {
                            $existingValues->put($gc->id, $gv);
                        }
                        $multipliedValues->get($gc->id)->push($gv);
                    }
                }
            }
        }

        // Einheitengruppen mit Einheiten vorladen (für Number/Float-Attribute)
        $unitGroupIds = $effectiveAttributes
            ->pluck('unit_group_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $unitGroups = !empty($unitGroupIds)
            ? UnitGroup::with('units')->whereIn('id', $unitGroupIds)->get()->keyBy('id')
            : collect();

        // Vergleichsoperator-Gruppen mit Operatoren vorladen
        $compOpGroupIds = $effectiveAttributes
            ->pluck('comparison_operator_group_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $compOpGroups = !empty($compOpGroupIds)
            ? ComparisonOperatorGroup::with('operators')->whereIn('id', $compOpGroupIds)->get()->keyBy('id')
            : collect();

        // Formatierungsregeln für String-/Number-/Float-Attribute vorladen (Produkteditor-Vorschau)
        $formattingAttrIds = $effectiveAttributes
            ->filter(fn ($a) => in_array($a->data_type, ['String', 'Number', 'Float'], true))
            ->pluck('attribute_id')
            ->unique()
            ->all();
        $formattingAttributes = !empty($formattingAttrIds)
            ? Attribute::whereIn('id', $formattingAttrIds)->whereNotNull('formatting_rule_id')->with('formattingRule')->get()->keyBy('id')
            : collect();

        $result = $effectiveAttributes->map(function ($assignment) use ($existingValues, $multipliedValues, $unitGroups, $compOpGroups, $effectiveAttributes, $formattingAttributes) {
            $pav = $existingValues->get($assignment->attribute_id);
            $value = null;
            $source = 'none';

            if ($pav) {
                $value = $pav->value_string ?? $pav->value_number ?? $pav->value_date ?? $pav->value_flag ?? $pav->value_selection_id;
                // MultiSelection: JSON-Array zurück dekodieren
                if ($assignment->data_type === 'MultiSelection' && is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $value = $decoded;
                    }
                }
                $source = $pav->is_inherited ? 'hierarchy_inheritance' : 'own';
            }

            // Einheit: gespeicherte unit_id oder default_unit_id des Attributs
            $unitId = $pav?->unit_id ?? $assignment->default_unit_id ?? null;

            // Einheitengruppe mit Einheiten für Dropdown
            $unitGroupData = null;
            if (!empty($assignment->unit_group_id)) {
                $ug = $unitGroups->get($assignment->unit_group_id);
                if ($ug) {
                    $unitGroupData = [
                        'id' => $ug->id,
                        'name_de' => $ug->name_de,
                        'units' => $ug->units->map(fn ($u) => [
                            'id' => $u->id,
                            'abbreviation' => $u->abbreviation,
                            'name_de' => $u->name_de,
                            'is_base_unit' => (bool) $u->is_base_unit,
                        ])->values()->all(),
                    ];
                }
            }

            $result = [
                'attribute_id' => $assignment->attribute_id,
                'attribute_technical_name' => $assignment->attribute_technical_name,
                'attribute_name_de' => $assignment->attribute_name_de,
                'attribute_name_en' => $assignment->attribute_name_en,
                'data_type' => $assignment->data_type,
                'value_list_id' => $assignment->value_list_id ?? null,
                'is_translatable' => (bool) $assignment->is_translatable,
                'is_mandatory' => (bool) ($assignment->is_mandatory || !empty($assignment->is_required)),
                'is_variant_attribute' => (bool) ($assignment->is_variant_attribute ?? false),
                'is_multipliable' => (bool) ($assignment->is_multipliable ?? false),
                'is_primary' => (bool) ($assignment->is_primary ?? false),
                'max_multiplied' => $assignment->max_multiplied ?? null,
                'attribute_type_id' => $assignment->attribute_type_id ?? null,
                'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                'composite_format' => $assignment->composite_format ?? null,
                'composite_expression' => $assignment->composite_expression ?? null,
                'delimiter' => $assignment->delimiter ?? null,
                'textarea_rows' => $assignment->textarea_rows ?? null,
                'textarea_cols' => $assignment->textarea_cols ?? null,
                'min_value' => $assignment->min_value ?? null,
                'max_value' => $assignment->max_value ?? null,
                'max_characters' => $assignment->max_characters ?? null,
                'is_readonly' => (bool) ($assignment->is_readonly ?? false),
                'collection_name' => $assignment->collection_name ?? $assignment->attribute_view_name_de ?? null,
                'collection_sort' => $assignment->collection_sort,
                'attribute_sort' => $assignment->attribute_sort,
                'access_product' => $assignment->access_product ?? 'editable',
                'access_variant' => $assignment->access_variant ?? 'editable',
                'value' => $value,
                'unit_id' => $unitId,
                'unit_group' => $unitGroupData,
                'comparison_operator_id' => $pav?->comparison_operator_id ?? null,
                'comparison_operators' => $this->buildComparisonOperators($assignment, $compOpGroups),
                'source' => $source,
                'is_inherited' => $source !== 'own' && $source !== 'none',
            ];

            // Formatierter Wert (read-only Vorschau im Produkteditor) — nur wenn er vom Rohwert abweicht
            if ($value !== null) {
                $formattingAttribute = $formattingAttributes->get($assignment->attribute_id);
                if ($formattingAttribute?->formattingRule) {
                    $formattedValue = AttributeFormattingService::apply($value, $formattingAttribute->formattingRule);
                    if ($formattedValue !== (string) $value) {
                        $result['formatted_value'] = $formattedValue;
                    }
                }
            }

            // Include all multiplied values for multipliable attributes
            if ($assignment->is_multipliable ?? false) {
                if ($assignment->data_type === 'Composite') {
                    // Vermehrbares Composite: Kind-Werte nach multiplied_index gruppiert
                    $result['multiplied_composites'] = $this->buildMultipliedCompositeValues(
                        $assignment->attribute_id,
                        $effectiveAttributes,
                        $multipliedValues
                    );
                } else {
                    $defaultUnitId = $assignment->default_unit_id ?? null;
                    $attrMultiplied = $multipliedValues->get($assignment->attribute_id, collect());
                    $result['multiplied_values'] = $attrMultiplied
                        ->sortBy('multiplied_index')
                        ->values()
                        ->map(fn ($pav) => [
                            'multiplied_index' => $pav->multiplied_index,
                            'value' => $pav->value_string ?? $pav->value_number ?? $pav->value_date ?? $pav->value_flag ?? $pav->value_selection_id,
                            'unit_id' => $pav->unit_id ?? $defaultUnitId,
                            'language' => $pav->language,
                        ])
                        ->all();
                }
            }

            return $result;
        });

        return response()->json(['data' => $result->values()]);
    }

    /**
     * PUT /products/{product}/attribute-values — bulk save values.
     *
     * Body: { "values": [ { "attribute_id": "...", "value": ..., "language": "de" }, ... ] }
     */
    public function bulkUpdate(BulkUpdateAttributeValuesRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $values = $request->validated('values');
        $changedAttributeIds = [];

        DB::transaction(function () use ($product, $values, &$changedAttributeIds) {
            foreach ($values as $entry) {
                $attribute = Attribute::findOrFail($entry['attribute_id']);

                // Skip Composite attributes — they are containers with no own value
                if ($attribute->data_type === 'Composite') {
                    continue;
                }

                // Skip readonly attributes
                if ($attribute->is_readonly) {
                    continue;
                }

                $language = $entry['language'] ?? null;
                $multipliedIndex = $entry['multiplied_index'] ?? 0;

                // Validate translatable consistency
                if ($attribute->is_translatable && $language === null) {
                    abort(422, "Attribute '{$attribute->technical_name}' is translatable — 'language' is required.");
                }
                if (!$attribute->is_translatable && $language !== null) {
                    abort(422, "Attribute '{$attribute->technical_name}' is not translatable — 'language' must be omitted.");
                }

                // Determine which value column to set
                $valueData = $this->resolveValueColumns($attribute, $entry);

                ProductAttributeValue::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'attribute_id' => $attribute->id,
                        'language' => $language,
                        'multiplied_index' => $multipliedIndex,
                        'output_hierarchy_id' => null,
                    ],
                    array_merge($valueData, [
                        'unit_id' => $entry['unit_id'] ?? null,
                        'comparison_operator_id' => $entry['comparison_operator_id'] ?? null,
                        'is_inherited' => false,
                        'inherited_from_node_id' => null,
                        'inherited_from_product_id' => null,
                    ])
                );

                // Attribut nachträglich auf "sprachabhängig" umgestellt: eine verwaiste
                // language=NULL-Zeile darf danach nicht mehr neben der Sprachzeile bestehen bleiben.
                if ($attribute->is_translatable) {
                    ProductAttributeValue::where('product_id', $product->id)
                        ->where('attribute_id', $attribute->id)
                        ->where('multiplied_index', $multipliedIndex)
                        ->whereNull('output_hierarchy_id')
                        ->whereNull('language')
                        ->delete();
                }

                $changedAttributeIds[] = $attribute->id;
            }

            // Clean up removed multiplied entries for multipliable attributes.
            // Collect the max multiplied_index sent per attribute, then delete any
            // entries with a higher index that are left over from a previous save.
            $multipliableIndices = [];
            foreach ($values as $entry) {
                $attrId = $entry['attribute_id'];
                $idx = $entry['multiplied_index'] ?? 0;
                if (!isset($multipliableIndices[$attrId]) || $idx > $multipliableIndices[$attrId]) {
                    $multipliableIndices[$attrId] = $idx;
                }
            }

            // Batch: alle relevanten Attribute in einem Query laden statt N+1
            $attrIdsToCheck = array_keys($multipliableIndices);
            $multipliableAttrs = Attribute::whereIn('id', $attrIdsToCheck)
                ->where('is_multipliable', true)
                ->get()
                ->keyBy('id');

            foreach ($multipliableIndices as $attrId => $maxIdx) {
                $attr = $multipliableAttrs->get($attrId);
                if (!$attr) continue;

                ProductAttributeValue::where('product_id', $product->id)
                    ->where('attribute_id', $attrId)
                    ->whereNull('output_hierarchy_id')
                    ->where('multiplied_index', '>', $maxIdx)
                    ->delete();

                // Vermehrbares Composite: auch Kind- und Enkel-Werte aufräumen
                if ($attr->data_type === 'Composite') {
                    $childIds = Attribute::where('parent_attribute_id', $attrId)->pluck('id')->all();
                    if (!empty($childIds)) {
                        ProductAttributeValue::where('product_id', $product->id)
                            ->whereIn('attribute_id', $childIds)
                            ->whereNull('output_hierarchy_id')
                            ->where('multiplied_index', '>', $maxIdx)
                            ->delete();

                        // Enkel-Attribute (Kinder von Kind-Composites)
                        $grandchildIds = Attribute::whereIn('parent_attribute_id', $childIds)->pluck('id')->all();
                        if (!empty($grandchildIds)) {
                            ProductAttributeValue::where('product_id', $product->id)
                                ->whereIn('attribute_id', $grandchildIds)
                                ->whereNull('output_hierarchy_id')
                                ->where('multiplied_index', '>', $maxIdx)
                                ->delete();
                        }
                    }
                }
            }
        });

        // Dispatch event for Performance / Inheritance agents
        event(new \App\Events\AttributeValuesChanged($product->id, array_unique($changedAttributeIds)));

        // Synchroner Attribut-Mapping-Sync: Wenn Mappings vorhanden sind,
        // Ziel-Werte sofort aktualisieren (nicht über Queue).
        $syncStats = null;
        $hasMappings = AttributeMapping::whereIn('source_attribute_id', array_unique($changedAttributeIds))->exists();
        if ($hasMappings) {
            $syncService = app(AttributeMappingSyncService::class);
            $syncStats = $syncService->syncProduct($product);
        }

        return response()->json([
            'message' => 'Attribute values updated.',
            'count' => count($values),
            'sync' => $syncStats,
        ]);
    }

    /**
     * GET /products/{product}/output-hierarchy-resolved-attributes
     *
     * Returns channel-specific attributes grouped by output hierarchy.
     * Optional query param: ?hierarchy_id=UUID to filter to a single hierarchy.
     */
    public function resolvedOutputHierarchy(
        Request $request,
        Product $product,
        HierarchyInheritanceService $hierarchyService,
        AttributeValueResolver $resolver,
    ): JsonResponse {
        $this->authorize('view', $product);

        $language = $this->getPrimaryLanguage($request);
        $filterHierarchyId = $request->query('hierarchy_id');

        $allOutputAttributes = $hierarchyService->getProductOutputHierarchyAttributes($product);

        if ($allOutputAttributes->isEmpty()) {
            return response()->json(['data' => []]);
        }

        // Optionally filter to a single hierarchy
        if ($filterHierarchyId) {
            $allOutputAttributes = $allOutputAttributes->only([$filterHierarchyId]);
        }

        $result = $allOutputAttributes->map(function (array $hierarchyData, string $hierarchyId) use ($product, $resolver, $language) {
            $hierarchy = $hierarchyData['hierarchy'];
            $attributes = $hierarchyData['attributes'];

            // Load existing channel-specific values for this product + hierarchy
            $existingValuesRaw = ProductAttributeValue::where('product_id', $product->id)
                ->where('output_hierarchy_id', $hierarchyId)
                ->with('attribute')
                ->where(function ($q) use ($language) {
                    $q->whereNull('language')->orWhere('language', $language);
                })
                ->get();
            $existingValues = $existingValuesRaw->keyBy('attribute_id');
            $existingValuesGrouped = $existingValuesRaw->groupBy('attribute_id');

            // Also load master values as fallback
            $masterValuesRaw = ProductAttributeValue::where('product_id', $product->id)
                ->whereNull('output_hierarchy_id')
                ->with('attribute')
                ->where(function ($q) use ($language) {
                    $q->whereNull('language')->orWhere('language', $language);
                })
                ->get();
            $masterValues = $masterValuesRaw->keyBy('attribute_id');
            $masterValuesGrouped = $masterValuesRaw->groupBy('attribute_id');

            $attributeData = $attributes->map(function ($assignment) use ($existingValues, $existingValuesGrouped, $masterValues, $masterValuesGrouped, $hierarchyId) {
                $isMultipliable = (bool) ($assignment->is_multipliable ?? false);
                $channelPav = $existingValues->get($assignment->attribute_id);
                $masterPav = $masterValues->get($assignment->attribute_id);
                $value = null;
                $source = 'none';

                $isMapped = false;
                if ($channelPav) {
                    $value = $channelPav->value_string ?? $channelPav->value_number ?? $channelPav->value_date ?? $channelPav->value_flag ?? $channelPav->value_selection_id;
                    $source = 'own';
                    $isMapped = (bool) $channelPav->is_inherited; // is_inherited=true → vom Mapping erzeugt
                } elseif ($masterPav) {
                    $value = $masterPav->value_string ?? $masterPav->value_number ?? $masterPav->value_date ?? $masterPav->value_flag ?? $masterPav->value_selection_id;
                    $source = 'master_fallback';
                }

                return [
                    'attribute_id' => $assignment->attribute_id,
                    'attribute_technical_name' => $assignment->attribute_technical_name,
                    'attribute_name_de' => $assignment->attribute_name_de,
                    'attribute_name_en' => $assignment->attribute_name_en,
                    'data_type' => $assignment->data_type,
                    'value_list_id' => $assignment->value_list_id ?? null,
                    'is_translatable' => (bool) $assignment->is_translatable,
                    'is_multipliable' => (bool) ($assignment->is_multipliable ?? false),
                    'max_multiplied' => $assignment->max_multiplied ?? null,
                    'is_mandatory' => (bool) ($assignment->is_mandatory || !empty($assignment->is_required)),
                    'is_variant_attribute' => (bool) ($assignment->is_variant_attribute ?? false),
                    'attribute_type_id' => $assignment->attribute_type_id ?? null,
                    'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                    'composite_format' => $assignment->composite_format ?? null,
                    'delimiter' => $assignment->delimiter ?? null,
                    'textarea_rows' => $assignment->textarea_rows ?? null,
                    'textarea_cols' => $assignment->textarea_cols ?? null,
                    'min_value' => $assignment->min_value ?? null,
                    'max_value' => $assignment->max_value ?? null,
                    'max_characters' => $assignment->max_characters ?? null,
                    'is_readonly' => (bool) ($assignment->is_readonly ?? false),
                    'collection_name' => $assignment->collection_name ?? $assignment->attribute_view_name_de ?? null,
                    'collection_sort' => $assignment->collection_sort,
                    'attribute_sort' => $assignment->attribute_sort,
                    'access_product' => $assignment->access_product ?? 'editable',
                    'access_variant' => $assignment->access_variant ?? 'editable',
                    'value' => $value,
                    'multiplied_values' => $isMultipliable ? collect($existingValuesGrouped->get($assignment->attribute_id, collect()))
                        ->map(fn ($pav) => [
                            'value' => $pav->value_string ?? $pav->value_number ?? $pav->value_date ?? $pav->value_flag ?? $pav->value_selection_id,
                            'multiplied_index' => $pav->multiplied_index ?? 0,
                        ])->values()->all() : null,
                    'source' => $source,
                    'is_inherited' => $source !== 'own' && $source !== 'none',
                    'is_mapped' => $isMapped,
                    'output_hierarchy_id' => $hierarchyId,
                ];
            });

            return [
                'hierarchy_id' => $hierarchyId,
                'hierarchy_technical_name' => $hierarchy->technical_name,
                'hierarchy_name_de' => $hierarchy->name_de,
                'hierarchy_name_en' => $hierarchy->name_en ?? $hierarchy->name_de,
                'attributes' => $attributeData->values(),
            ];
        });

        return response()->json(['data' => $result->values()]);
    }

    /**
     * PUT /products/{product}/output-hierarchy-attribute-values
     *
     * Bulk save channel-specific attribute values for a product in an output hierarchy context.
     * Body: { "output_hierarchy_id": "...", "values": [ { "attribute_id": "...", "value": ..., "language": "de" }, ... ] }
     */
    public function bulkUpdateOutputHierarchy(BulkUpdateOutputHierarchyAttributeValuesRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $outputHierarchyId = $request->validated('output_hierarchy_id');
        $values = $request->validated('values');
        $changedAttributeIds = [];

        DB::transaction(function () use ($product, $outputHierarchyId, $values, &$changedAttributeIds) {
            foreach ($values as $entry) {
                $attribute = Attribute::findOrFail($entry['attribute_id']);

                if ($attribute->data_type === 'Composite') {
                    continue;
                }

                if ($attribute->is_readonly) {
                    continue;
                }

                $language = $entry['language'] ?? null;
                $multipliedIndex = $entry['multiplied_index'] ?? 0;

                if ($attribute->is_translatable && $language === null) {
                    abort(422, "Attribute '{$attribute->technical_name}' is translatable — 'language' is required.");
                }
                if (!$attribute->is_translatable && $language !== null) {
                    abort(422, "Attribute '{$attribute->technical_name}' is not translatable — 'language' must be omitted.");
                }

                $valueData = $this->resolveValueColumns($attribute, $entry);

                ProductAttributeValue::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'attribute_id' => $attribute->id,
                        'language' => $language,
                        'multiplied_index' => $multipliedIndex,
                        'output_hierarchy_id' => $outputHierarchyId,
                    ],
                    array_merge($valueData, [
                        'unit_id' => $entry['unit_id'] ?? null,
                        'comparison_operator_id' => $entry['comparison_operator_id'] ?? null,
                        'is_inherited' => false,
                        'inherited_from_node_id' => null,
                        'inherited_from_product_id' => null,
                    ])
                );

                // Attribut nachträglich auf "sprachabhängig" umgestellt: eine verwaiste
                // language=NULL-Zeile darf danach nicht mehr neben der Sprachzeile bestehen bleiben.
                if ($attribute->is_translatable) {
                    ProductAttributeValue::where('product_id', $product->id)
                        ->where('attribute_id', $attribute->id)
                        ->where('multiplied_index', $multipliedIndex)
                        ->where('output_hierarchy_id', $outputHierarchyId)
                        ->whereNull('language')
                        ->delete();
                }

                $changedAttributeIds[] = $attribute->id;
            }
        });

        event(new \App\Events\AttributeValuesChanged($product->id, array_unique($changedAttributeIds), $outputHierarchyId));

        return response()->json(['message' => 'Output hierarchy attribute values updated.', 'count' => count($values)]);
    }

    /**
     * Baut die multiplied_composites Struktur für ein vermehrbares Composite.
     * Gruppiert Kind-Werte nach multiplied_index.
     */
    private function buildMultipliedCompositeValues(
        string $compositeAttrId,
        \Illuminate\Support\Collection $effectiveAttributes,
        \Illuminate\Support\Collection $multipliedValues
    ): array {
        // Finde alle direkten Kind-Attribute dieses Composites
        $children = $effectiveAttributes->filter(
            fn ($a) => ($a->parent_attribute_id ?? null) === $compositeAttrId
        );

        if ($children->isEmpty()) {
            return [];
        }

        // Sammle alle multiplied_index Werte die für Kind-Attribute existieren
        $allIndices = collect();
        foreach ($children as $child) {
            $childValues = $multipliedValues->get($child->attribute_id, collect());
            foreach ($childValues as $pav) {
                $allIndices->push($pav->multiplied_index);
            }
            // Für Sub-Composites: auch deren Kinder-Indizes berücksichtigen
            if ($child->data_type === 'Composite') {
                $grandchildren = $effectiveAttributes->filter(
                    fn ($a) => ($a->parent_attribute_id ?? null) === $child->attribute_id
                );
                foreach ($grandchildren as $gc) {
                    $gcValues = $multipliedValues->get($gc->attribute_id, collect());
                    foreach ($gcValues as $pav) {
                        $allIndices->push($pav->multiplied_index);
                    }
                }
            }
        }

        $uniqueIndices = $allIndices->unique()->sort()->values();

        if ($uniqueIndices->isEmpty()) {
            return [];
        }

        $instances = [];
        foreach ($uniqueIndices as $idx) {
            $childrenData = [];
            foreach ($children as $child) {
                if ($child->data_type === 'Composite') {
                    // Sub-Composite: Enkel-Werte für diesen Index sammeln
                    $grandchildren = $effectiveAttributes->filter(
                        fn ($a) => ($a->parent_attribute_id ?? null) === $child->attribute_id
                    );
                    $subChildren = [];
                    foreach ($grandchildren as $gc) {
                        $gcValues = $multipliedValues->get($gc->attribute_id, collect());
                        $gcPav = $gcValues->firstWhere('multiplied_index', $idx);
                        $subChildren[$gc->attribute_id] = $gcPav
                            ? ($gcPav->value_string ?? $gcPav->value_number ?? $gcPav->value_date ?? $gcPav->value_flag ?? $gcPav->value_selection_id)
                            : null;
                    }
                    $childrenData[$child->attribute_id] = [
                        'data_type' => 'Composite',
                        'children' => $subChildren,
                    ];
                } else {
                    $childValues = $multipliedValues->get($child->attribute_id, collect());
                    $pav = $childValues->firstWhere('multiplied_index', $idx);
                    $childrenData[$child->attribute_id] = $pav
                        ? ($pav->value_string ?? $pav->value_number ?? $pav->value_date ?? $pav->value_flag ?? $pav->value_selection_id)
                        : null;
                }
            }
            $instances[] = [
                'multiplied_index' => $idx,
                'children' => $childrenData,
            ];
        }

        return $instances;
    }

    /**
     * Vergleichsoperatoren für ein Attribut aufbereiten (falls comparison_operator_group_id gesetzt).
     */
    private function buildComparisonOperators(object $assignment, \Illuminate\Support\Collection $compOpGroups): ?array
    {
        if (empty($assignment->comparison_operator_group_id)) {
            return null;
        }

        $group = $compOpGroups->get($assignment->comparison_operator_group_id);
        if (!$group) {
            return null;
        }

        return $group->operators->map(fn ($op) => [
            'id' => $op->id,
            'symbol' => $op->symbol,
            'technical_name' => $op->technical_name,
            'description_de' => $op->description_de,
        ])->values()->all();
    }

    /**
     * Map the incoming "value" to the appropriate column based on attribute data_type.
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
            'String' => array_merge($columns, ['value_string' => (string) $value]),
            'Number', 'Float' => array_merge($columns, ['value_number' => $value !== null ? (float) $value : null]),
            'Date' => array_merge($columns, ['value_date' => $value]),
            'Flag' => array_merge($columns, ['value_flag' => (bool) $value]),
            'Selection', 'Dictionary' => array_merge($columns, [
                'value_string' => $value,
                'value_selection_id' => $entry['value_selection_id'] ?? null,
            ]),
            'MultiSelection', 'SimpleMultiSelect' => array_merge($columns, [
                'value_string' => is_array($value) ? json_encode($value) : $value,
            ]),
            'RichText', 'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink',
            'DelimitedValue', 'JsonArtefact' => array_merge($columns, ['value_string' => (string) $value]),
            'Composite' => $columns,
            default => array_merge($columns, ['value_string' => (string) $value]),
        };
    }
}
