<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EcommerceOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EcommerceOrderPolicy
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
        return $user->hasPermissionTo('ecommerce-orders.view');
    }

    public function view(User $user, EcommerceOrder $order): bool
    {
        return $user->hasPermissionTo('ecommerce-orders.view');
    }

    public function update(User $user, EcommerceOrder $order): bool
    {
        return $user->hasPermissionTo('ecommerce-orders.edit');
    }

    public function retry(User $user, EcommerceOrder $order): bool
    {
        return $user->hasPermissionTo('ecommerce-orders.edit');
    }
}
