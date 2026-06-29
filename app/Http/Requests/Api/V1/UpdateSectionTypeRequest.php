<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('section_type')?->id ?? $this->route('section_type');

        return [
            'technical_name' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('section_types', 'technical_name')->ignore($id),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'icon' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'schema' => 'sometimes|array',
            'schema.fields' => 'required_with:schema|array',
            'schema.fields.*.key' => 'required|string',
            'schema.fields.*.type' => 'required|string',
            'is_repeatable' => 'sometimes|boolean',
            'is_nestable' => 'sometimes|boolean',
            'preview_component' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ];
    }
}
