<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReorderCollectionItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'string|uuid',
        ];
    }
}
