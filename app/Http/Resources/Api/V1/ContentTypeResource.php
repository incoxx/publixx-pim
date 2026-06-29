<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'technical_name' => $this->technical_name,
            'name_de' => $this->name_de,
            'name_en' => $this->name_en,
            'name_json' => $this->name_json,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'allowed_section_types' => $this->allowed_section_types,
            'default_sections' => $this->default_sections,
            'layout_hint' => $this->layout_hint,
            'workflow_id' => $this->workflow_id,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'pages_count' => $this->whenCounted('pages'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
