<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_status_id' => $this->start_status_id,
            'end_status_id' => $this->end_status_id,
            'is_active' => $this->is_active,
            'start_status' => $this->whenLoaded('startStatus', fn () => new WorkflowStatusResource($this->startStatus)),
            'end_status' => $this->whenLoaded('endStatus', fn () => new WorkflowStatusResource($this->endStatus)),
            'statuses' => WorkflowStatusResource::collection($this->whenLoaded('statuses')),
            'transitions' => WorkflowTransitionResource::collection($this->whenLoaded('transitions')),
            'product_types' => ProductTypeResource::collection($this->whenLoaded('productTypes')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
