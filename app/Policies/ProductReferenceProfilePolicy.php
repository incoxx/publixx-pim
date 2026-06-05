<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductReferenceProfile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductReferenceProfilePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin') || $user->hasRole('Sysadmin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('reference-profiles.view');
    }

    public function view(User $user, ProductReferenceProfile $profile): bool
    {
        return $user->hasPermissionTo('reference-profiles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('reference-profiles.create');
    }

    public function update(User $user, ProductReferenceProfile $profile): bool
    {
        return $user->hasPermissionTo('reference-profiles.edit');
    }

    public function delete(User $user, ProductReferenceProfile $profile): bool
    {
        return $user->hasPermissionTo('reference-profiles.delete');
    }
}
