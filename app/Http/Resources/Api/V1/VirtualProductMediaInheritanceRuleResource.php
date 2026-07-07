<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VirtualProductMediaInheritanceRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'virtual_product_id' => $this->virtual_product_id,
            'usage_type_id' => $this->usage_type_id,
            'usage_type' => $this->whenLoaded('usageType', fn () => [
                'id' => $this->usageType->id,
                'technical_name' => $this->usageType->technical_name,
                'name_de' => $this->usageType->name_de ?? null,
            ]),
            'conflict_mode' => $this->conflict_mode,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
