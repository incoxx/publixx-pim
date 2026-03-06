<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutputHierarchyProductAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hierarchy_node_id' => $this->hierarchy_node_id,
            'product_id' => $this->product_id,
            'sort_order' => $this->sort_order,
            'product' => new ProductResource($this->whenLoaded('product')),
            'hierarchy_node' => new HierarchyNodeResource($this->whenLoaded('hierarchyNode')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
