<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content_type_id' => $this->content_type_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'is_currently_valid' => $this->isCurrentlyValid(),
            'seo_title_json' => $this->seo_title_json,
            'seo_description_json' => $this->seo_description_json,
            'seo_image_id' => $this->seo_image_id,
            'workflow_id' => $this->workflow_id,
            'current_workflow_status_id' => $this->current_workflow_status_id,
            'workflow_assignee_id' => $this->workflow_assignee_id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'content_type' => new ContentTypeResource($this->whenLoaded('contentType')),
            'sections' => ContentSectionResource::collection($this->whenLoaded('sections')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
