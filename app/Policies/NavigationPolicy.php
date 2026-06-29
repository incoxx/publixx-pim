<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Navigation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NavigationPolicy
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
        return $user->hasPermissionTo('navigation.view');
    }

    public function view(User $user, Navigation $navigation): bool
    {
        return $user->hasPermissionTo('navigation.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('navigation.create');
    }

    public function update(User $user, Navigation $navigation): bool
    {
        return $user->hasPermissionTo('navigation.edit');
    }

    public function delete(User $user, Navigation $navigation): bool
    {
        return $user->hasPermissionTo('navigation.delete');
    }
}
