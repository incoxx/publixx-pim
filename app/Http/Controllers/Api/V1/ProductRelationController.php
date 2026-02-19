<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductRelationRequest;
use App\Http\Resources\Api\V1\ProductRelationResource;
use App\Models\Product;
use App\Models\ProductRelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductRelationController extends Controller
{
    /**
     * GET /products/{product}/relations
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = $product->relations()
            ->with(['relationType', 'targetProduct', 'attributeValues.attribute'])
            ->orderBy('sort_order', 'asc');

        return ProductRelationResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /products/{product}/relations
     */
    public function store(StoreProductRelationRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = $request->validated();
        $data['source_product_id'] = $product->id;

        $relation = ProductRelation::create($data);

        return (new ProductRelationResource($relation->load(['relationType', 'targetProduct', 'attributeValues.attribute'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /product-relations/{id}
     */
    public function destroy(ProductRelation $productRelation): JsonResponse
    {
        $this->authorize('update', $productRelation->sourceProduct);

        $productRelation->delete();

        return response()->json(null, 204);
    }
}
