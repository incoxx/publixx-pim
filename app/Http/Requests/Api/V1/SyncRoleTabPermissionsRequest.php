<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\AttributeView;
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
            'tabs.*.tab_key' => ['required', 'string'],
            'tabs.*.access_level' => ['required', 'in:hidden,read,write'],
        ];
    }

    /**
     * Prüft nach der Basis-Validierung: keine doppelten tab_keys, und jeder tab_key ist
     * entweder ein bekannter fester Tab (TAB_KEYS) oder ein gültiger dynamischer
     * Attribut-Sicht-Tab (AttributeView::TAB_KEY_PREFIX . "{uuid}" einer Sicht mit
     * show_as_tab = true).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tabs = $this->input('tabs', []);
            $keys = array_column($tabs, 'tab_key');

            if (count($keys) !== count(array_unique($keys))) {
                $validator->errors()->add('tabs', 'Duplicate tab_key entries are not allowed.');
            }

            $dynamicIds = [];
            foreach ($keys as $key) {
                if (in_array($key, RoleTabPermission::TAB_KEYS, true)) {
                    continue;
                }
                if (str_starts_with($key, AttributeView::TAB_KEY_PREFIX)) {
                    $dynamicIds[] = substr($key, strlen(AttributeView::TAB_KEY_PREFIX));
                    continue;
                }
                $validator->errors()->add('tabs', "Unbekannter tab_key: {$key}");
            }

            if ($dynamicIds !== []) {
                $validCount = AttributeView::whereIn('id', $dynamicIds)
                    ->where('show_as_tab', true)
                    ->count();
                if ($validCount !== count(array_unique($dynamicIds))) {
                    $validator->errors()->add('tabs', 'Ungültiger Attribut-Sicht-Tab (nicht gefunden oder nicht als Tab aktiviert).');
                }
            }
        });
    }
}
