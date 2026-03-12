<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\AccessLink;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccessLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $adminRoleId = Role::where('name', 'Admin')->value('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'uuid', 'exists:roles,id', "not_in:{$adminRoleId}"],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.not_in' => 'Die Admin-Rolle kann nicht für Zugangslinks verwendet werden.',
        ];
    }
}
