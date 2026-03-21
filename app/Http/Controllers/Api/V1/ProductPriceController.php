<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductPriceRequest;
use App\Http\Requests\Api\V1\UpdateProductPriceRequest;
use App\Http\Resources\Api\V1\ProductPriceResource;
use App\Http\Traits\AuditsChanges;
use App\Http\Traits\ChecksTabPermissions;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductPriceController extends Controller
{
    use AuditsChanges;
    use ChecksTabPermissions;
    /**
     * GET /products/{product}/prices
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = $product->prices()
            ->with(['priceType', 'priceRegion'])
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
        $this->assertTabWriteAccess('prices');

        $price = $product->prices()->create($request->validated());

        $this->audit('price_added', 'Product', $product->id, null, [
            'price_id' => $price->id,
            'price_type_id' => $price->price_type_id,
            'amount' => $price->amount,
            'currency' => $price->currency,
        ]);

        return (new ProductPriceResource($price->load(['priceType', 'priceRegion'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /product-prices/{id}
     */
    public function update(UpdateProductPriceRequest $request, ProductPrice $productPrice): ProductPriceResource
    {
        $this->authorize('update', $productPrice->product);
        $this->assertTabWriteAccess('prices');

        $productPrice->update($request->validated());
        $newValues = $productPrice->getChanges();
        unset($newValues['updated_at']);

        if (!empty($newValues)) {
            $oldValues = collect($productPrice->getOriginal())
                ->only(array_keys($newValues))
                ->toArray();
            $this->audit('price_updated', 'Product', $productPrice->product_id, $oldValues, $newValues);
        }

        return new ProductPriceResource($productPrice->fresh()->load(['priceType', 'priceRegion']));
    }

    /**
     * DELETE /product-prices/{id}
     */
    public function destroy(ProductPrice $productPrice): JsonResponse
    {
        $this->authorize('update', $productPrice->product);
        $this->assertTabWriteAccess('prices');

        $snapshot = [
            'price_id' => $productPrice->id,
            'price_type_id' => $productPrice->price_type_id,
            'amount' => $productPrice->amount,
            'currency' => $productPrice->currency,
        ];
        $productId = $productPrice->product_id;

        $productPrice->delete();

        $this->audit('price_removed', 'Product', $productId, $snapshot);

        return response()->json(null, 204);
    }
}
