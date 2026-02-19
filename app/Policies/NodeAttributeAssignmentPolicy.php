<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NodeAttributeAssignmentPolicy
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

    public function view(User $user, HierarchyNodeAttributeAssignment $assignment): bool
    {
        return $user->hasPermissionTo('hierarchies.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hierarchies.edit');
    }

    public function update(User $user, HierarchyNodeAttributeAssignment $assignment): bool
    {
        return $user->hasPermissionTo('hierarchies.edit');
    }

    public function delete(User $user, HierarchyNodeAttributeAssignment $assignment): bool
    {
        return $user->hasPermissionTo('hierarchies.edit');
    }
}
