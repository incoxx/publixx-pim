<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateAttributeValuesRequest;
use App\Http\Requests\Api\V1\BulkUpdateOutputHierarchyAttributeValuesRequest;
use App\Http\Resources\Api\V1\ProductAttributeValueResource;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
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
            ->with(['attribute', 'attribute.unitGroup', 'unit', 'valueListEntry'])
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
            $node = \App\Models\HierarchyNode::find($overrideNodeId);
            $effectiveAttributes = $node
                ? $hierarchyService->getEffectiveAttributes($node)
                : collect();
        } else {
            $effectiveAttributes = $hierarchyService->getProductAttributes($product);
        }

        // Load existing master attribute values for this product (exclude channel-specific)
        // Use groupBy to support multipliable attributes with multiple values
        $allExistingValues = $product->attributeValues()
            ->with('attribute')
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
        $compositeIds = $effectiveAttributes
            ->filter(fn ($a) => $a->data_type === 'Composite')
            ->pluck('attribute_id')
            ->all();

        if (!empty($compositeIds)) {
            $existingAttrIds = $effectiveAttributes->pluck('attribute_id')->all();
            $missingChildren = Attribute::whereIn('parent_attribute_id', $compositeIds)
                ->whereNotIn('id', $existingAttrIds)
                ->get();

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
                    'is_mandatory' => $child->is_mandatory,
                    'is_variant_attribute' => $child->is_variant_attribute ?? false,
                    'parent_attribute_id' => $child->parent_attribute_id,
                    'composite_format' => $child->composite_format,
                    'composite_expression' => $child->composite_expression,
                    'collection_name' => $parent->collection_name ?? null,
                    'collection_sort' => $parent->collection_sort ?? 0,
                    'attribute_sort' => $child->position ?? 999,
                    'access_product' => $parent->access_product ?? 'editable',
                    'access_variant' => $parent->access_variant ?? 'editable',
                    'attribute_view_name_de' => $parent->attribute_view_name_de ?? null,
                ]);

                // Also load existing values for these children (master context only)
                $childValues = $product->attributeValues()
                    ->where('attribute_id', $child->id)
                    ->whereNull('output_hierarchy_id')
                    ->where(function ($q) use ($language) {
                        $q->whereNull('language')->orWhere('language', $language);
                    })
                    ->first();
                if ($childValues) {
                    $existingValues->put($child->id, $childValues);
                }
            }
        }

        $result = $effectiveAttributes->map(function ($assignment) use ($existingValues, $multipliedValues) {
            $pav = $existingValues->get($assignment->attribute_id);
            $value = null;
            $source = 'none';

            if ($pav) {
                $value = $pav->value_string ?? $pav->value_number ?? $pav->value_date ?? $pav->value_flag ?? $pav->value_selection_id;
                $source = $pav->is_inherited ? 'hierarchy_inheritance' : 'own';
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
                'max_multiplied' => $assignment->max_multiplied ?? null,
                'attribute_type_id' => $assignment->attribute_type_id ?? null,
                'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                'composite_format' => $assignment->composite_format ?? null,
                'composite_expression' => $assignment->composite_expression ?? null,
                'collection_name' => $assignment->collection_name ?? $assignment->attribute_view_name_de ?? null,
                'collection_sort' => $assignment->collection_sort,
                'attribute_sort' => $assignment->attribute_sort,
                'access_product' => $assignment->access_product ?? 'editable',
                'access_variant' => $assignment->access_variant ?? 'editable',
                'value' => $value,
                'source' => $source,
                'is_inherited' => $source !== 'own' && $source !== 'none',
            ];

            // Include all multiplied values for multipliable attributes
            if ($assignment->is_multipliable ?? false) {
                $attrMultiplied = $multipliedValues->get($assignment->attribute_id, collect());
                $result['multiplied_values'] = $attrMultiplied
                    ->sortBy('multiplied_index')
                    ->values()
                    ->map(fn ($pav) => [
                        'multiplied_index' => $pav->multiplied_index,
                        'value' => $pav->value_string ?? $pav->value_number ?? $pav->value_date ?? $pav->value_flag ?? $pav->value_selection_id,
                        'language' => $pav->language,
                    ])
                    ->all();
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

            foreach ($multipliableIndices as $attrId => $maxIdx) {
                $attr = Attribute::find($attrId);
                if ($attr && $attr->is_multipliable) {
                    ProductAttributeValue::where('product_id', $product->id)
                        ->where('attribute_id', $attrId)
                        ->whereNull('output_hierarchy_id')
                        ->where('multiplied_index', '>', $maxIdx)
                        ->delete();
                }
            }
        });

        // Dispatch event for Performance / Inheritance agents
        event(new \App\Events\AttributeValuesChanged($product->id, array_unique($changedAttributeIds)));

        return response()->json(['message' => 'Attribute values updated.', 'count' => count($values)]);
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
            $existingValues = ProductAttributeValue::where('product_id', $product->id)
                ->where('output_hierarchy_id', $hierarchyId)
                ->with('attribute')
                ->where(function ($q) use ($language) {
                    $q->whereNull('language')->orWhere('language', $language);
                })
                ->get()
                ->keyBy('attribute_id');

            // Also load master values as fallback
            $masterValues = ProductAttributeValue::where('product_id', $product->id)
                ->whereNull('output_hierarchy_id')
                ->with('attribute')
                ->where(function ($q) use ($language) {
                    $q->whereNull('language')->orWhere('language', $language);
                })
                ->get()
                ->keyBy('attribute_id');

            $attributeData = $attributes->map(function ($assignment) use ($existingValues, $masterValues, $hierarchyId) {
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
                    'is_mandatory' => (bool) ($assignment->is_mandatory || !empty($assignment->is_required)),
                    'is_variant_attribute' => (bool) ($assignment->is_variant_attribute ?? false),
                    'attribute_type_id' => $assignment->attribute_type_id ?? null,
                    'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                    'composite_format' => $assignment->composite_format ?? null,
                    'collection_name' => $assignment->collection_name ?? $assignment->attribute_view_name_de ?? null,
                    'collection_sort' => $assignment->collection_sort,
                    'attribute_sort' => $assignment->attribute_sort,
                    'access_product' => $assignment->access_product ?? 'editable',
                    'access_variant' => $assignment->access_variant ?? 'editable',
                    'value' => $value,
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

                $changedAttributeIds[] = $attribute->id;
            }
        });

        event(new \App\Events\AttributeValuesChanged($product->id, array_unique($changedAttributeIds), $outputHierarchyId));

        return response()->json(['message' => 'Output hierarchy attribute values updated.', 'count' => count($values)]);
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
            'RichText', 'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink' => array_merge($columns, ['value_string' => (string) $value]),
            'Composite' => $columns,
            default => array_merge($columns, ['value_string' => (string) $value]),
        };
    }
}
