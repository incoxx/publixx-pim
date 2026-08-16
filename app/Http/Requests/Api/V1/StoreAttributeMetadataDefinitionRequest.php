<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\AttributeMetadataDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeMetadataDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'required|string|max:100|unique:attribute_metadata_definitions,technical_name',
            'name_de' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'value_type' => ['required', Rule::in(AttributeMetadataDefinition::VALUE_TYPES)],
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('value_type');
            $options = $this->input('options');

            if (in_array($type, AttributeMetadataDefinition::OPTION_TYPES, true) && empty($options)) {
                $validator->errors()->add(
                    'options',
                    'Für den Wert-Typ "' . $type . '" muss mindestens eine Auswahloption angegeben werden.'
                );
            }
        });
    }
}
