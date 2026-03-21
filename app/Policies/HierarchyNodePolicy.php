<?php

declare(strict_types=1);

namespace App\Policies;

use App\Http\Traits\ChecksInstanceRestrictions;
use App\Models\HierarchyNode;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HierarchyNodePolicy
{
    use HandlesAuthorization, ChecksInstanceRestrictions;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('hierarchy-nodes.view');
    }

    public function view(User $user, HierarchyNode $hierarchyNode): bool
    {
        return $user->hasPermissionTo('hierarchy-nodes.view')
            && $this->checkNodeInstanceAccess($user, $hierarchyNode, 'read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('hierarchy-nodes.create');
    }

    public function update(User $user, HierarchyNode $hierarchyNode): bool
    {
        return $user->hasPermissionTo('hierarchy-nodes.edit')
            && $this->checkNodeInstanceAccess($user, $hierarchyNode, 'write');
    }

    public function delete(User $user, HierarchyNode $hierarchyNode): bool
    {
        return $user->hasPermissionTo('hierarchy-nodes.delete')
            && $this->checkNodeInstanceAccess($user, $hierarchyNode, 'delete');
    }
}
