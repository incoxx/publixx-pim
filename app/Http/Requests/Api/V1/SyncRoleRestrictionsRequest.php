<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SyncRoleRestrictionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'restrictions' => ['present', 'array'],
            'restrictions.*.id' => ['required', 'uuid'],
            'restrictions.*.access_level' => ['required', 'in:read,write,delete'],
        ];
    }
}
