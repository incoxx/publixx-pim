<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MediaRenditionPreset;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaRenditionPresetPolicy
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
        return $user->hasPermissionTo('media.view');
    }

    public function view(User $user, MediaRenditionPreset $mediaRenditionPreset): bool
    {
        return $user->hasPermissionTo('media.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media.edit');
    }

    public function update(User $user, MediaRenditionPreset $mediaRenditionPreset): bool
    {
        return $user->hasPermissionTo('media.edit');
    }

    public function delete(User $user, MediaRenditionPreset $mediaRenditionPreset): bool
    {
        return $user->hasPermissionTo('media.delete');
    }
}
