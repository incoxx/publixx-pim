<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutputHierarchyProductAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'uuid',
                'exists:products,id',
                Rule::unique('output_hierarchy_product_assignments')
                    ->where('hierarchy_node_id', $this->route('hierarchy_node')->id),
            ],
            'sort_order' => 'sometimes|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.unique' => 'Dieses Produkt ist diesem Knoten bereits zugeordnet.',
        ];
    }
}
