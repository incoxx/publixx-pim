<?php

declare(strict_types=1);

namespace App\Policies;

use App\Http\Traits\ChecksInstanceRestrictions;
use App\Models\AttributeType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttributeTypePolicy
{
    use HandlesAuthorization, ChecksInstanceRestrictions;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return null;
    }

    /**
     * Analog zu AttributeViewPolicy::viewAny() — 'products.view' reicht ebenfalls zum
     * Auflisten, da Attributgruppen auch als Filteroption im Produkteditor geladen werden.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('attribute-types.view')
            || $user->hasPermissionTo('products.view');
    }

    public function view(User $user, AttributeType $attributeType): bool
    {
        return $user->hasPermissionTo('attribute-types.view')
            && $this->checkInstanceAccess($user, $attributeType, 'read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attribute-types.create');
    }

    public function update(User $user, AttributeType $attributeType): bool
    {
        return $user->hasPermissionTo('attribute-types.edit')
            && $this->checkInstanceAccess($user, $attributeType, 'write');
    }

    public function delete(User $user, AttributeType $attributeType): bool
    {
        return $user->hasPermissionTo('attribute-types.delete')
            && $this->checkInstanceAccess($user, $attributeType, 'delete');
    }
}
