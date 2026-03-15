<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
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
        return $user->hasPermissionTo('teams.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('teams.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('teams.create');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('teams.edit');
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('teams.delete');
    }
}
