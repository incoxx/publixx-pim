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
        ];
    }
}
