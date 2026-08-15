<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('language')?->id;

        return [
            'technical_name' => [
                'sometimes', 'string', 'regex:/^[a-z]{2}$/',
                Rule::unique('languages', 'technical_name')->ignore($id),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'technical_name.regex' => 'Der Sprachcode muss aus genau zwei Kleinbuchstaben bestehen (ISO 639-1), z. B. "fi".',
        ];
    }
}
