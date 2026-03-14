<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'string', 'max:10',
                Rule::unique('price_regions', 'code')->ignore($this->route('price_region')),
            ],
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:country,vkorg',
            'metadata' => 'nullable|array',
        ];
    }
}
