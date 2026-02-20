<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttributeType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttributeTypePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('attribute-types.view');
    }

    public function view(User $user, AttributeType $attributeType): bool
    {
        return $user->hasPermissionTo('attribute-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attribute-types.create');
    }

    public function update(User $user, AttributeType $attributeType): bool
    {
        return $user->hasPermissionTo('attribute-types.edit');
    }

    public function delete(User $user, AttributeType $attributeType): bool
    {
        return $user->hasPermissionTo('attribute-types.delete');
    }
}
