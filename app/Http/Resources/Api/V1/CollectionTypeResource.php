<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionTypeResource extends JsonResource
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
            'default_attribute_groups' => $this->default_attribute_groups,
            'default_item_attribute_groups' => $this->default_item_attribute_groups,
            'default_render_template_id' => $this->default_render_template_id,
            'requires_organization' => $this->requires_organization,
            'requires_snapshot' => $this->requires_snapshot,
            'allowed_export_formats' => $this->allowed_export_formats,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
