<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\BulkUpdateRelationAttributeValuesRequest;
use App\Http\Resources\Api\V1\ProductRelationAttributeValueResource;
use App\Http\Traits\AuditsChanges;
use App\Http\Traits\ChecksTabPermissions;
use App\Models\ProductRelation;
use App\Models\ProductRelationAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductRelationAttributeValueController extends Controller
{
    use AuditsChanges;
    use ChecksTabPermissions;
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
        $this->assertTabWriteAccess('relations');

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

        $this->audit('relation_attributes_updated', 'Product', $productRelation->source_product_id, null, [
            'relation_id' => $productRelation->id,
            'attribute_ids' => array_column($values, 'attribute_id'),
        ]);

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
        $this->assertTabWriteAccess('relations');

        $productId = $productRelationAttributeValue->productRelation->source_product_id;
        $snapshot = [
            'relation_id' => $productRelationAttributeValue->product_relation_id,
            'attribute_id' => $productRelationAttributeValue->attribute_id,
        ];

        $productRelationAttributeValue->delete();

        $this->audit('relation_attribute_removed', 'Product', $productId, $snapshot);

        return response()->json(null, 204);
    }
}
