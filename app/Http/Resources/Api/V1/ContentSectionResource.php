<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content_page_id' => $this->content_page_id,
            'section_type_id' => $this->section_type_id,
            'parent_section_id' => $this->parent_section_id,
            'sort_order' => $this->sort_order,
            'is_visible' => $this->is_visible,
            'values_json' => $this->values_json,
            'settings_json' => $this->settings_json,
            'section_type' => new SectionTypeResource($this->whenLoaded('sectionType')),
            'children' => ContentSectionResource::collection($this->whenLoaded('childSections')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
