<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeMetadataDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'technical_name' => $this->technical_name,
            'name_de' => $this->name_de,
            'name_en' => $this->name_en,
            'description' => $this->description,
            'value_type' => $this->value_type,
            'options' => $this->options,
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
            'values_count' => $this->when(isset($this->values_count), $this->values_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
