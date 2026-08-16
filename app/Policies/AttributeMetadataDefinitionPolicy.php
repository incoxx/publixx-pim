<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttributeMetadataDefinition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttributeMetadataDefinitionPolicy
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
        return $user->hasPermissionTo('attribute-metadata.view');
    }

    public function view(User $user, AttributeMetadataDefinition $attributeMetadataDefinition): bool
    {
        return $user->hasPermissionTo('attribute-metadata.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attribute-metadata.create');
    }

    public function update(User $user, AttributeMetadataDefinition $attributeMetadataDefinition): bool
    {
        return $user->hasPermissionTo('attribute-metadata.edit');
    }

    public function delete(User $user, AttributeMetadataDefinition $attributeMetadataDefinition): bool
    {
        return $user->hasPermissionTo('attribute-metadata.delete');
    }
}
