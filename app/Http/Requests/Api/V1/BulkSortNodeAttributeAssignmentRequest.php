<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkSortNodeAttributeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|uuid|exists:hierarchy_node_attribute_assignments,id',
            'items.*.collection_sort' => 'integer',
            'items.*.attribute_sort' => 'integer',
        ];
    }
}
