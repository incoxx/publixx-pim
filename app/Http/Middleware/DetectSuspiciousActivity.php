<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\SecurityMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Angriffs-Erkennung auf API-Ebene (Audit: SecurityMonitor).
 *
 * Blockiert nur harte Signale und niemals authentifizierte Requests mit
 * gueltigem Token — legitime Server-zu-Server-Integrationen bleiben unberuehrt.
 */
class DetectSuspiciousActivity
{
    public function __construct(
        private readonly SecurityMonitor $monitor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->monitor->enabled()) {
            return $next($request);
        }

        // 0. IP im Cooldown (zuvor als Scanner erkannt)?
        if ($this->monitor->isTemporarilyBlocked($request)) {
            return $this->deny($request, 'enumeration_burst', 429,
                'Zu viele verdaechtige Anfragen. Bitte spaeter erneut versuchen.', alreadyRecorded: true);
        }

        // 1. Geo-Anomalie (gesperrtes / nicht erlaubtes Land).
        $country = $this->monitor->country($request);
        if ($this->monitor->isCountrySuspicious($country)) {
            $block = $this->monitor->blockingEnabled();
            $this->monitor->record('geo_blocked', 'high', $request, ['country' => $country], $block);
            if ($block) {
                return $this->deny($request, 'geo_blocked', 403,
                    'Zugriff aus Ihrer Region ist nicht gestattet.', alreadyRecorded: true);
            }
        }

        // 2. Bot-User-Agent auf sensiblem Pfad — nur UNauthentifiziert.
        //    Authentifizierte Integrationen (gueltiges Token) sind ausgenommen.
        if (! $request->user()
            && $this->monitor->isSensitivePath($request)
            && $this->monitor->isBotUserAgent($request->userAgent())
        ) {
            $block = $this->monitor->blockingEnabled();
            $this->monitor->record('bot_sensitive_path', 'high', $request, [
                'user_agent' => (string) $request->userAgent(),
            ], $block);
            if ($block) {
                return $this->deny($request, 'bot_sensitive_path', 403,
                    'Automatisierter Zugriff auf diesen Endpunkt ist nicht gestattet.', alreadyRecorded: true);
            }
        }

        $response = $next($request);

        // 3. 4xx-Enumeration nachzaehlen (blockt erst ab Schwelle den naechsten Request).
        $this->monitor->trackClientError($request, $response->getStatusCode());

        return $response;
    }

    private function deny(Request $request, string $type, int $status, string $message, bool $alreadyRecorded = false): Response
    {
        if (! $alreadyRecorded) {
            $this->monitor->record($type, 'high', $request, [], true);
        }

        return response()->json([
            'type' => "https://anypim.local/problems/security/{$type}",
            'title' => $status === 429 ? 'Too Many Requests' : 'Forbidden',
            'status' => $status,
            'detail' => $message,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
