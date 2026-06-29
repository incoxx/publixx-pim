<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product_widget')?->id ?? $this->route('product_widget');

        return [
            'technical_name' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('product_widgets', 'technical_name')->ignore($id),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'description' => 'nullable|string',
            'config' => 'sometimes|array',
            'config.fields' => 'required_with:config|array',
            'config.fields.*.role' => 'required|string',
            'config.fields.*.source' => 'required|string',
            'config.fields.*.type' => 'required|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ];
    }
}
