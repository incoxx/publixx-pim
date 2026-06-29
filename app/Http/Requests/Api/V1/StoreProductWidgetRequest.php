<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'required|string|max:100|unique:product_widgets,technical_name',
            'name_de' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'description' => 'nullable|string',
            'config' => 'required|array',
            'config.fields' => 'required|array',
            'config.fields.*.role' => 'required|string',
            'config.fields.*.source' => 'required|string',
            'config.fields.*.type' => 'required|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ];
    }
}
