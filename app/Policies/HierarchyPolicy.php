<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Hierarchy;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HierarchyPolicy
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
        return $user->hasPermissionTo('hierarchies.view');
    }

    public function view(User $user, Hierarchy $hierarchy): bool
    {
        return $user->hasPermissionTo('hierarchies.view');
    }

    public function update(User $user, Hierarchy $hierarchy): bool
    {
        return $user->hasPermissionTo('hierarchies.edit');
    }

    public function createNode(User $user): bool
    {
        return $user->hasPermissionTo('hierarchy-nodes.create');
    }

    public function moveNode(User $user): bool
    {
        return $user->hasPermissionTo('hierarchy-nodes.move');
    }
}
