<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentPagePolicy
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
        return $user->hasPermissionTo('content.view');
    }

    public function view(User $user, ContentPage $page): bool
    {
        return $user->hasPermissionTo('content.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('content.create');
    }

    public function update(User $user, ContentPage $page): bool
    {
        return $user->hasPermissionTo('content.edit');
    }

    public function delete(User $user, ContentPage $page): bool
    {
        return $user->hasPermissionTo('content.delete');
    }
}
