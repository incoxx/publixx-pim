<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ImportJob;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ImportJobPolicy
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
        return $user->hasPermissionTo('imports.view');
    }

    public function view(User $user, ImportJob $importJob): bool
    {
        return $user->hasPermissionTo('imports.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('imports.create');
    }

    public function update(User $user, ImportJob $importJob): bool
    {
        return $user->hasPermissionTo('imports.execute');
    }

    public function delete(User $user, ImportJob $importJob): bool
    {
        return $user->hasPermissionTo('imports.delete');
    }
}
