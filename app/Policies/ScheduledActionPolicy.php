<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScheduledActionPolicy
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
        return $user->hasPermissionTo('products.view');
    }

    public function view(User $user, ScheduledAction $action): bool
    {
        return $user->hasPermissionTo('products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('products.edit');
    }

    public function update(User $user, ScheduledAction $action): bool
    {
        return $user->hasPermissionTo('products.edit');
    }

    public function delete(User $user, ScheduledAction $action): bool
    {
        return $user->hasPermissionTo('products.edit');
    }
}
