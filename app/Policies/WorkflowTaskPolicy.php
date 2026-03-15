<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowTask;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowTaskPolicy
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

    public function view(User $user, WorkflowTask $task): bool
    {
        if ($user->hasPermissionTo('workflows.view')) {
            return true;
        }

        // Users can always view tasks assigned to them
        return $task->assigned_to === $user->id || $task->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('workflows.edit');
    }

    public function update(User $user, WorkflowTask $task): bool
    {
        if ($user->hasPermissionTo('workflows.edit')) {
            return true;
        }

        // Assignees can update their own tasks (e.g. change status)
        return $task->assigned_to === $user->id;
    }

    public function delete(User $user, WorkflowTask $task): bool
    {
        if ($user->hasPermissionTo('workflows.delete')) {
            return true;
        }

        // Creators can delete their own tasks
        return $task->created_by === $user->id;
    }
}
