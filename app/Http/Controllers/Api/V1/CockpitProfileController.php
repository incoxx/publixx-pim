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
 * Verwalten der Rollen-Layouts: Berechtigung 'roles.edit'.
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
     * GET /cockpit-profiles
     * Alle gespeicherten Rollen-Layouts (Admin).
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureCanManage($request);

        $profiles = CockpitProfile::whereNotNull('role_id')->get()
            ->mapWithKeys(fn (CockpitProfile $p) => [$p->role_id => $p->layout]);

        return response()->json(['data' => $profiles]);
    }

    /**
     * GET /cockpit-profiles/{role}
     * Gespeichertes Layout einer Rolle (Admin) — oder null, wenn nicht gepflegt.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        $this->ensureCanManage($request);

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

    private function ensureCanManage(Request $request): void
    {
        abort_unless($request->user()?->can('roles.edit'), Response::HTTP_FORBIDDEN);
    }
}
