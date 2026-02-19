<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_type_id' => $this->product_type_id,
            'sku' => $this->sku,
            'ean' => $this->ean,
            'name' => $this->name,
            'status' => $this->status,
            'product_type_ref' => $this->product_type_ref,
            'parent_product_id' => $this->parent_product_id,
            'master_hierarchy_node_id' => $this->master_hierarchy_node_id,
            'product_type' => new ProductTypeResource($this->whenLoaded('productType')),
            'attribute_values' => ProductAttributeValueResource::collection($this->whenLoaded('attributeValues')),
            'variants' => ProductResource::collection($this->whenLoaded('variants')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'prices' => ProductPriceResource::collection($this->whenLoaded('prices')),
            'relations' => ProductRelationResource::collection($this->whenLoaded('relations')),
            'parent_product' => new ProductResource($this->whenLoaded('parentProduct')),
            'master_hierarchy_node' => new HierarchyNodeResource($this->whenLoaded('masterHierarchyNode')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
