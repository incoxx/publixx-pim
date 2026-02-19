<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductAttributeValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'attribute_id' => $this->attribute_id,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
            'value_string' => $this->value_string,
            'value_number' => $this->value_number,
            'value_date' => $this->value_date,
            'value_flag' => $this->value_flag,
            'value_selection_id' => $this->value_selection_id,
            'value_list_entry' => new ValueListEntryResource($this->whenLoaded('valueListEntry')),
            'unit_id' => $this->unit_id,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'comparison_operator_id' => $this->comparison_operator_id,
            'language' => $this->language,
            'multiplied_index' => $this->multiplied_index,
            'is_inherited' => $this->is_inherited,
            'inherited_from_node_id' => $this->inherited_from_node_id,
            'inherited_from_product_id' => $this->inherited_from_product_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
