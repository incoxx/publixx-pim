<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Language;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LanguagePolicy
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
        return $user->hasPermissionTo('languages.view');
    }

    public function view(User $user, Language $language): bool
    {
        return $user->hasPermissionTo('languages.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('languages.create');
    }

    public function update(User $user, Language $language): bool
    {
        return $user->hasPermissionTo('languages.update');
    }

    public function delete(User $user, Language $language): bool
    {
        return $user->hasPermissionTo('languages.delete');
    }
}
