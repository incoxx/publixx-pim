<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('content_type')?->id ?? $this->route('content_type');

        return [
            'technical_name' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('content_types', 'technical_name')->ignore($id),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'allowed_section_types' => 'nullable|array',
            'allowed_section_types.*' => 'string',
            'default_sections' => 'nullable|array',
            'layout_hint' => 'nullable|string|max:50',
            'workflow_id' => 'nullable|string|exists:workflows,id',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ];
    }
}
