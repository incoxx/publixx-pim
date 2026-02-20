<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitGroupPolicy
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
        return $user->hasPermissionTo('unit-groups.view');
    }

    public function view(User $user, UnitGroup $unitGroup): bool
    {
        return $user->hasPermissionTo('unit-groups.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('unit-groups.create');
    }

    public function update(User $user, UnitGroup $unitGroup): bool
    {
        return $user->hasPermissionTo('unit-groups.edit');
    }

    public function delete(User $user, UnitGroup $unitGroup): bool
    {
        return $user->hasPermissionTo('unit-groups.delete');
    }
}
