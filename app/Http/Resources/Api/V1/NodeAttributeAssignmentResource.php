<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeAttributeAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'hierarchy_node_id' => $this->hierarchy_node_id,
            'attribute_id' => $this->attribute_id,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
            'collection_name' => $this->collection_name,
            'collection_sort' => $this->collection_sort,
            'attribute_sort' => $this->attribute_sort,
            'dont_inherit' => $this->dont_inherit,
            'is_required' => $this->is_required,
            'access_hierarchy' => $this->access_hierarchy,
            'access_product' => $this->access_product,
            'access_variant' => $this->access_variant,
            'parent_assignment_id' => $this->parent_assignment_id,
            'is_facet' => $this->is_facet,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        // Include inheritance info when available (set by controller for ?inherited=true)
        if (isset($this->resource->is_inherited_assignment)) {
            $data['is_inherited'] = (bool) $this->resource->is_inherited_assignment;
            if ($this->resource->is_inherited_assignment && $this->relationLoaded('hierarchyNode')) {
                $data['inherited_from_node_name'] = $this->hierarchyNode->name_de ?? $this->hierarchyNode->name_en ?? null;
            }
        }

        return $data;
    }
}
