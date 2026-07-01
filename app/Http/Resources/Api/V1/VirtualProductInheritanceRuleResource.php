<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VirtualProductInheritanceRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'virtual_product_id' => $this->virtual_product_id,
            'attribute_id' => $this->attribute_id,
            'attribute' => $this->whenLoaded('attribute', fn () => [
                'id' => $this->attribute->id,
                'technical_name' => $this->attribute->technical_name,
                'name_de' => $this->attribute->name_de ?? null,
            ]),
            'conflict_mode' => $this->conflict_mode,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
