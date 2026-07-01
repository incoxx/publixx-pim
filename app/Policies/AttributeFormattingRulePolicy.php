<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttributeFormattingRule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttributeFormattingRulePolicy
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
        return $user->hasPermissionTo('attribute-formatting-rules.view');
    }

    public function view(User $user, AttributeFormattingRule $attributeFormattingRule): bool
    {
        return $user->hasPermissionTo('attribute-formatting-rules.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attribute-formatting-rules.create');
    }

    public function update(User $user, AttributeFormattingRule $attributeFormattingRule): bool
    {
        return $user->hasPermissionTo('attribute-formatting-rules.edit');
    }

    public function delete(User $user, AttributeFormattingRule $attributeFormattingRule): bool
    {
        return $user->hasPermissionTo('attribute-formatting-rules.delete');
    }
}
