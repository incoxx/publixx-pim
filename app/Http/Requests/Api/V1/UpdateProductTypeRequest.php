<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductTypeRequest extends FormRequest
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
                Rule::unique('product_types', 'technical_name')->ignore($this->route('product_type')),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'has_variants' => 'boolean',
            'has_ean' => 'boolean',
            'has_prices' => 'boolean',
            'has_media' => 'boolean',
            'has_stock' => 'boolean',
            'has_physical_dimensions' => 'boolean',
            'default_attribute_groups' => 'nullable|array',
            'allowed_relation_types' => 'nullable|array',
            'validation_rules' => 'nullable|array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
