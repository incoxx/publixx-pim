<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductTypePolicy
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
        return $user->hasPermissionTo('product-types.view');
    }

    public function view(User $user, ProductType $productType): bool
    {
        return $user->hasPermissionTo('product-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('product-types.create');
    }

    public function update(User $user, ProductType $productType): bool
    {
        return $user->hasPermissionTo('product-types.edit');
    }

    public function delete(User $user, ProductType $productType): bool
    {
        return $user->hasPermissionTo('product-types.delete');
    }
}
