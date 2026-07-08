<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionTypeRequest extends FormRequest
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
                Rule::unique('collection_types', 'technical_name')->ignore($this->route('collection_type')),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'default_attribute_groups' => 'nullable|array',
            'default_item_attribute_groups' => 'nullable|array',
            'default_render_template_id' => 'nullable|uuid',
            'requires_organization' => 'boolean',
            'requires_snapshot' => 'boolean',
            'allowed_export_formats' => 'nullable|array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
