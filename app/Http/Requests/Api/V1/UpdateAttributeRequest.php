<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('attributes', 'technical_name')->ignore($this->route('attribute')),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'description_de' => 'nullable|string',
            'description_en' => 'nullable|string',
            'data_type' => 'sometimes|in:String,Number,Float,Date,Flag,Selection,Dictionary,Collection',
            'attribute_type_id' => 'nullable|uuid|exists:attribute_types,id',
            'value_list_id' => 'nullable|uuid|exists:value_lists,id',
            'unit_group_id' => 'nullable|uuid|exists:unit_groups,id',
            'default_unit_id' => 'nullable|uuid|exists:units,id',
            'comparison_operator_group_id' => 'nullable|uuid|exists:comparison_operator_groups,id',
            'is_translatable' => 'boolean',
            'is_multipliable' => 'boolean',
            'max_multiplied' => 'nullable|integer|min:1',
            'max_pre_decimal' => 'nullable|integer|min:1',
            'max_post_decimal' => 'nullable|integer|min:0',
            'max_characters' => 'nullable|integer|min:1',
            'is_searchable' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_unique' => 'boolean',
            'is_country_specific' => 'boolean',
            'is_inheritable' => 'boolean',
            'is_variant_attribute' => 'boolean',
            'is_internal' => 'boolean',
            'parent_attribute_id' => 'nullable|uuid|exists:attributes,id',
            'position' => 'nullable|integer',
            'source_system' => 'nullable|string|max:50',
            'source_attribute_name' => 'nullable|string|max:255',
            'source_attribute_key' => 'nullable|string|max:255',
            'status' => 'in:active,inactive',
        ];
    }
}
