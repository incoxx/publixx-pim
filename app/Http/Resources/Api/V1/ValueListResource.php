<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValueListResource extends JsonResource
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
            'value_data_type' => $this->value_data_type,
            'max_depth' => $this->max_depth,
            'entries' => ValueListEntryResource::collection($this->whenLoaded('entries')),
            'attributes' => AttributeResource::collection($this->whenLoaded('attributes')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
