<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CollectionType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CollectionTypePolicy
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
        return $user->hasPermissionTo('collection-types.view');
    }

    public function view(User $user, CollectionType $collectionType): bool
    {
        return $user->hasPermissionTo('collection-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('collection-types.create');
    }

    public function update(User $user, CollectionType $collectionType): bool
    {
        return $user->hasPermissionTo('collection-types.edit');
    }

    public function delete(User $user, CollectionType $collectionType): bool
    {
        return $user->hasPermissionTo('collection-types.delete');
    }
}
