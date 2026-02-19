<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeAttributeAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hierarchy_node_id' => $this->hierarchy_node_id,
            'attribute_id' => $this->attribute_id,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
            'collection_name' => $this->collection_name,
            'collection_sort' => $this->collection_sort,
            'attribute_sort' => $this->attribute_sort,
            'dont_inherit' => $this->dont_inherit,
            'access_hierarchy' => $this->access_hierarchy,
            'access_product' => $this->access_product,
            'access_variant' => $this->access_variant,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
