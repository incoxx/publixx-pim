<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:10|unique:price_regions,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:country,vkorg',
            'metadata' => 'nullable|array',
        ];
    }
}
