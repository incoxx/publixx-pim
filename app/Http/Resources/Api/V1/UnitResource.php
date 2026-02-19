<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_group_id' => $this->unit_group_id,
            'technical_name' => $this->technical_name,
            'abbreviation' => $this->abbreviation,
            'abbreviation_json' => $this->abbreviation_json,
            'conversion_factor' => $this->conversion_factor,
            'is_base_unit' => $this->is_base_unit,
            'is_translatable' => $this->is_translatable,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
