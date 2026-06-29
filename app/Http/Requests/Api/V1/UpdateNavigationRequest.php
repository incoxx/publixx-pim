<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNavigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('navigation')?->id ?? $this->route('navigation');

        return [
            'technical_name' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('navigations', 'technical_name')->ignore($id),
            ],
            'name_de' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'locale_set' => 'nullable|array',
            'locale_set.*' => 'string|max:5',
            'theme_json' => 'nullable|array',
            'access_mode' => 'sometimes|in:public,login',
            'is_primary' => 'sometimes|boolean',
        ];
    }
}
