<?php

declare(strict_types=1);

namespace App\Policies;

use App\Http\Traits\ChecksInstanceRestrictions;
use App\Models\MediaUsageType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaUsageTypePolicy
{
    use HandlesAuthorization, ChecksInstanceRestrictions;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('media-usage-types.view');
    }

    public function view(User $user, MediaUsageType $mediaUsageType): bool
    {
        return $user->hasPermissionTo('media-usage-types.view')
            && $this->checkInstanceAccess($user, $mediaUsageType, 'read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media-usage-types.create');
    }

    public function update(User $user, MediaUsageType $mediaUsageType): bool
    {
        return $user->hasPermissionTo('media-usage-types.edit')
            && $this->checkInstanceAccess($user, $mediaUsageType, 'write');
    }

    public function delete(User $user, MediaUsageType $mediaUsageType): bool
    {
        return $user->hasPermissionTo('media-usage-types.delete')
            && $this->checkInstanceAccess($user, $mediaUsageType, 'delete');
    }
}
