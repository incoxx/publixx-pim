<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNodeAttributeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection_name' => 'nullable|string|max:255',
            'collection_sort' => 'integer',
            'attribute_sort' => 'integer',
            'dont_inherit' => 'boolean',
            'access_hierarchy' => 'in:hidden,visible,editable',
            'access_product' => 'in:hidden,visible,editable',
            'access_variant' => 'in:hidden,visible,editable',
        ];
    }
}
