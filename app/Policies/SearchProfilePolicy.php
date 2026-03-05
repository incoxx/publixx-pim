<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SearchProfile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SearchProfilePolicy
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
        return true;
    }

    public function view(User $user, SearchProfile $profile): bool
    {
        return $profile->user_id === $user->id || $profile->is_shared;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SearchProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    public function delete(User $user, SearchProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }
}
