<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|string|max:100|unique:products,sku',
            'ean' => 'nullable|string|max:20',
            'name' => 'required|string|max:500',
            'status' => 'in:draft,active,inactive,discontinued',
        ];
    }
}
