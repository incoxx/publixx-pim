<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreComparisonOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => 'required|string|max:100',
            'symbol' => 'required|string|max:20',
            'description_de' => 'nullable|string|max:255',
        ];
    }
}
