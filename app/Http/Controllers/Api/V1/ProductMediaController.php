<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductMediaRequest;
use App\Http\Resources\Api\V1\ProductMediaResource;
use App\Models\Product;
use App\Models\ProductMediaAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductMediaController extends Controller
{
    /**
     * GET /products/{product}/media — assigned media for a product.
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = $product->mediaAssignments()
            ->with(['media', 'usageType'])
            ->orderBy('sort_order', 'asc');

        return ProductMediaResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * POST /products/{product}/media — assign a medium to the product.
     */
    public function store(StoreProductMediaRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $assignment = $product->mediaAssignments()->create($request->validated());

        return (new ProductMediaResource($assignment->load(['media', 'usageType'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /product-media/{id} — remove assignment.
     */
    public function destroy(ProductMediaAssignment $productMedium): JsonResponse
    {
        $this->authorize('update', $productMedium->product);

        $productMedium->delete();

        return response()->json(null, 204);
    }
}
