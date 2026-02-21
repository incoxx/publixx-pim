<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateAttributeValuesRequest;
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

        // Get effective attributes from hierarchy
        $effectiveAttributes = $hierarchyService->getProductAttributes($product);

        // Load existing attribute values for this product
        $existingValues = $product->attributeValues()
            ->with('attribute')
            ->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            })
            ->get()
            ->keyBy('attribute_id');

        $result = $effectiveAttributes->map(function ($assignment) use ($existingValues) {
            $pav = $existingValues->get($assignment->attribute_id);
            $value = null;
            $source = 'none';

            if ($pav) {
                $value = $pav->value_string ?? $pav->value_number ?? $pav->value_date ?? $pav->value_flag ?? $pav->value_selection_id;
                $source = $pav->is_inherited ? 'hierarchy_inheritance' : 'own';
            }

            return [
                'attribute_id' => $assignment->attribute_id,
                'attribute_technical_name' => $assignment->attribute_technical_name,
                'attribute_name_de' => $assignment->attribute_name_de,
                'attribute_name_en' => $assignment->attribute_name_en,
                'data_type' => $assignment->data_type,
                'value_list_id' => $assignment->value_list_id ?? null,
                'is_translatable' => (bool) $assignment->is_translatable,
                'is_mandatory' => (bool) $assignment->is_mandatory,
                'is_variant_attribute' => (bool) ($assignment->is_variant_attribute ?? false),
                'parent_attribute_id' => $assignment->parent_attribute_id ?? null,
                'collection_name' => $assignment->collection_name,
                'collection_sort' => $assignment->collection_sort,
                'attribute_sort' => $assignment->attribute_sort,
                'access_product' => $assignment->access_product ?? 'editable',
                'access_variant' => $assignment->access_variant ?? 'editable',
                'value' => $value,
                'source' => $source,
                'is_inherited' => $source !== 'own' && $source !== 'none',
            ];
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

        // Dispatch event for Performance / Inheritance agents
        event(new \App\Events\AttributeValuesChanged($product->id, array_unique($changedAttributeIds)));

        return response()->json(['message' => 'Attribute values updated.', 'count' => count($values)]);
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
            'Collection' => array_merge($columns, ['value_string' => is_array($value) ? json_encode($value) : (string) $value]),
            'Composite' => $columns,
            default => array_merge($columns, ['value_string' => (string) $value]),
        };
    }
}
