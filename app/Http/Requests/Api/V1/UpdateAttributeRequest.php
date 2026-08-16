<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Attribute;
use App\Models\AttributeFormattingRule;
use App\Services\Attributes\AttributeMetadataService;
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
            'data_type' => ['sometimes', Rule::in(Attribute::DATA_TYPES)],
            'delimiter' => 'nullable|string|max:10',
            'textarea_rows' => 'nullable|integer|min:1|max:50',
            'textarea_cols' => 'nullable|integer|min:10|max:200',
            'simple_options' => 'nullable|array',
            'simple_options.*' => 'string|max:255',
            'attribute_type_id' => 'nullable|uuid|exists:attribute_types,id',
            'value_list_id' => 'nullable|uuid|exists:value_lists,id',
            'formatting_rule_id' => 'nullable|uuid|exists:attribute_formatting_rules,id',
            'unit_group_id' => 'nullable|uuid|exists:unit_groups,id',
            'default_unit_id' => 'nullable|uuid|exists:units,id',
            'comparison_operator_group_id' => 'nullable|uuid|exists:comparison_operator_groups,id',
            'is_translatable' => 'boolean',
            'is_multipliable' => 'boolean',
            'max_multiplied' => 'nullable|integer|min:1',
            'max_pre_decimal' => 'nullable|integer|min:1',
            'max_post_decimal' => 'nullable|integer|min:0',
            'max_characters' => 'nullable|integer|min:1',
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
            'is_searchable' => 'boolean',
            'is_mandatory' => 'boolean',
            'is_unique' => 'boolean',
            'is_country_specific' => 'boolean',
            'is_inheritable' => 'boolean',
            'is_variant_attribute' => 'boolean',
            'is_internal' => 'boolean',
            'is_readonly' => 'boolean',
            'is_hidden' => 'boolean',
            'is_quick_search' => 'boolean',
            'is_primary' => 'boolean',
            'parent_attribute_id' => 'nullable|uuid|exists:attributes,id',
            'composite_format' => 'nullable|string|max:500',
            'composite_expression' => 'nullable|string|max:500',
            'position' => 'nullable|integer',
            'source_system' => 'nullable|string|max:50',
            'source_attribute_name' => 'nullable|string|max:255',
            'source_attribute_key' => 'nullable|string|max:255',
            'status' => 'in:active,inactive',
            // Metadaten als Map technical_name => Wert; Detailprüfung in withValidator()
            'metadata' => 'sometimes|nullable|array',
        ];
    }

    /**
     * Formatierungsregeln für Case/Regex sind nur für String-Attribute sinnvoll,
     * Zahlenformatierung nur für Number/Float.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (is_array($this->input('metadata'))) {
                app(AttributeMetadataService::class)->validate($this->input('metadata'), $validator);
            }
        });

        $validator->after(function ($validator) {
            if (!$this->has('formatting_rule_id') || empty($this->input('formatting_rule_id'))) {
                return;
            }

            $rule = AttributeFormattingRule::find($this->input('formatting_rule_id'));
            if (!$rule) {
                return;
            }

            $attribute = $this->route('attribute');
            $dataType = $this->input('data_type', $attribute?->data_type);
            $isNumberRule = $rule->rule_type === 'number_format';
            $isNumberAttribute = in_array($dataType, ['Number', 'Float'], true);

            if ($isNumberRule && !$isNumberAttribute) {
                $validator->errors()->add('formatting_rule_id', 'Zahlenformatierungs-Regeln können nur Number/Float-Attributen zugeordnet werden.');
            } elseif (!$isNumberRule && $dataType !== 'String') {
                $validator->errors()->add('formatting_rule_id', 'Diese Formatierungsregel kann nur String-Attributen zugeordnet werden.');
            }
        });
    }
}
