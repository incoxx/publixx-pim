<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Export;

use Illuminate\Foundation\Http\FormRequest;

class PublixxPqlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pql' => ['required', 'string', 'max:5000'],
            'params' => ['sometimes', 'array'],
        ];
    }
}
