<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductWidget;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProductWidgetPolicy
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
        return $user->hasPermissionTo('product-widgets.view');
    }

    public function view(User $user, ProductWidget $widget): bool
    {
        return $user->hasPermissionTo('product-widgets.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('product-widgets.create');
    }

    public function update(User $user, ProductWidget $widget): bool
    {
        return $user->hasPermissionTo('product-widgets.edit');
    }

    public function delete(User $user, ProductWidget $widget): bool
    {
        return $user->hasPermissionTo('product-widgets.delete');
    }
}
