<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttributeView;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttributeViewPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return null;
    }

    /**
     * Auflisten ist zusätzlich zur Admin-Berechtigung 'attribute-views.view' auch mit
     * 'products.view' erlaubt: jede produktbearbeitende Rolle muss die Liste lesen können,
     * um zu erkennen, welche Attribut-Sichten als eigener Produkteditor-Tab konfiguriert
     * sind (siehe RoleTabPermission-Tab-Key 'attribute-view:{uuid}') — unabhängig davon,
     * ob sie auch die Sichten selbst verwalten darf. Einzelzugriff/Erstellen/Bearbeiten/
     * Löschen bleiben exklusiv an die Admin-Berechtigungen gebunden.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('attribute-views.view')
            || $user->hasPermissionTo('products.view');
    }

    public function view(User $user, AttributeView $attributeView): bool
    {
        return $user->hasPermissionTo('attribute-views.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('attribute-views.create');
    }

    public function update(User $user, AttributeView $attributeView): bool
    {
        return $user->hasPermissionTo('attribute-views.edit');
    }

    public function delete(User $user, AttributeView $attributeView): bool
    {
        return $user->hasPermissionTo('attribute-views.delete');
    }
}
