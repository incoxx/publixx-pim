<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MediaLanguage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaLanguagePolicy
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
        return $user->hasPermissionTo('media-languages.view');
    }

    public function view(User $user, MediaLanguage $mediaLanguage): bool
    {
        return $user->hasPermissionTo('media-languages.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media-languages.create');
    }

    public function update(User $user, MediaLanguage $mediaLanguage): bool
    {
        return $user->hasPermissionTo('media-languages.edit');
    }

    public function delete(User $user, MediaLanguage $mediaLanguage): bool
    {
        return $user->hasPermissionTo('media-languages.delete');
    }
}
