<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExportPolicy
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
        return $user->hasPermissionTo('export.view');
    }

    public function execute(User $user): bool
    {
        return $user->hasPermissionTo('export.execute');
    }

    public function editMappings(User $user): bool
    {
        return $user->hasPermissionTo('export.mappings.edit');
    }

    public function managePublixxMappings(User $user): bool
    {
        return $user->hasAnyPermission([
            'publixx-mappings.view',
            'publixx-mappings.create',
            'publixx-mappings.edit',
            'publixx-mappings.delete',
        ]);
    }

    public function managePxfTemplates(User $user): bool
    {
        return $user->hasAnyPermission([
            'pxf-templates.view',
            'pxf-templates.create',
            'pxf-templates.edit',
            'pxf-templates.delete',
        ]);
    }
}
