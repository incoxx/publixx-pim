<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bulk attribute editor — edit multiple products × multiple attributes at once.
 *
 * POST /products/bulk-edit      → Load current values for given product+attribute combos
 * PUT  /products/bulk-edit      → Save changed values across products
 */
class BulkEditorController extends Controller
{
    /**
     * POST /api/v1/products/bulk-edit
     *
     * Load current attribute values for the given products.
     *
     * Body: {
     *   "product_ids": ["uuid1", "uuid2", ...],
     *   "attribute_ids": ["uuid-a", "uuid-b", ...],  // optional — if omitted, all editable attributes
     *   "language": "de"
     * }
     *
     * Response: {
     *   "products": [ { id, sku, name, status } ],
     *   "attributes": [ { id, technical_name, name_de, name_en, data_type, is_translatable, value_list? } ],
     *   "values": { "productId|attributeId": value, ... }
     * }
     */
    public function load(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:200',
            'product_ids.*' => 'string|uuid',
            'attribute_ids' => 'nullable|array',
            'attribute_ids.*' => 'string|uuid',
            'language' => 'nullable|string|max:5',
        ]);

        $productIds = $validated['product_ids'];
        $language = $validated['language'] ?? 'de';

        // Load products
        $products = Product::whereIn('id', $productIds)
            ->select('id', 'sku', 'name', 'status')
            ->orderByRaw("FIELD(id, " . collect($productIds)->map(fn() => '?')->join(',') . ")", $productIds)
            ->get();

        // Load attributes
        $attrQuery = Attribute::where('is_internal', false)
            ->whereNotIn('data_type', ['Composite']);

        if (!empty($validated['attribute_ids'])) {
            $attrQuery->whereIn('id', $validated['attribute_ids']);
        }

        $attributes = $attrQuery
            ->with('valueList.entries')
            ->orderBy('position')
            ->get();

        // Load existing values
        $values = ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $attributes->pluck('id'))
            ->where(function ($q) use ($language) {
                $q->whereNull('language')->orWhere('language', $language);
            })
            ->where('multiplied_index', 0)
            ->get();

        // Build values map: "productId|attributeId" => raw value
        $valuesMap = [];
        foreach ($values as $pav) {
            $key = $pav->product_id . '|' . $pav->attribute_id;
            $valuesMap[$key] = $this->extractValue($pav);
        }

        // Format attributes for frontend
        $attrData = $attributes->map(function ($attr) {
            $item = [
                'id' => $attr->id,
                'technical_name' => $attr->technical_name,
                'name_de' => $attr->name_de,
                'name_en' => $attr->name_en,
                'data_type' => $attr->data_type,
                'is_translatable' => (bool) $attr->is_translatable,
            ];

            if ($attr->valueList) {
                $item['value_list'] = [
                    'id' => $attr->valueList->id,
                    'entries' => $attr->valueList->entries->map(fn($e) => [
                        'id' => $e->id,
                        'code' => $e->code,
                        'display_value_de' => $e->display_value_de,
                        'display_value_en' => $e->display_value_en,
                    ])->toArray(),
                ];
            }

            return $item;
        });

        return response()->json([
            'products' => $products,
            'attributes' => $attrData->values(),
            'values' => $valuesMap,
        ]);
    }

    /**
     * PUT /api/v1/products/bulk-edit
     *
     * Save changed attribute values across multiple products.
     *
     * Body: {
     *   "changes": [
     *     { "product_id": "...", "attribute_id": "...", "value": ..., "language": "de" },
     *     ...
     *   ]
     * }
     */
    public function save(Request $request): JsonResponse
    {
        $this->authorize('update', Product::class);

        $validated = $request->validate([
            'changes' => 'required|array|min:1|max:5000',
            'changes.*.product_id' => 'required|string|uuid',
            'changes.*.attribute_id' => 'required|string|uuid',
            'changes.*.value' => 'present',
            'changes.*.language' => 'nullable|string|max:5',
        ]);

        $changes = $validated['changes'];
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($changes, &$updated, &$errors) {
            // Pre-load all referenced products and attributes
            $productIds = collect($changes)->pluck('product_id')->unique();
            $attributeIds = collect($changes)->pluck('attribute_id')->unique();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
            $attributes = Attribute::whereIn('id', $attributeIds)->get()->keyBy('id');

            foreach ($changes as $change) {
                $product = $products->get($change['product_id']);
                $attribute = $attributes->get($change['attribute_id']);

                if (!$product || !$attribute) {
                    $errors[] = "Product or attribute not found: {$change['product_id']}|{$change['attribute_id']}";
                    continue;
                }

                $language = $change['language'] ?? null;

                // Determine value columns
                $valueData = $this->resolveValueColumns($attribute, $change['value']);

                ProductAttributeValue::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'attribute_id' => $attribute->id,
                        'language' => $language,
                        'multiplied_index' => 0,
                    ],
                    array_merge($valueData, [
                        'is_inherited' => false,
                        'inherited_from_node_id' => null,
                        'inherited_from_product_id' => null,
                    ])
                );

                $updated++;
            }

            // Fire events for changed products
            foreach ($productIds as $pid) {
                $changedAttrs = collect($changes)
                    ->where('product_id', $pid)
                    ->pluck('attribute_id')
                    ->unique()
                    ->toArray();
                if (!empty($changedAttrs)) {
                    event(new \App\Events\AttributeValuesChanged($pid, $changedAttrs));
                }
            }
        });

        return response()->json([
            'message' => "Bulk edit completed.",
            'updated' => $updated,
            'errors' => array_slice($errors, 0, 20),
        ]);
    }

    private function extractValue(ProductAttributeValue $pav): mixed
    {
        if ($pav->value_string !== null) return $pav->value_string;
        if ($pav->value_number !== null) return $pav->value_number;
        if ($pav->value_date !== null) return $pav->value_date;
        if ($pav->value_flag !== null) return (bool) $pav->value_flag;
        if ($pav->value_selection_id !== null) return $pav->value_selection_id;
        return null;
    }

    private function resolveValueColumns(Attribute $attribute, mixed $value): array
    {
        $columns = [
            'value_string' => null,
            'value_number' => null,
            'value_date' => null,
            'value_flag' => null,
            'value_selection_id' => null,
        ];

        return match ($attribute->data_type) {
            'String' => array_merge($columns, ['value_string' => $value !== null ? (string) $value : null]),
            'Number', 'Float' => array_merge($columns, ['value_number' => $value !== null ? (float) $value : null]),
            'Date' => array_merge($columns, ['value_date' => $value]),
            'Flag' => array_merge($columns, ['value_flag' => $value !== null ? (bool) $value : null]),
            'Selection', 'Dictionary' => array_merge($columns, [
                'value_string' => $value !== null ? (string) $value : null,
                'value_selection_id' => $value,
            ]),
            default => array_merge($columns, ['value_string' => $value !== null ? (string) $value : null]),
        };
    }
}
