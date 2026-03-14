<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PriceRegion;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PriceRegionPolicy
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
        return $user->hasPermissionTo('price-regions.view');
    }

    public function view(User $user, PriceRegion $priceRegion): bool
    {
        return $user->hasPermissionTo('price-regions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('price-regions.create');
    }

    public function update(User $user, PriceRegion $priceRegion): bool
    {
        return $user->hasPermissionTo('price-regions.edit');
    }

    public function delete(User $user, PriceRegion $priceRegion): bool
    {
        return $user->hasPermissionTo('price-regions.delete');
    }
}
