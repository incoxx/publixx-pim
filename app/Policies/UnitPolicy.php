<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnitPolicy
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
        return $user->hasPermissionTo('units.view');
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('units.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('units.create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('units.edit');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->hasPermissionTo('units.delete');
    }
}
