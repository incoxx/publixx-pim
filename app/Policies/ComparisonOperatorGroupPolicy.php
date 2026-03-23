<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ComparisonOperatorGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComparisonOperatorGroupPolicy
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
        return $user->hasPermissionTo('comparison-operator-groups.view');
    }

    public function view(User $user, ComparisonOperatorGroup $comparisonOperatorGroup): bool
    {
        return $user->hasPermissionTo('comparison-operator-groups.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('comparison-operator-groups.create');
    }

    public function update(User $user, ComparisonOperatorGroup $comparisonOperatorGroup): bool
    {
        return $user->hasPermissionTo('comparison-operator-groups.edit');
    }

    public function delete(User $user, ComparisonOperatorGroup $comparisonOperatorGroup): bool
    {
        return $user->hasPermissionTo('comparison-operator-groups.delete');
    }
}
