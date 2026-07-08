<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'nullable|uuid|exists:products,id',
            'position' => 'sometimes|integer|min:0',
            'quantity' => 'sometimes|numeric|min:0',
            'unit_id' => 'nullable|uuid|exists:units,id',
        ];
    }
}
