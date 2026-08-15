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

    /**
     * Die Sprachliste darf jeder angemeldete Benutzer lesen.
     *
     * Sie ist keine schuetzenswerte Information, aber die halbe Oberflaeche
     * haengt daran: der Produkteditor braucht sie fuer die Sprachumschaltung,
     * Suche und Export fuer ihre Filter. Haenge man sie an ein Recht, saehe
     * ein Redakteur ohne dieses Recht schlicht keine Sprachen mehr — und der
     * Bruch zwischen Konfiguration und Oberflaeche waere zurueck.
     *
     * Schreibend bleibt alles an Rechten haengen.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Language $language): bool
    {
        return true;
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
