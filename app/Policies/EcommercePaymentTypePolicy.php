<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EcommercePaymentType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EcommercePaymentTypePolicy
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
        return $user->hasPermissionTo('ecommerce-payment-types.view');
    }

    public function view(User $user, EcommercePaymentType $paymentType): bool
    {
        return $user->hasPermissionTo('ecommerce-payment-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('ecommerce-payment-types.create');
    }

    public function update(User $user, EcommercePaymentType $paymentType): bool
    {
        return $user->hasPermissionTo('ecommerce-payment-types.edit');
    }

    public function delete(User $user, EcommercePaymentType $paymentType): bool
    {
        return $user->hasPermissionTo('ecommerce-payment-types.delete');
    }
}
