<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_type_id' => 'required|string|exists:section_types,id',
            'parent_section_id' => 'nullable|string|exists:content_sections,id',
            'sort_order' => 'sometimes|integer',
            'is_visible' => 'sometimes|boolean',
            'values_json' => 'nullable|array',
            'settings_json' => 'nullable|array',
        ];
    }
}
