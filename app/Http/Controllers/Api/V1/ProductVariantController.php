<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductVariantRequest;
use App\Http\Requests\Api\V1\UpdateVariantRulesRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\VariantInheritanceRuleResource;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\VariantInheritanceRule;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductVariantController extends Controller
{
    /**
     * GET /products/{product}/variants — list variants of a parent product.
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = $product->variants()
            ->with($this->parseIncludes($request, ['productType', 'attributeValues', 'media', 'prices']));

        $this->applySorting($query, $request, 'sku', 'asc');

        return ProductResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /products/{product}/variants — create a variant for this parent.
     */
    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validated();
        $data['parent_product_id'] = $product->id;
        $data['product_type_id'] = $product->product_type_id;
        $data['product_type_ref'] = 'variant';
        $data['created_by'] = $request->user()?->id;

        $variant = Product::create($data);

        try {
            event(new \App\Events\ProductCreated($variant));
        } catch (\Throwable $e) {
            Log::warning('ProductCreated event failed for variant', ['variant_id' => $variant->id, 'error' => $e->getMessage()]);
        }

        return (new ProductResource($variant))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /products/{product}/variant-rules — inheritance rules for variants.
     */
    public function rules(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $rules = $product->variantInheritanceRules()
            ->with('attribute')
            ->get();

        return VariantInheritanceRuleResource::collection($rules);
    }

    /**
     * PUT /products/{product}/variant-rules — set inheritance rules.
     *
     * Body: { "rules": [ { "attribute_id": "...", "inheritance_mode": "inherit|override" } ] }
     */
    public function updateRules(UpdateVariantRulesRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $rules = $request->validated('rules');

        DB::transaction(function () use ($product, $rules) {
            // Remove existing rules
            $product->variantInheritanceRules()->delete();

            // Insert new rules
            foreach ($rules as $rule) {
                VariantInheritanceRule::create([
                    'product_id' => $product->id,
                    'attribute_id' => $rule['attribute_id'],
                    'inheritance_mode' => $rule['inheritance_mode'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Variant inheritance rules updated.',
            'count' => count($rules),
        ]);
    }

    /**
     * POST /products/{product}/variants/generate
     *
     * Generate variants from cross-product of dimension values.
     *
     * Body: {
     *   "dimensions": [
     *     { "attribute_id": "uuid", "values": ["Red", "Green", "Blue"] },
     *     { "attribute_id": "uuid", "values": ["S", "M", "L"] }
     *   ],
     *   "sku_prefix": "SHIRT-001",   // optional, default: parent SKU
     *   "status": "draft"             // optional, default: draft
     * }
     */
    public function generate(Request $request, Product $product): JsonResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'dimensions' => 'required|array|min:1|max:10',
            'dimensions.*.attribute_id' => 'required|string|uuid|exists:attributes,id',
            'dimensions.*.values' => 'required|array|min:1|max:100',
            'dimensions.*.values.*' => 'required|string|max:500',
            'sku_prefix' => 'nullable|string|max:80',
            'status' => 'nullable|string|in:draft,active,inactive,discontinued',
        ]);

        $dimensions = $validated['dimensions'];
        $skuPrefix = $validated['sku_prefix'] ?? $product->sku;
        $status = $validated['status'] ?? 'draft';
        $userId = $request->user()?->id;

        // Load attribute definitions for value column resolution
        $attributeMap = Attribute::whereIn('id', collect($dimensions)->pluck('attribute_id'))
            ->get()
            ->keyBy('id');

        // Build cross-product of all dimension values
        $combinations = [[]];
        foreach ($dimensions as $dim) {
            $newCombinations = [];
            foreach ($combinations as $combo) {
                foreach ($dim['values'] as $value) {
                    $newCombinations[] = array_merge($combo, [
                        ['attribute_id' => $dim['attribute_id'], 'value' => $value],
                    ]);
                }
            }
            $combinations = $newCombinations;
        }

        $created = 0;
        $skipped = 0;
        $createdVariants = [];

        DB::transaction(function () use (
            $product, $combinations, $skuPrefix, $status, $userId,
            $attributeMap, &$created, &$skipped, &$createdVariants
        ) {
            foreach ($combinations as $combo) {
                // Generate SKU from combination values
                $skuParts = array_map(function ($item) {
                    return Str::slug(Str::limit($item['value'], 20, ''), '-');
                }, $combo);
                $sku = $skuPrefix . '-' . implode('-', $skuParts);

                // Check for SKU collision
                if (Product::where('sku', $sku)->exists()) {
                    $skipped++;
                    continue;
                }

                // Generate name
                $valueParts = array_map(fn($item) => $item['value'], $combo);
                $name = $product->name . ' — ' . implode(' / ', $valueParts);

                // Create variant
                $variant = Product::create([
                    'sku' => $sku,
                    'name' => $name,
                    'status' => $status,
                    'product_type_id' => $product->product_type_id,
                    'product_type_ref' => 'variant',
                    'parent_product_id' => $product->id,
                    'master_hierarchy_node_id' => $product->master_hierarchy_node_id,
                    'created_by' => $userId,
                ]);

                // Set attribute values for each dimension
                foreach ($combo as $item) {
                    $attribute = $attributeMap->get($item['attribute_id']);
                    if (!$attribute) continue;

                    $valueData = $this->resolveValueColumns($attribute, $item['value']);

                    ProductAttributeValue::create(array_merge($valueData, [
                        'product_id' => $variant->id,
                        'attribute_id' => $attribute->id,
                        'language' => $attribute->is_translatable ? 'de' : null,
                        'multiplied_index' => 0,
                        'is_inherited' => false,
                    ]));
                }

                try {
                    event(new \App\Events\ProductCreated($variant));
                } catch (\Throwable $e) {
                    Log::warning('ProductCreated event failed for generated variant', [
                        'variant_id' => $variant->id, 'error' => $e->getMessage(),
                    ]);
                }

                $createdVariants[] = ['id' => $variant->id, 'sku' => $variant->sku, 'name' => $variant->name];
                $created++;
            }
        });

        return response()->json([
            'message' => "Variant generation completed.",
            'created' => $created,
            'skipped' => $skipped,
            'total_combinations' => count($combinations),
            'variants' => $createdVariants,
        ]);
    }

    /**
     * Map a value to the appropriate column based on attribute data_type.
     */
    private function resolveValueColumns(Attribute $attribute, string $value): array
    {
        $columns = [
            'value_string' => null,
            'value_number' => null,
            'value_date' => null,
            'value_flag' => null,
            'value_selection_id' => null,
        ];

        return match ($attribute->data_type) {
            'Number', 'Float' => array_merge($columns, ['value_number' => (float) $value]),
            'Date' => array_merge($columns, ['value_date' => $value]),
            'Flag' => array_merge($columns, ['value_flag' => in_array(strtolower($value), ['true', '1', 'ja', 'yes'])]),
            'Selection', 'Dictionary' => array_merge($columns, [
                'value_string' => $value,
                'value_selection_id' => $value,
            ]),
            default => array_merge($columns, ['value_string' => $value]),
        };
    }
}
