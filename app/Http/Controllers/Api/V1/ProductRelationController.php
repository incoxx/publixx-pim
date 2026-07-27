<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreProductRelationRequest;
use App\Http\Resources\Api\V1\ProductRelationResource;
use App\Http\Traits\AuditsChanges;
use App\Http\Traits\ChecksTabPermissions;
use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ProductRelationController extends Controller
{
    use AuditsChanges;
    use ChecksTabPermissions;
    /**
     * GET /products/{product}/relations
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('view', $product);

        $query = $product->relations()
            ->with([
                'relationType.defaultAttributes',
                'targetProduct',
                // valueListEntry/unit mitladen, damit die Metadaten-Spalten in der
                // Beziehungs-Tabelle Auswahllisten-Labels (statt leer) und Einheiten zeigen.
                'attributeValues.attribute',
                'attributeValues.valueListEntry',
                'attributeValues.unit',
            ])
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
        $this->assertTabWriteAccess('relations');

        $data = $request->validated();
        $data['source_product_id'] = $product->id;

        $relationType = ProductRelationType::findOrFail($data['relation_type_id']);
        $targetProduct = Product::findOrFail($data['target_product_id']);

        if (!$relationType->allowsSourceProductType($product->product_type_id)) {
            throw ValidationException::withMessages([
                'relation_type_id' => "Beziehungstyp \"{$relationType->name_de}\" ist für den Produkttyp des Quellprodukts nicht zulässig.",
            ]);
        }
        if (!$relationType->allowsTargetProductType($targetProduct->product_type_id)) {
            throw ValidationException::withMessages([
                'target_product_id' => "Beziehungstyp \"{$relationType->name_de}\" ist für den Produkttyp des Zielprodukts nicht zulässig.",
            ]);
        }

        $relation = ProductRelation::create($data);

        $this->audit('relation_added', 'Product', $product->id, null, [
            'relation_id' => $relation->id,
            'relation_type_id' => $data['relation_type_id'] ?? null,
            'target_product_id' => $data['target_product_id'] ?? null,
        ]);

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
        $this->assertTabWriteAccess('relations');

        $snapshot = [
            'relation_id' => $productRelation->id,
            'relation_type_id' => $productRelation->relation_type_id,
            'target_product_id' => $productRelation->target_product_id,
        ];
        $productId = $productRelation->source_product_id;

        $productRelation->delete();

        $this->audit('relation_removed', 'Product', $productId, $snapshot);

        return response()->json(null, 204);
    }
}
