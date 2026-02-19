<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HierarchyNodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hierarchy_id' => $this->hierarchy_id,
            'parent_node_id' => $this->parent_node_id,
            'name_de' => $this->name_de,
            'name_en' => $this->name_en,
            'name_json' => $this->name_json,
            'path' => $this->path,
            'depth' => $this->depth,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'children' => HierarchyNodeResource::collection($this->whenLoaded('children')),
            'parent' => new HierarchyNodeResource($this->whenLoaded('parent')),
            'attribute_assignments' => NodeAttributeAssignmentResource::collection($this->whenLoaded('attributeAssignments')),
            'attribute_values' => HierarchyNodeAttributeValueResource::collection($this->whenLoaded('attributeValues')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
