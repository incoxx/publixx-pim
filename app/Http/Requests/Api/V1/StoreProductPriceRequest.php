<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price_type_id' => 'required|uuid|exists:price_types,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'valid_from' => 'required|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'country' => 'nullable|string|size:2',
            'scale_from' => 'nullable|integer|min:1',
            'scale_to' => 'nullable|integer|min:1',
        ];
    }
}
