<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type_id' => 'required|uuid|exists:product_types,id',
            'sku' => 'required|string|max:100|unique:products,sku',
            'ean' => 'nullable|string|max:20',
            'name' => 'required|string|max:500',
            'status' => 'in:draft,active,inactive,discontinued',
            'master_hierarchy_node_id' => 'nullable|uuid|exists:hierarchy_nodes,id',
        ];
    }
}
