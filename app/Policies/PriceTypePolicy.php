<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PriceType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PriceTypePolicy
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
        return $user->hasPermissionTo('price-types.view');
    }

    public function view(User $user, PriceType $priceType): bool
    {
        return $user->hasPermissionTo('price-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('price-types.create');
    }

    public function update(User $user, PriceType $priceType): bool
    {
        return $user->hasPermissionTo('price-types.edit');
    }

    public function delete(User $user, PriceType $priceType): bool
    {
        return $user->hasPermissionTo('price-types.delete');
    }
}
