<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaUsageTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'technical_name' => $this->technical_name,
            'name_de' => $this->name_de,
            'name_en' => $this->name_en,
            'name_json' => $this->name_json,
            'sort_order' => $this->sort_order,
            'allowed_extensions' => $this->allowed_extensions,
            'default_attributes' => $this->whenLoaded('defaultAttributes', function () {
                return $this->defaultAttributes->map(fn ($attr) => [
                    'id' => $attr->id,
                    'technical_name' => $attr->technical_name,
                    'name_de' => $attr->name_de,
                    'name_en' => $attr->name_en,
                    'data_type' => $attr->data_type,
                    'sort_order' => $attr->pivot->sort_order ?? 0,
                ]);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
