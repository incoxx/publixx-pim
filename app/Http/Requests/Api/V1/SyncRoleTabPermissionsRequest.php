<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\RoleTabPermission;
use Illuminate\Foundation\Http\FormRequest;

class SyncRoleTabPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tabs' => ['present', 'array'],
            'tabs.*.tab_key' => ['required', 'string', 'in:' . implode(',', RoleTabPermission::TAB_KEYS)],
            'tabs.*.access_level' => ['required', 'in:hidden,read,write'],
        ];
    }

    /**
     * After validation, ensure no duplicate tab_keys.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tabs = $this->input('tabs', []);
            $keys = array_column($tabs, 'tab_key');
            if (count($keys) !== count(array_unique($keys))) {
                $validator->errors()->add('tabs', 'Duplicate tab_key entries are not allowed.');
            }
        });
    }
}
