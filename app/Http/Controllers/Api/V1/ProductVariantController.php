<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductVariantRequest;
use App\Http\Requests\Api\V1\UpdateVariantAxesRequest;
use App\Http\Requests\Api\V1\UpdateVariantRulesRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\ProductVariantAxisResource;
use App\Http\Resources\Api\V1\VariantInheritanceRuleResource;
use App\Http\Traits\ChecksTabPermissions;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Inheritance\VariantAxisService;
use App\Services\Inheritance\VariantInheritanceService;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    use ChecksTabPermissions;
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
     *
     * Optionaler Body-Block "axis_values": { attribute_id => wert } für die
     * konfigurierten Varianten-Achsen des Elternprodukts.
     */
    public function store(StoreProductVariantRequest $request, Product $product, VariantAxisService $variantAxisService): JsonResponse
    {
        $this->authorize('create', Product::class);
        $this->assertTabWriteAccess('variants');

        $data = $request->validated();
        $axisValues = $data['axis_values'] ?? [];
        unset($data['axis_values']);

        $data['parent_product_id'] = $product->id;
        $data['product_type_id'] = $product->product_type_id;
        $data['product_type_ref'] = 'variant';
        $data['created_by'] = $request->user()?->id;

        $variant = DB::transaction(function () use ($data, $axisValues, $variantAxisService) {
            $variant = Product::create($data);

            foreach ($axisValues as $attributeId => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $attribute = Attribute::find($attributeId);
                if (!$attribute) {
                    continue;
                }

                $valueData = $this->resolveValueColumns($attribute, (string) $value);

                ProductAttributeValue::create(array_merge($valueData, [
                    'product_id' => $variant->id,
                    'attribute_id' => $attribute->id,
                    'language' => $attribute->is_translatable ? 'de' : null,
                    'multiplied_index' => 0,
                    'is_inherited' => false,
                ]));
            }

            $variantAxisService->ensureOverrideRules($variant);
            $variantAxisService->assertUniqueCombination($variant);

            return $variant;
        });

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
    public function updateRules(UpdateVariantRulesRequest $request, Product $product, VariantInheritanceService $variantInheritanceService, VariantAxisService $variantAxisService): JsonResponse
    {
        $this->authorize('update', $product);
        $this->assertTabWriteAccess('variants');

        if (!$product->parent_product_id) {
            throw ValidationException::withMessages([
                'product' => 'Vererbungsregeln können nur für eine Variante gesetzt werden (Produkt hat kein Elternprodukt).',
            ]);
        }

        $rules = $request->validated('rules');
        $rulesMap = [];
        foreach ($rules as $rule) {
            $rulesMap[$rule['attribute_id']] = $rule['inheritance_mode'];
        }

        // Über den Service statt direktem Model-Zugriff: erzwingt u.a., dass eine
        // konfigurierte Varianten-Achse nicht auf "inherit" gesetzt werden kann.
        DB::transaction(function () use ($product, $rulesMap, $variantInheritanceService, $variantAxisService) {
            $variantInheritanceService->resetAllRules($product);
            if (!empty($rulesMap)) {
                $variantInheritanceService->setRules($product, $rulesMap);
            }

            // Achsen-Attribute des Elternprodukts müssen immer override bleiben,
            // auch wenn der Client sie in der Regel-Liste nicht mitgeschickt hat
            // (sonst würde reset+set sie stillschweigend auf inherit zurückfallen
            // lassen und die Eindeutigkeit der Merkmalskombination aushebeln).
            $variantAxisService->ensureOverrideRules($product);
        });

        return response()->json([
            'message' => 'Variant inheritance rules updated.',
            'count' => count($rules),
        ]);
    }

    /**
     * GET /products/{product}/variant-axes — konfigurierte Merkmalsachsen dieses
     * Elternprodukts (welche Attribute unterscheiden seine Varianten).
     */
    public function axes(Request $request, Product $product, VariantAxisService $variantAxisService): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        return ProductVariantAxisResource::collection($variantAxisService->getAxes($product));
    }

    /**
     * PUT /products/{product}/variant-axes — Merkmalsachsen ersetzen.
     *
     * Body: { "attribute_ids": ["uuid", ...] } — Reihenfolge = Spaltenreihenfolge.
     */
    public function updateAxes(UpdateVariantAxesRequest $request, Product $product, VariantAxisService $variantAxisService): AnonymousResourceCollection
    {
        $this->authorize('update', $product);
        $this->assertTabWriteAccess('variants');

        $variantAxisService->setAxes($product, $request->validated('attribute_ids'));

        return ProductVariantAxisResource::collection($variantAxisService->getAxes($product));
    }

    /**
     * GET /products/{product}/variant-matrix — Varianten als Matrix: Spalten
     * sind die konfigurierten Achsen, Zeilen die Varianten mit ihren Achsen-Werten.
     */
    public function matrix(Request $request, Product $product, VariantAxisService $variantAxisService): JsonResponse
    {
        $this->authorize('view', $product);

        $axes = $variantAxisService->getAxes($product);
        $variants = $product->variants()->orderBy('sku')->get();
        $axisAttributeIds = $axes->pluck('attribute_id')->all();

        $valuesByProduct = empty($axisAttributeIds) || $variants->isEmpty()
            ? collect()
            : ProductAttributeValue::whereIn('product_id', $variants->pluck('id'))
                ->whereIn('attribute_id', $axisAttributeIds)
                ->whereNull('output_hierarchy_id')
                ->where(function ($q) {
                    $q->whereNull('language')->orWhere('language', 'de');
                })
                ->get()
                ->groupBy('product_id');

        $rows = $variants->map(function (Product $variant) use ($valuesByProduct, $axisAttributeIds) {
            $variantValues = $valuesByProduct->get($variant->id, collect())->groupBy('attribute_id');

            $axisValues = [];
            foreach ($axisAttributeIds as $attributeId) {
                $rowsForAttribute = $variantValues->get($attributeId, collect());
                $pav = $rowsForAttribute->firstWhere('language', 'de') ?? $rowsForAttribute->firstWhere('language', null);
                $axisValues[$attributeId] = $pav
                    ? ($pav->value_string ?? $pav->value_number ?? $pav->value_date ?? $pav->value_flag ?? $pav->value_selection_id)
                    : null;
            }

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'status' => $variant->status,
                'axis_values' => $axisValues,
            ];
        });

        return response()->json([
            'columns' => ProductVariantAxisResource::collection($axes)->resolve($request),
            'rows' => $rows->values(),
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
    public function generate(Request $request, Product $product, VariantAxisService $variantAxisService): JsonResponse
    {
        $this->authorize('create', Product::class);
        $this->assertTabWriteAccess('variants');

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

        // Eine äußere Transaktion um den gesamten Batch: die innere
        // DB::transaction() je Kombination wird dadurch automatisch zu einem
        // Savepoint (nur dieser wird bei einer erwarteten ValidationException
        // zurückgerollt). Ein unerwarteter Fehler (z.B. echter DB-Fehler) rollt
        // dagegen den ganzen Batch zurück, statt bereits erstellte Varianten
        // committed stehenzulassen und die Response zu verlieren.
        DB::transaction(function () use (
            $combinations, $product, $skuPrefix, $status, $userId, $attributeMap, $variantAxisService,
            &$created, &$skipped, &$createdVariants
        ) {
            foreach ($combinations as $combo) {
                try {
                    $variant = DB::transaction(function () use (
                        $product, $combo, $skuPrefix, $status, $userId, $attributeMap, $variantAxisService
                    ) {
                        // Generate SKU from combination values
                        $skuParts = array_map(function ($item) {
                            return Str::slug(Str::limit($item['value'], 20, ''), '-');
                        }, $combo);
                        $sku = $skuPrefix . '-' . implode('-', $skuParts);

                        // Check for SKU collision — nothing created yet, safe to skip without rollback
                        if (Product::where('sku', $sku)->exists()) {
                            return null;
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

                        // Achsen-Attribute des Elternprodukts dürfen bei Varianten nie
                        // geerbt werden, und Geschwister-Kombinationen müssen eindeutig
                        // bleiben — wirft ValidationException und rollt diese Variante
                        // zurück, falls eine identische Kombination bereits existiert.
                        $variantAxisService->ensureOverrideRules($variant);
                        $variantAxisService->assertUniqueCombination($variant);

                        return $variant;
                    });
                } catch (ValidationException $e) {
                    $skipped++;
                    continue;
                }

                if ($variant === null) {
                    $skipped++;
                    continue;
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
            'Selection', 'Dictionary' => $this->resolveSelectionValueColumns($attribute, $value, $columns),
            'RichText', 'Hyperlink', 'ImageLink', 'PdfLink', 'VideoLink' => array_merge($columns, ['value_string' => $value]),
            default => array_merge($columns, ['value_string' => $value]),
        };
    }

    /**
     * @throws ValidationException
     */
    private function resolveSelectionValueColumns(Attribute $attribute, string $value, array $columns): array
    {
        return array_merge($columns, $attribute->resolveSelectionEntry($value));
    }
}
