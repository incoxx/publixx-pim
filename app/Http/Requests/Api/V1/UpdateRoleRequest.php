<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id ?? $this->route('role');

        return [
            'name' => ['sometimes', 'string', 'max:255', "unique:roles,name,{$roleId}"],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
