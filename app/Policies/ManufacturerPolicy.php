<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ManufacturerPolicy
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
        return $user->hasPermissionTo('manufacturers.view');
    }

    public function view(User $user, Manufacturer $manufacturer): bool
    {
        return $user->hasPermissionTo('manufacturers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manufacturers.create');
    }

    public function update(User $user, Manufacturer $manufacturer): bool
    {
        return $user->hasPermissionTo('manufacturers.edit');
    }

    public function delete(User $user, Manufacturer $manufacturer): bool
    {
        return $user->hasPermissionTo('manufacturers.delete');
    }
}
