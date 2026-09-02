<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technical_name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('tag_groups', 'technical_name')->ignore($this->route('tag_group')),
            ],
            'name_de' => 'sometimes|required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_json' => 'nullable|array',
            'name_json.*' => 'nullable|string|max:255',
            'sort_order' => 'integer',
        ];
    }
}
