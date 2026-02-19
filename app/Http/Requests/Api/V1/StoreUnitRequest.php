<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'required|string|max:100',
            'abbreviation' => 'required|string|max:20',
            'abbreviation_json' => 'nullable|array',
            'conversion_factor' => 'numeric|min:0',
            'is_base_unit' => 'required|boolean',
            'is_translatable' => 'required|boolean',
        ];
    }
}
