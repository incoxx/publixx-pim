<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MediaMotif;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaMotifPolicy
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

    public function view(User $user, MediaMotif $mediaMotif): bool
    {
        return $user->hasPermissionTo('media.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media.create');
    }

    public function update(User $user, MediaMotif $mediaMotif): bool
    {
        return $user->hasPermissionTo('media.edit');
    }

    public function delete(User $user, MediaMotif $mediaMotif): bool
    {
        return $user->hasPermissionTo('media.delete');
    }
}
