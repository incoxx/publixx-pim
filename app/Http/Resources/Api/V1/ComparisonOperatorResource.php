<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComparisonOperatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'technical_name' => $this->technical_name,
            'symbol' => $this->symbol,
            'description_de' => $this->description_de,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
