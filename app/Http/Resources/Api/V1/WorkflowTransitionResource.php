<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowTransitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'from_status_id' => $this->from_status_id,
            'to_status_id' => $this->to_status_id,
            'duration_hours' => $this->duration_hours,
            'name' => $this->name,
            'from_status' => $this->whenLoaded('fromStatus', fn () => new WorkflowStatusResource($this->fromStatus)),
            'to_status' => $this->whenLoaded('toStatus', fn () => new WorkflowStatusResource($this->toStatus)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
