<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttributeFormattingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'rule_type' => 'sometimes|in:uppercase,lowercase,capitalize,regex,number_format',
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'config.pattern' => 'required_if:rule_type,regex|string|max:500',
            'config.replacement' => 'nullable|string|max:500',
            'config.case_insensitive' => 'nullable|boolean',
            'config.decimals' => 'nullable|integer|min:0|max:10',
            'config.decimal_separator' => 'nullable|string|max:5',
            'config.thousands_separator' => 'nullable|string|max:5',
            'config.prefix' => 'nullable|string|max:20',
            'config.suffix' => 'nullable|string|max:20',
        ];
    }
}
