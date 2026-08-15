<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ISO-639-1: genau zwei Kleinbuchstaben. Der Code landet als
            // target_lang im TMS und als `language` an den Attributwerten —
            // Tippfehler waeren dort nur schwer wieder herauszubekommen.
            'technical_name' => ['required', 'string', 'regex:/^[a-z]{2}$/', 'unique:languages,technical_name'],
            'name_de' => 'required|string|max:255',
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
