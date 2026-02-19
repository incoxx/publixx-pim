<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Export;

use Illuminate\Foundation\Http\FormRequest;

class PublixxWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'max:100'],
            'mapping_id' => ['sometimes', 'nullable', 'string', 'uuid'],
            'product_id' => ['sometimes', 'nullable', 'string', 'uuid'],
            'payload' => ['sometimes', 'array'],
        ];
    }
}
