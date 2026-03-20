<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ConnectorConnection;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConnectorConnectionPolicy
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
        return $user->hasPermissionTo('connectors.view');
    }

    public function view(User $user, ConnectorConnection $connection): bool
    {
        return $user->hasPermissionTo('connectors.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('connectors.manage');
    }

    public function update(User $user, ConnectorConnection $connection): bool
    {
        return $user->hasPermissionTo('connectors.manage');
    }

    public function delete(User $user, ConnectorConnection $connection): bool
    {
        return $user->hasPermissionTo('connectors.manage');
    }

    public function sync(User $user, ConnectorConnection $connection): bool
    {
        return $user->hasPermissionTo('connectors.sync');
    }
}
