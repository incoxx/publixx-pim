<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Language;
use App\Rules\ValidFallbackLanguage;
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
        // Der Route-Parameter ist normalerweise das gebundene Model. Faellt die
        // Bindung aus, kaeme hier ein String an — dann waere ignore() wirkungslos
        // und JEDE Bearbeitung scheiterte an "technical name already taken".
        $bound = $this->route('language');
        $language = $bound instanceof Language ? $bound : Language::find($bound);

        return [
            'technical_name' => [
                'sometimes', 'string', 'regex:/^[a-z]{2}$/',
                Rule::unique('languages', 'technical_name')->ignore($language?->getKey()),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'fallback_language' => ['nullable', 'string', 'size:2', new ValidFallbackLanguage($language?->technical_name)],
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
