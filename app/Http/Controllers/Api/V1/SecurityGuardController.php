<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Services\Security\SecurityMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Konfiguration und Auswertung des SecurityMonitor (System → Guard).
 * Ausschliesslich fuer Administratoren.
 */
class SecurityGuardController extends Controller
{
    private const SETTING_GROUP = 'security_guard';

    private function assertAdmin(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRole('Admin')) {
            abort(403, 'Nur Administratoren duerfen den Security-Guard verwalten.');
        }
    }

    /**
     * GET /api/v1/admin/security-guard
     *
     * Liefert die aktuell wirksame Konfiguration (env/config-Defaults, ueberlagert
     * von in der GUI gespeicherten Overrides) plus schreibgeschuetzte Referenzdaten.
     */
    public function show(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $overrides = Setting::getPayload(self::SETTING_GROUP) ?? [];

        $effective = [
            'enabled' => (bool) ($overrides['enabled'] ?? config('security.enabled', true)),
            'block' => (bool) ($overrides['block'] ?? config('security.block', true)),
            'allowed_countries' => array_values((array) ($overrides['allowed_countries'] ?? config('security.geo.allowed_countries', []))),
            'blocked_countries' => array_values((array) ($overrides['blocked_countries'] ?? config('security.geo.blocked_countries', []))),
            'mail' => (bool) ($overrides['mail'] ?? config('security.alerts.mail', true)),
            'mail_min_severity' => (string) ($overrides['mail_min_severity'] ?? config('security.alerts.mail_min_severity', 'high')),
            'client_error_burst' => (int) ($overrides['client_error_burst'] ?? config('security.thresholds.client_error_burst', 25)),
        ];

        return response()->json([
            'data' => [
                'config' => $effective,
                // Schreibgeschuetzt (nur env/config): zur Anzeige in der GUI.
                'readonly' => [
                    'country_header' => config('security.geo.country_header'),
                    'ip_header' => config('security.ip_header'),
                    'window_seconds' => config('security.thresholds.window_seconds'),
                    'block_cooldown_seconds' => config('security.thresholds.block_cooldown_seconds'),
                    'bot_user_agent_patterns' => config('security.bots.user_agent_patterns'),
                    'sensitive_path_patterns' => config('security.sensitive_path_patterns'),
                ],
                'has_overrides' => $overrides !== [],
            ],
        ]);
    }

    /**
     * PUT /api/v1/admin/security-guard
     *
     * Speichert die in der GUI aenderbaren Felder als Override.
     */
    public function update(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'block' => ['sometimes', 'boolean'],
            'allowed_countries' => ['sometimes', 'array'],
            'allowed_countries.*' => ['string', 'size:2'],
            'blocked_countries' => ['sometimes', 'array'],
            'blocked_countries.*' => ['string', 'size:2'],
            'mail' => ['sometimes', 'boolean'],
            'mail_min_severity' => ['sometimes', 'in:low,medium,high'],
            'client_error_burst' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ]);

        // Laendercodes normalisieren (Grossbuchstaben).
        foreach (['allowed_countries', 'blocked_countries'] as $key) {
            if (isset($validated[$key])) {
                $validated[$key] = array_values(array_unique(array_map(
                    static fn ($c): string => strtoupper((string) $c),
                    $validated[$key],
                )));
            }
        }

        // Nur ueberschreibbare Felder speichern (Merge mit bestehendem Override).
        $current = Setting::getPayload(self::SETTING_GROUP) ?? [];
        $merged = array_merge($current, array_intersect_key($validated, array_flip(SecurityMonitor::OVERRIDABLE_KEYS)));

        Setting::setPayload(self::SETTING_GROUP, $merged);
        Cache::forget('security_guard_settings');

        return $this->show($request);
    }

    /**
     * GET /api/v1/admin/security-guard/events
     *
     * Aktuelle Security-Events (paginiert, filterbar nach Typ/Severity).
     */
    public function events(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);

        $events = SecurityEvent::query()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('severity'), fn ($q, $sev) => $q->where('severity', $sev))
            ->when($request->query('ip'), fn ($q, $ip) => $q->where('ip_address', $ip))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/security-guard/stats
     *
     * Aggregierte Kennzahlen der letzten 24 Stunden.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $since = now()->subDay();

        return response()->json([
            'data' => [
                'total_24h' => SecurityEvent::where('created_at', '>=', $since)->count(),
                'blocked_24h' => SecurityEvent::where('created_at', '>=', $since)->where('blocked', true)->count(),
                'by_type' => SecurityEvent::where('created_at', '>=', $since)
                    ->selectRaw('type, count(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'by_severity' => SecurityEvent::where('created_at', '>=', $since)
                    ->selectRaw('severity, count(*) as count')
                    ->groupBy('severity')
                    ->pluck('count', 'severity'),
            ],
        ]);
    }
}
