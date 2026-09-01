<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional: wird im Controller aus name_de abgeleitet, wenn nicht gesetzt.
            'technical_name' => 'nullable|string|max:100|unique:tags,technical_name',
            'name_de' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'name_json.*' => 'nullable|string|max:255',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
