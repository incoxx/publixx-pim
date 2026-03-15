<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowPolicy
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
        return $user->hasPermissionTo('workflows.view');
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('workflows.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('workflows.create');
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('workflows.edit');
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->hasPermissionTo('workflows.delete');
    }
}
