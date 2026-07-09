<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CollectionPolicy
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
        return $user->hasPermissionTo('collections.view');
    }

    public function view(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collections.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('collections.create');
    }

    public function update(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collections.edit');
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collections.delete');
    }
}
