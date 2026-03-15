<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowStatusPolicy
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
        return $user->hasPermissionTo('workflow-statuses.view');
    }

    public function view(User $user, WorkflowStatus $workflowStatus): bool
    {
        return $user->hasPermissionTo('workflow-statuses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('workflow-statuses.create');
    }

    public function update(User $user, WorkflowStatus $workflowStatus): bool
    {
        return $user->hasPermissionTo('workflow-statuses.edit');
    }

    public function delete(User $user, WorkflowStatus $workflowStatus): bool
    {
        return $user->hasPermissionTo('workflow-statuses.delete');
    }
}
