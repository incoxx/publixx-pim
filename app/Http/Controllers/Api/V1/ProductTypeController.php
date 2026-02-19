<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductTypeRequest;
use App\Http\Requests\Api\V1\UpdateProductTypeRequest;
use App\Http\Resources\Api\V1\ProductTypeResource;
use App\Models\ProductType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductTypeController extends Controller
{
    private const ALLOWED_INCLUDES = ['products'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProductType::class);

        $query = ProductType::query()
            ->with($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        $this->applyFilters($query, array_intersect_key(
            $request->query('filter', []),
            array_flip(['is_active'])
        ));
        $this->applySorting($query, $request, 'sort_order', 'asc');

        return ProductTypeResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function store(StoreProductTypeRequest $request): JsonResponse
    {
        $this->authorize('create', ProductType::class);

        $type = ProductType::create($request->validated());

        return (new ProductTypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ProductType $productType): ProductTypeResource
    {
        $this->authorize('view', $productType);

        $productType->load($this->parseIncludes($request, self::ALLOWED_INCLUDES));

        return new ProductTypeResource($productType);
    }

    public function update(UpdateProductTypeRequest $request, ProductType $productType): ProductTypeResource
    {
        $this->authorize('update', $productType);

        $productType->update($request->validated());

        return new ProductTypeResource($productType->fresh());
    }

    public function destroy(ProductType $productType): JsonResponse
    {
        $this->authorize('delete', $productType);

        $productType->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /product-types/{id}/schema — effective attribute schema for this type.
     */
    public function schema(ProductType $productType): JsonResponse
    {
        $this->authorize('view', $productType);

        // Returns attribute groups assigned by default to this product type.
        // The full schema resolution is a cross-cutting concern; this provides the raw config.
        return response()->json([
            'data' => [
                'id' => $productType->id,
                'technical_name' => $productType->technical_name,
                'has_variants' => $productType->has_variants,
                'has_ean' => $productType->has_ean,
                'has_prices' => $productType->has_prices,
                'has_media' => $productType->has_media,
                'has_stock' => $productType->has_stock,
                'has_physical_dimensions' => $productType->has_physical_dimensions,
                'default_attribute_groups' => $productType->default_attribute_groups,
                'allowed_relation_types' => $productType->allowed_relation_types,
                'validation_rules' => $productType->validation_rules,
            ],
        ]);
    }
}
