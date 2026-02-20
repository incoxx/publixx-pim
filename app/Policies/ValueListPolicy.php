<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ValueList;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValueListPolicy
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
        return $user->hasPermissionTo('value-lists.view');
    }

    public function view(User $user, ValueList $valueList): bool
    {
        return $user->hasPermissionTo('value-lists.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('value-lists.create');
    }

    public function update(User $user, ValueList $valueList): bool
    {
        return $user->hasPermissionTo('value-lists.edit');
    }

    public function delete(User $user, ValueList $valueList): bool
    {
        return $user->hasPermissionTo('value-lists.delete');
    }
}
