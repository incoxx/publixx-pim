<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductRelationType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RelationTypePolicy
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
        return $user->hasPermissionTo('relation-types.view');
    }

    public function view(User $user, ProductRelationType $relationType): bool
    {
        return $user->hasPermissionTo('relation-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('relation-types.create');
    }

    public function update(User $user, ProductRelationType $relationType): bool
    {
        return $user->hasPermissionTo('relation-types.edit');
    }

    public function delete(User $user, ProductRelationType $relationType): bool
    {
        return $user->hasPermissionTo('relation-types.delete');
    }
}
