<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EcommerceAddressType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EcommerceAddressTypePolicy
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
        return $user->hasPermissionTo('ecommerce-address-types.view');
    }

    public function view(User $user, EcommerceAddressType $addressType): bool
    {
        return $user->hasPermissionTo('ecommerce-address-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('ecommerce-address-types.create');
    }

    public function update(User $user, EcommerceAddressType $addressType): bool
    {
        return $user->hasPermissionTo('ecommerce-address-types.edit');
    }

    public function delete(User $user, EcommerceAddressType $addressType): bool
    {
        return $user->hasPermissionTo('ecommerce-address-types.delete');
    }
}
