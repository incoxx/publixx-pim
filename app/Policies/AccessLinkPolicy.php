<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccessLinkPolicy
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
        return $user->hasPermissionTo('access-links.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('access-links.manage');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('access-links.manage');
    }
}
