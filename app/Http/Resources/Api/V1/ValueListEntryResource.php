<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValueListEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'value_list_id' => $this->value_list_id,
            'parent_entry_id' => $this->parent_entry_id,
            'technical_name' => $this->technical_name,
            'display_value_de' => $this->display_value_de,
            'display_value_en' => $this->display_value_en,
            'display_value_json' => $this->display_value_json,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
