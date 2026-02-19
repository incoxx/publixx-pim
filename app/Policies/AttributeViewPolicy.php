<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttributeView;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttributeViewPolicy
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
        return $user->hasPermissionTo('attribute-views.view');
    }

    public function view(User $user, AttributeView $attributeView): bool
    {
        return $user->hasPermissionTo('attribute-views.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attribute-views.create');
    }

    public function update(User $user, AttributeView $attributeView): bool
    {
        return $user->hasPermissionTo('attribute-views.edit');
    }

    public function delete(User $user, AttributeView $attributeView): bool
    {
        return $user->hasPermissionTo('attribute-views.delete');
    }
}
