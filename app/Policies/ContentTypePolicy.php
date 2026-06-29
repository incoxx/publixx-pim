<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContentType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentTypePolicy
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
        return $user->hasPermissionTo('content-types.view');
    }

    public function view(User $user, ContentType $type): bool
    {
        return $user->hasPermissionTo('content-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('content-types.create');
    }

    public function update(User $user, ContentType $type): bool
    {
        return $user->hasPermissionTo('content-types.edit');
    }

    public function delete(User $user, ContentType $type): bool
    {
        return $user->hasPermissionTo('content-types.delete');
    }
}
