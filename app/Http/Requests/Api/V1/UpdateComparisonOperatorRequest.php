<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComparisonOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'sometimes|string|max:100',
            'symbol' => 'sometimes|string|max:20',
            'description_de' => 'nullable|string|max:255',
        ];
    }
}
