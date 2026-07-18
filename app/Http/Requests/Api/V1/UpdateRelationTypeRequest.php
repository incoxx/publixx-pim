<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRelationTypeRequest extends FormRequest
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
                Rule::unique('product_relation_types', 'technical_name')->ignore($this->route('relation_type')),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'is_bidirectional' => 'boolean',
            'allowed_source_product_type_ids' => 'nullable|array',
            'allowed_source_product_type_ids.*' => 'uuid|exists:product_types,id',
            'allowed_target_product_type_ids' => 'nullable|array',
            'allowed_target_product_type_ids.*' => 'uuid|exists:product_types,id',
        ];
    }
}
