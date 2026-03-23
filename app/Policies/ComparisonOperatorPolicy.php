<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ComparisonOperator;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComparisonOperatorPolicy
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
        return $user->hasPermissionTo('comparison-operators.view');
    }

    public function view(User $user, ComparisonOperator $comparisonOperator): bool
    {
        return $user->hasPermissionTo('comparison-operators.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('comparison-operators.create');
    }

    public function update(User $user, ComparisonOperator $comparisonOperator): bool
    {
        return $user->hasPermissionTo('comparison-operators.edit');
    }

    public function delete(User $user, ComparisonOperator $comparisonOperator): bool
    {
        return $user->hasPermissionTo('comparison-operators.delete');
    }
}
