<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EcommerceCartType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EcommerceCartTypePolicy
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
        return $user->hasPermissionTo('ecommerce-cart-types.view');
    }

    public function view(User $user, EcommerceCartType $cartType): bool
    {
        return $user->hasPermissionTo('ecommerce-cart-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('ecommerce-cart-types.create');
    }

    public function update(User $user, EcommerceCartType $cartType): bool
    {
        return $user->hasPermissionTo('ecommerce-cart-types.edit');
    }

    public function delete(User $user, EcommerceCartType $cartType): bool
    {
        return $user->hasPermissionTo('ecommerce-cart-types.delete');
    }
}
