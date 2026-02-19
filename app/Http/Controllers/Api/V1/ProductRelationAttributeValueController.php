<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateRelationAttributeValuesRequest;
use App\Http\Resources\Api\V1\ProductRelationAttributeValueResource;
use App\Models\ProductRelation;
use App\Models\ProductRelationAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductRelationAttributeValueController extends Controller
{
    /**
     * GET /product-relations/{product_relation}/attribute-values
     */
    public function index(Request $request, ProductRelation $productRelation): AnonymousResourceCollection
    {
        $this->authorize('view', $productRelation->sourceProduct);

        $query = $productRelation->attributeValues()
            ->with(['attribute', 'valueListEntry', 'unit']);

        if ($request->has('filter')) {
            $this->applyFilters($query, $request->query('filter'));
        }

        return ProductRelationAttributeValueResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    /**
     * PUT /product-relations/{product_relation}/attribute-values
     *
     * Bulk upsert attribute values for a product relation edge.
     */
    public function bulkUpdate(BulkUpdateRelationAttributeValuesRequest $request, ProductRelation $productRelation): AnonymousResourceCollection
    {
        $this->authorize('update', $productRelation->sourceProduct);

        $values = $request->validated()['values'];

        foreach ($values as $entry) {
            $key = [
                'product_relation_id' => $productRelation->id,
                'attribute_id' => $entry['attribute_id'],
                'language' => $entry['language'] ?? null,
                'multiplied_index' => $entry['multiplied_index'] ?? 0,
            ];

            $data = array_filter([
                'value_string' => $entry['value_string'] ?? null,
                'value_number' => $entry['value_number'] ?? null,
                'value_date' => $entry['value_date'] ?? null,
                'value_flag' => $entry['value_flag'] ?? null,
                'value_selection_id' => $entry['value_selection_id'] ?? null,
                'unit_id' => $entry['unit_id'] ?? null,
            ], fn ($v) => $v !== null);

            ProductRelationAttributeValue::updateOrCreate($key, $data);
        }

        return ProductRelationAttributeValueResource::collection(
            $productRelation->attributeValues()
                ->with(['attribute', 'valueListEntry', 'unit'])
                ->get()
        );
    }

    /**
     * DELETE /product-relation-attribute-values/{product_relation_attribute_value}
     */
    public function destroy(ProductRelationAttributeValue $productRelationAttributeValue): JsonResponse
    {
        $this->authorize('update', $productRelationAttributeValue->productRelation->sourceProduct);

        $productRelationAttributeValue->delete();

        return response()->json(null, 204);
    }
}
