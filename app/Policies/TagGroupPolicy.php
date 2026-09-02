<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Gruppen teilen sich die Rechte der Tags: wer Tags pflegen darf, ordnet sie
 * auch in Gruppen. Ein eigener Rechte-Satz für eine reine Sortierhilfe wäre
 * zusätzliche Rollenpflege ohne Mehrwert.
 */
class TagGroupPolicy
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
        return $user->hasPermissionTo('tags.view');
    }

    public function view(User $user, TagGroup $tagGroup): bool
    {
        return $user->hasPermissionTo('tags.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tags.create');
    }

    public function update(User $user, TagGroup $tagGroup): bool
    {
        return $user->hasPermissionTo('tags.edit');
    }

    public function delete(User $user, TagGroup $tagGroup): bool
    {
        return $user->hasPermissionTo('tags.delete');
    }
}
