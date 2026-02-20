<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductRequest;
use App\Http\Requests\Api\V1\UpdateProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    private const ALLOWED_INCLUDES = [
        'productType', 'attributeValues', 'variants', 'media',
        'prices', 'relations', 'parentProduct', 'masterHierarchyNode',
    ];

    private const ALLOWED_FILTERS = [
        'status', 'product_type_id', 'product_type_ref',
        'master_hierarchy_node_id',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $languages = $this->getRequestedLanguages($request);

        $query = Product::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        // If attributeValues are included, filter by language
        if (in_array('attributeValues', $this->parseIncludes($request, self::ALLOWED_INCLUDES))) {
            $this->constrainAttributeValuesForLanguages($query, $languages);
        }

        $filters = array_intersect_key(
            $request->query('filter', []),
            array_flip(self::ALLOWED_FILTERS)
        );

        // By default, exclude variants from the main product listing
        if (!isset($filters['product_type_ref'])) {
            $query->where('product_type_ref', 'product');
        }

        $this->applyFilters($query, $filters);
        $this->applySearch($query, $request, ['name', 'sku', 'ean']);
        $this->applySorting($query, $request, 'created_at', 'desc');

        return ProductResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;

        $product = Product::create($data);

        try {
            event(new \App\Events\ProductCreated($product));
        } catch (\Throwable $e) {
            // Event listeners may fail (e.g. queue unavailable) — don't break the response
        }

        return (new ProductResource($product->load('productType')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product): ProductResource
    {
        $this->authorize('view', $product);

        $languages = $this->getRequestedLanguages($request);
        $includes = $this->parseIncludes($request, self::ALLOWED_INCLUDES);

        // Build eager loading with language constraint for attribute values
        $eagerLoads = [];
        foreach ($includes as $include) {
            if ($include === 'attributeValues') {
                $eagerLoads['attributeValues'] = function ($q) use ($languages) {
                    $q->where(function ($sub) use ($languages) {
                        $sub->whereNull('language')
                            ->orWhereIn('language', $languages);
                    });
                };
            } else {
                $eagerLoads[] = $include;
            }
        }

        $product->load($eagerLoads);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $data['updated_by'] = $request->user()?->id;

        $product->update($data);

        try {
            event(new \App\Events\ProductUpdated($product));
        } catch (\Throwable $e) {
            // Don't break the response
        }

        return new ProductResource($product->fresh());
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $productId = $product->id;
        $product->delete();

        try {
            event(new \App\Events\ProductDeleted($productId));
        } catch (\Throwable $e) {
            // Don't break the response
        }

        return response()->json(null, 204);
    }
}
