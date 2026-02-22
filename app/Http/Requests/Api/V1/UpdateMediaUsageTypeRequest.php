<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaUsageTypeRequest extends FormRequest
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
                Rule::unique('media_usage_types', 'technical_name')->ignore($this->route('media_usage_type')),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'sort_order' => 'integer',
        ];
    }
}
