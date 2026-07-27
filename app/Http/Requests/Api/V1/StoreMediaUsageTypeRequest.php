<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaUsageTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'required|string|max:100|unique:media_usage_types,technical_name',
            'name_de' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'sort_order' => 'integer',
            'allowed_extensions' => 'nullable|array',
            'allowed_extensions.*' => 'string|max:10',
            'restricted_display_mode' => 'sometimes|in:hidden,locked',
            'allowed_product_type_ids' => 'nullable|array',
            'allowed_product_type_ids.*' => 'uuid|exists:product_types,id',
        ];
    }
}
