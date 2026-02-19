<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:51200', // 50 MB
            'title_de' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_de' => 'nullable|string',
            'description_en' => 'nullable|string',
            'alt_text_de' => 'nullable|string|max:255',
            'alt_text_en' => 'nullable|string|max:255',
            'width' => 'nullable|integer',
            'height' => 'nullable|integer',
        ];
    }
}
