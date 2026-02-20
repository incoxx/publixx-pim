<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductVariantRequest;
use App\Http\Requests\Api\V1\UpdateVariantRulesRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\VariantInheritanceRuleResource;
use App\Models\Product;
use App\Models\VariantInheritanceRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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
            // Event listeners may fail (e.g. queue unavailable) — don't break the response
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
}
