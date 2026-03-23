<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttributeMapping;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttributeMappingPolicy
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
        return $user->hasPermissionTo('attribute-mappings.view');
    }

    public function view(User $user, AttributeMapping $attributeMapping): bool
    {
        return $user->hasPermissionTo('attribute-mappings.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attribute-mappings.create');
    }

    public function update(User $user, AttributeMapping $attributeMapping): bool
    {
        return $user->hasPermissionTo('attribute-mappings.edit');
    }

    public function delete(User $user, AttributeMapping $attributeMapping): bool
    {
        return $user->hasPermissionTo('attribute-mappings.delete');
    }
}
