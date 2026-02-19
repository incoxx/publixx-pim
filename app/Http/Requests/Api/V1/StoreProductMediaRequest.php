<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_id' => 'required|uuid|exists:media,id',
            'usage_type' => 'required|in:teaser,gallery,document,technical_drawing',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }
}
