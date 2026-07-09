<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HierarchyAttributeAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hierarchy_id' => $this->hierarchy_id,
            'attribute_id' => $this->attribute_id,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
            'sort_order' => $this->sort_order,
            'scope' => $this->scope,
            'is_facet' => $this->is_facet,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
