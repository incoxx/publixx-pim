<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'language' => $this->language,
            'is_active' => (bool) $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->relationLoaded('permissions')
                        ? $role->permissions->pluck('name')->values()
                        : [],
                ]);
            }),
            'all_permissions' => $this->whenLoaded('roles', function () {
                return $this->getAllPermissions()->pluck('name')->unique()->values();
            }),
            'entity_restrictions' => $this->whenLoaded('roles', function () {
                $restrictions = [];
                foreach ($this->roles as $role) {
                    if ($role->relationLoaded('entityRestrictions')) {
                        foreach ($role->entityRestrictions as $restriction) {
                            $key = $restriction->restrictable_type . ':' . $restriction->restrictable_id;
                            if (! isset($restrictions[$key])) {
                                $restrictions[$key] = [
                                    'restrictable_type' => $restriction->restrictable_type,
                                    'restrictable_id' => $restriction->restrictable_id,
                                    'access_level' => $restriction->access_level,
                                ];
                            } else {
                                // Keep highest access level across roles
                                $hierarchy = ['read' => 1, 'write' => 2, 'delete' => 3];
                                if (($hierarchy[$restriction->access_level] ?? 0) > ($hierarchy[$restrictions[$key]['access_level']] ?? 0)) {
                                    $restrictions[$key]['access_level'] = $restriction->access_level;
                                }
                            }
                        }
                    }
                }

                return array_values($restrictions);
            }),
            'tab_permissions' => $this->whenLoaded('roles', function () {
                $hierarchy = ['hidden' => 0, 'read' => 1, 'write' => 2];

                // Collect per-role tab permissions
                // A role with NO entry for a tab → implicit 'write' (full access)
                $perRolePerms = [];
                foreach ($this->roles as $role) {
                    $rolePerms = [];
                    if ($role->relationLoaded('tabPermissions')) {
                        foreach ($role->tabPermissions as $tp) {
                            $rolePerms[$tp->tab_key] = $tp->access_level;
                        }
                    }
                    $perRolePerms[] = $rolePerms;
                }

                if (empty($perRolePerms)) {
                    return (object) [];
                }

                // Merge across roles: highest access wins
                // A role that has no entry for a tab grants 'write'
                $keyArrays = array_map('array_keys', $perRolePerms);
                $allTabKeys = array_unique(array_merge(...$keyArrays ?: [[]]));

                $merged = [];
                foreach ($allTabKeys as $tabKey) {
                    $maxLevel = 'hidden';
                    foreach ($perRolePerms as $rolePerms) {
                        $level = $rolePerms[$tabKey] ?? 'write';
                        if (($hierarchy[$level] ?? 0) > ($hierarchy[$maxLevel] ?? 0)) {
                            $maxLevel = $level;
                        }
                    }
                    // Only include non-default levels
                    if ($maxLevel !== 'write') {
                        $merged[$tabKey] = $maxLevel;
                    }
                }

                return $merged;
            }),
        ];
    }
}
