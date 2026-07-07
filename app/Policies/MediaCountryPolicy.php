<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MediaCountry;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaCountryPolicy
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
        return $user->hasPermissionTo('media-countries.view');
    }

    public function view(User $user, MediaCountry $mediaCountry): bool
    {
        return $user->hasPermissionTo('media-countries.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media-countries.create');
    }

    public function update(User $user, MediaCountry $mediaCountry): bool
    {
        return $user->hasPermissionTo('media-countries.edit');
    }

    public function delete(User $user, MediaCountry $mediaCountry): bool
    {
        return $user->hasPermissionTo('media-countries.delete');
    }
}
