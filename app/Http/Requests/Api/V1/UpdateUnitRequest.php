<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'sometimes|string|max:100',
            'abbreviation' => 'sometimes|string|max:20',
            'abbreviation_json' => 'nullable|array',
            'conversion_factor' => 'numeric|min:0',
            'is_base_unit' => 'boolean',
            'is_translatable' => 'boolean',
        ];
    }
}
