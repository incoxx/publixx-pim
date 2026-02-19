<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttributePolicy
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
        return $user->hasPermissionTo('attributes.view');
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $user->hasPermissionTo('attributes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attributes.create');
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $user->hasPermissionTo('attributes.edit');
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $user->hasPermissionTo('attributes.delete');
    }
}
