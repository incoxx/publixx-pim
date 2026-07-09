<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreCollectionItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'uuid|exists:products,id',
        ];
    }
}
