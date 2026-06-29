<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'required|string|max:100|unique:section_types,technical_name',
            'name_de' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'icon' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'schema' => 'required|array',
            'schema.fields' => 'required|array',
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
