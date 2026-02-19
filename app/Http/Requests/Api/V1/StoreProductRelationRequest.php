<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_product_id' => 'required|uuid|exists:products,id',
            'relation_type_id' => 'required|uuid|exists:product_relation_types,id',
            'sort_order' => 'integer',
        ];
    }
}
