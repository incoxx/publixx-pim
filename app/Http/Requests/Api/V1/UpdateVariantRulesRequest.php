<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVariantRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rules' => 'required|array|min:1',
            'rules.*.attribute_id' => 'required|uuid|exists:attributes,id',
            'rules.*.inheritance_mode' => 'required|in:inherit,override',
        ];
    }
}
