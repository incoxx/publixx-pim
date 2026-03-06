<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDictionaryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => 'nullable|string|max:100',
            'short_text_de' => 'sometimes|string|max:255',
            'short_text_en' => 'nullable|string|max:255',
            'long_text_de' => 'sometimes|string',
            'long_text_en' => 'nullable|string',
            'status' => 'in:active,inactive',
        ];
    }
}
