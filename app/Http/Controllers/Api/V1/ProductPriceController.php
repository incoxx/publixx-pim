<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductPriceRequest;
use App\Http\Requests\Api\V1\UpdateProductPriceRequest;
use App\Http\Resources\Api\V1\ProductPriceResource;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductPriceController extends Controller
{
    /**
     * GET /products/{product}/prices
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = $product->prices()
            ->with('priceType')
            ->orderBy('valid_from', 'desc');

        return ProductPriceResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /products/{product}/prices
     */
    public function store(StoreProductPriceRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $price = $product->prices()->create($request->validated());

        return (new ProductPriceResource($price->load('priceType')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /product-prices/{id}
     */
    public function update(UpdateProductPriceRequest $request, ProductPrice $productPrice): ProductPriceResource
    {
        $this->authorize('update', $productPrice->product);

        $productPrice->update($request->validated());

        return new ProductPriceResource($productPrice->fresh()->load('priceType'));
    }

    /**
     * DELETE /product-prices/{id}
     */
    public function destroy(ProductPrice $productPrice): JsonResponse
    {
        $this->authorize('update', $productPrice->product);

        $productPrice->delete();

        return response()->json(null, 204);
    }
}
