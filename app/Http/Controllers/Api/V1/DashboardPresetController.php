<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\DashboardPreset;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardPresetController extends Controller
{
    /**
     * Alle Presets auflisten (für jeden User sichtbar).
     */
    public function index(): JsonResponse
    {
        $presets = DashboardPreset::with('creator:id,name')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $presets]);
    }

    /**
     * Neues Preset erstellen (nur Admin).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'payload' => 'required|array',
            'payload.widgets' => 'required|array',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            DashboardPreset::where('is_default', true)->update(['is_default' => false]);
        }

        $preset = DashboardPreset::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $preset->load('creator:id,name');

        return response()->json(['data' => $preset], 201);
    }

    /**
     * Preset aktualisieren (nur Admin).
     */
    public function update(Request $request, DashboardPreset $preset): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'payload' => 'sometimes|array',
            'payload.widgets' => 'required_with:payload|array',
        ]);

        $preset->update($validated);
        $preset->load('creator:id,name');

        return response()->json(['data' => $preset]);
    }

    /**
     * Preset löschen (nur Admin, nicht das Default).
     */
    public function destroy(Request $request, DashboardPreset $preset): JsonResponse
    {
        $this->authorizeAdmin($request);

        if ($preset->is_default) {
            return response()->json(['message' => 'Das Standard-Preset kann nicht gelöscht werden.'], 422);
        }

        $preset->delete();

        return response()->json(null, 204);
    }

    /**
     * Preset als Default markieren (nur Admin).
     */
    public function setDefault(Request $request, DashboardPreset $preset): JsonResponse
    {
        $this->authorizeAdmin($request);

        DashboardPreset::where('is_default', true)->update(['is_default' => false]);
        $preset->update(['is_default' => true]);

        return response()->json(['data' => $preset]);
    }

    /**
     * Preset für den aktuellen User aktivieren.
     * Kopiert den Payload in die user_preferences.
     */
    public function activate(Request $request, DashboardPreset $preset): JsonResponse
    {
        UserPreference::setPayload(
            $request->user()->id,
            'dashboard',
            $preset->payload,
        );

        return response()->json(['data' => $preset->payload]);
    }

    /**
     * Aktuelles Dashboard-Layout als neues Preset speichern (nur Admin).
     */
    public function saveFromCurrent(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $currentConfig = UserPreference::getPayload($request->user()->id, 'dashboard');
        if (!$currentConfig || empty($currentConfig['widgets'])) {
            return response()->json(['message' => 'Kein Dashboard-Layout vorhanden.'], 422);
        }

        $preset = DashboardPreset::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'payload' => $currentConfig,
            'created_by' => $request->user()->id,
        ]);

        $preset->load('creator:id,name');

        return response()->json(['data' => $preset], 201);
    }

    private function authorizeAdmin(Request $request): void
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Nur Administratoren können Dashboard-Presets verwalten.');
        }
    }
}
