<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'technical_name' => $this->technical_name,
            'name_de' => $this->name_de,
            'name_en' => $this->name_en,
            'name_json' => $this->name_json,
            'description_de' => $this->description_de,
            'description_en' => $this->description_en,
            'data_type' => $this->data_type,
            'attribute_type_id' => $this->attribute_type_id,
            'value_list_id' => $this->value_list_id,
            'attribute_type' => new AttributeTypeResource($this->whenLoaded('attributeType')),
            'value_list' => new ValueListResource($this->whenLoaded('valueList')),
            'unit_group' => new UnitGroupResource($this->whenLoaded('unitGroup')),
            'default_unit' => new UnitResource($this->whenLoaded('defaultUnit')),
            'comparison_operator_group_id' => $this->comparison_operator_group_id,
            'is_translatable' => $this->is_translatable,
            'is_multipliable' => $this->is_multipliable,
            'max_multiplied' => $this->max_multiplied,
            'max_pre_decimal' => $this->max_pre_decimal,
            'max_post_decimal' => $this->max_post_decimal,
            'max_characters' => $this->max_characters,
            'is_searchable' => $this->is_searchable,
            'is_mandatory' => $this->is_mandatory,
            'is_unique' => $this->is_unique,
            'is_country_specific' => $this->is_country_specific,
            'is_inheritable' => $this->is_inheritable,
            'is_variant_attribute' => $this->is_variant_attribute,
            'is_internal' => $this->is_internal,
            'parent_attribute_id' => $this->parent_attribute_id,
            'children' => AttributeResource::collection($this->whenLoaded('children')),
            'position' => $this->position,
            'source_system' => $this->source_system,
            'source_attribute_name' => $this->source_attribute_name,
            'source_attribute_key' => $this->source_attribute_key,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
