<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CockpitProfile;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rollenspezifische Cockpit-Layouts (Phase 4, Admin-Editor).
 *
 * Lesen des eigenen Layouts: jede:r authentifizierte Nutzer:in (mine()).
 * Lesen der Rollen-Layouts/Rollenliste: Berechtigung 'cockpit-layouts.view'.
 * Verwalten (anlegen/aktualisieren/löschen): Berechtigung 'cockpit-layouts.edit'.
 */
class CockpitProfileController extends Controller
{
    /** Erlaubte Zonen-Schlüssel im Layout. */
    private const ZONES = ['tiles', 'workplace', 'content', 'kpis'];

    /**
     * GET /cockpit-profiles/mine
     * Gespeichertes Layout der Primärrolle des aktuellen Nutzers (oder null).
     */
    public function mine(Request $request): JsonResponse
    {
        $role = $request->user()->roles->first();
        $layout = null;

        if ($role) {
            $profile = CockpitProfile::where('role_id', $role->id)->first();
            $layout = $profile?->layout;
        }

        return response()->json(['data' => $layout]);
    }

    /**
     * GET /cockpit-profiles/roles
     * Rollenliste für den Cockpit-Layout-Editor (entkoppelt von 'roles.view').
     * Liefert je Rolle, ob ein eigenes Layout hinterlegt ist.
     */
    public function roles(Request $request): JsonResponse
    {
        $this->ensureCanView($request);

        $customRoleIds = CockpitProfile::pluck('role_id')->all();

        $roles = Role::orderBy('name')->get(['id', 'name'])->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'has_custom_layout' => in_array($role->id, $customRoleIds, true),
        ]);

        return response()->json(['data' => $roles]);
    }

    /**
     * GET /cockpit-profiles/{role}
     * Gespeichertes Layout einer Rolle (Admin) — oder null, wenn nicht gepflegt.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        $this->ensureCanView($request);

        $profile = CockpitProfile::where('role_id', $role->id)->first();

        return response()->json(['data' => $profile?->layout]);
    }

    /**
     * PUT /cockpit-profiles/{role}
     * Layout einer Rolle anlegen/aktualisieren (Admin).
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validate([
            'tiles' => 'sometimes|array',
            'tiles.*' => 'string|max:100',
            'workplace' => 'sometimes|array',
            'workplace.*' => 'string|max:100',
            'content' => 'sometimes|array',
            'content.*' => 'string|max:100',
            'kpis' => 'sometimes|array',
            'kpis.*' => 'string|max:100',
        ]);

        $layout = [];
        foreach (self::ZONES as $zone) {
            $layout[$zone] = array_values($validated[$zone] ?? []);
        }

        $profile = CockpitProfile::updateOrCreate(
            ['role_id' => $role->id],
            ['layout' => $layout],
        );

        return response()->json(['data' => $profile->layout]);
    }

    /**
     * DELETE /cockpit-profiles/{role}
     * Layout einer Rolle zurücksetzen (Admin) → Code-Default greift wieder.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->ensureCanManage($request);

        CockpitProfile::where('role_id', $role->id)->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function ensureCanView(Request $request): void
    {
        abort_unless($request->user()?->can('cockpit-layouts.view'), Response::HTTP_FORBIDDEN);
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($request->user()?->can('cockpit-layouts.edit'), Response::HTTP_FORBIDDEN);
    }
}
