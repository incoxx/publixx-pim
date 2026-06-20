<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function show(Request $request, string $group): JsonResponse
    {
        $payload = UserPreference::getPayload($request->user()->id, $group);

        return response()->json(['data' => $payload]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        if ($group === 'dashboard') {
            return $this->updateDashboard($request);
        }

        if ($group === 'quick_links') {
            return $this->updateQuickLinks($request);
        }

        if ($group === 'footer_presets') {
            return $this->updateFooterPresets($request);
        }

        if ($group === 'view_mode') {
            return $this->updateViewMode($request);
        }

        $request->validate([
            'preset' => 'sometimes|string|in:light,dark-navy,dark-charcoal,custom',
            'sidebar_bg' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_text' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_icon' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_active_bg' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_active_text' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sidebar_font_size' => 'sometimes|nullable|integer|min:11|max:18',
            'sidebar_colored_icons' => 'sometimes|boolean',
            'toolbar_bg' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'toolbar_text' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'toolbar_font_size' => 'sometimes|nullable|integer|min:11|max:18',
        ]);

        $pref = UserPreference::setPayload(
            $request->user()->id,
            $group,
            $request->only([
                'preset',
                'sidebar_bg', 'sidebar_text', 'sidebar_icon',
                'sidebar_active_bg', 'sidebar_active_text', 'sidebar_font_size',
                'sidebar_colored_icons',
                'toolbar_bg', 'toolbar_text', 'toolbar_font_size',
            ]),
        );

        return response()->json(['data' => $pref->payload]);
    }

    /**
     * Footer-Preset-Slots speichern (P1–P5, Route-Schnellzugriffe).
     * Leere Slots werden als null uebertragen — Position bleibt erhalten.
     */
    private function updateFooterPresets(Request $request): JsonResponse
    {
        $request->validate([
            'slots' => 'required|array|max:5',
            'slots.*' => 'nullable|array',
            'slots.*.path' => 'sometimes|string|max:500',
            'slots.*.label' => 'sometimes|string|max:100',
        ]);

        $pref = UserPreference::setPayload(
            $request->user()->id,
            'footer_presets',
            ['slots' => $request->input('slots')],
        );

        return response()->json(['data' => $pref->payload]);
    }

    /**
     * Ansichtsmodus speichern (Cockpit vs. klassisches Menü-GUI).
     * Diese persönliche Wahl schlägt den Rollen-Standard. mode=null folgt wieder der Rolle.
     */
    private function updateViewMode(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => 'present|nullable|string|in:cockpit,gui',
        ]);

        $pref = UserPreference::setPayload(
            $request->user()->id,
            'view_mode',
            ['mode' => $request->input('mode')],
        );

        return response()->json(['data' => $pref->payload]);
    }

    /**
     * Schnellzugriff-Links speichern.
     */
    private function updateQuickLinks(Request $request): JsonResponse
    {
        $request->validate([
            'links' => 'required|array|max:20',
            'links.*.id' => 'required|string|max:100',
            'links.*.label' => 'required|string|max:100',
            'links.*.to' => 'required|string|max:255',
            'links.*.icon' => 'sometimes|nullable|string|max:50',
        ]);

        $pref = UserPreference::setPayload(
            $request->user()->id,
            'quick_links',
            ['links' => $request->input('links')],
        );

        return response()->json(['data' => $pref->payload]);
    }

    /**
     * Dashboard-Konfiguration speichern (Widget-Sichtbarkeit, Profilkarten).
     */
    private function updateDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|string|max:100',
            'widgets.*.visible' => 'required|boolean',
            'widgets.*.search_profile_id' => 'sometimes|nullable|string',
            'widgets.*.chart_type' => 'sometimes|nullable|string|in:gauge,number,trend,bar,donut',
            'widgets.*.metric' => 'sometimes|nullable|string',
            'widgets.*.color' => 'sometimes|nullable|string|max:20',
        ]);

        $pref = UserPreference::setPayload(
            $request->user()->id,
            'dashboard',
            ['widgets' => $request->input('widgets')],
        );

        return response()->json(['data' => $pref->payload]);
    }
}
