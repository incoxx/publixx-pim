<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateAttributeValuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'values' => 'required|array|min:1',
            'values.*.attribute_id' => 'required|uuid|exists:attributes,id',
            'values.*.value' => 'nullable',
            'values.*.value_selection_id' => 'nullable|uuid|exists:value_list_entries,id',
            'values.*.language' => 'nullable|string|max:5',
            'values.*.multiplied_index' => 'integer|min:0',
            'values.*.unit_id' => 'nullable|uuid|exists:units,id',
            'values.*.comparison_operator_id' => 'nullable|uuid|exists:comparison_operators,id',
        ];
    }
}
