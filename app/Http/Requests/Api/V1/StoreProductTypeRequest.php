<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'required|string|max:100|unique:product_types,technical_name',
            'name_de' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'has_variants' => 'required|boolean',
            'has_ean' => 'required|boolean',
            'has_prices' => 'required|boolean',
            'has_media' => 'required|boolean',
            'has_stock' => 'required|boolean',
            'has_physical_dimensions' => 'required|boolean',
            'default_attribute_groups' => 'nullable|array',
            'allowed_relation_types' => 'nullable|array',
            'validation_rules' => 'nullable|array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
