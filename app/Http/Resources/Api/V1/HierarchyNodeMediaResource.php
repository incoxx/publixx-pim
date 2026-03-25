<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HierarchyNodeMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hierarchy_node_id' => $this->hierarchy_node_id,
            'media_id' => $this->media_id,
            'media' => new MediaResource($this->whenLoaded('media')),
            'usage_type_id' => $this->usage_type_id,
            'usage_type' => new MediaUsageTypeResource($this->whenLoaded('usageType')),
            'sort_order' => $this->sort_order,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
