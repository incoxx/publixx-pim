<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductRelationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_product_id' => $this->source_product_id,
            'target_product_id' => $this->target_product_id,
            'relation_type_id' => $this->relation_type_id,
            'relation_type' => new RelationTypeResource($this->whenLoaded('relationType')),
            'target_product' => new ProductResource($this->whenLoaded('targetProduct')),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
