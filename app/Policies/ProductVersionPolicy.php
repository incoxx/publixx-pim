<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductVersionPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('products.view');
    }

    public function view(User $user, ProductVersion $version): bool
    {
        return $user->hasPermissionTo('products.view');
    }

    public function create(User $user, Product $product): bool
    {
        return $user->hasPermissionTo('products.edit');
    }

    public function activate(User $user, ProductVersion $version): bool
    {
        return $user->hasPermissionTo('products.edit');
    }

    public function schedule(User $user, ProductVersion $version): bool
    {
        return $user->hasPermissionTo('products.edit');
    }

    public function revert(User $user, ProductVersion $version): bool
    {
        return $user->hasPermissionTo('products.edit');
    }
}
