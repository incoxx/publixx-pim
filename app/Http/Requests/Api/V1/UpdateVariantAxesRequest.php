<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVariantAxesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attribute_ids' => 'present|array',
            'attribute_ids.*' => 'string|uuid|distinct|exists:attributes,id',
        ];
    }
}
