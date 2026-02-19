<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductTypeResource extends JsonResource
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
            'has_variants' => $this->has_variants,
            'has_ean' => $this->has_ean,
            'has_prices' => $this->has_prices,
            'has_media' => $this->has_media,
            'has_stock' => $this->has_stock,
            'has_physical_dimensions' => $this->has_physical_dimensions,
            'default_attribute_groups' => $this->default_attribute_groups,
            'allowed_relation_types' => $this->allowed_relation_types,
            'validation_rules' => $this->validation_rules,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
