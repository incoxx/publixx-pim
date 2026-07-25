<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Jobs\RecordSecurityEvent;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SecurityAlertNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Zentrale Angriffs-Erkennung: bewertet Requests anhand von Heuristiken,
 * schreibt Security-Events (DB + Log-Channel) und alarmiert Admins.
 *
 * Severity-Rangfolge: low < medium < high.
 */
class SecurityMonitor
{
    private const SEVERITY_RANK = ['low' => 1, 'medium' => 2, 'high' => 3];

    /** In der GUI ueberschreibbare Felder (Setting-Group 'security_guard'). */
    public const OVERRIDABLE_KEYS = [
        'enabled', 'block', 'allowed_countries', 'blocked_countries',
        'mail', 'mail_min_severity', 'client_error_burst',
    ];

    private ?array $overrides = null;

    /**
     * In der GUI gespeicherte Overrides (ueber env/config-Defaults gelegt).
     *
     * @return array<string, mixed>
     */
    private function overrides(): array
    {
        return $this->overrides ??= Cache::remember(
            'security_guard_settings',
            60,
            // Resilient: fehlt die settings-Tabelle (z.B. waehrend Migrationen), gelten die Defaults.
            static fn (): array => rescue(static fn () => Setting::getPayload('security_guard') ?? [], [], false),
        );
    }

    private function opt(string $key, mixed $default): mixed
    {
        $o = $this->overrides();

        return array_key_exists($key, $o) ? $o[$key] : $default;
    }

    public function enabled(): bool
    {
        return (bool) $this->opt('enabled', config('security.enabled', true));
    }

    public function blockingEnabled(): bool
    {
        return (bool) $this->opt('block', config('security.block', true));
    }

    /**
     * Echte Client-IP (hinter Cloudflare/Proxy aus dem konfigurierten Header).
     */
    public function clientIp(Request $request): ?string
    {
        $header = (string) config('security.ip_header', '');
        if ($header !== '' && $request->headers->has($header)) {
            // Header kann eine Liste sein — die erste (Client-)IP nehmen.
            $value = trim(explode(',', (string) $request->header($header))[0]);
            if ($value !== '') {
                return $value;
            }
        }

        return $request->ip();
    }

    /**
     * Zwei-Buchstaben-Laendercode aus dem Cloudflare-Header (oder null).
     */
    public function country(Request $request): ?string
    {
        $header = (string) config('security.geo.country_header', '');
        if ($header === '' || ! $request->headers->has($header)) {
            return null;
        }

        $code = strtoupper(trim((string) $request->header($header)));

        // Cloudflare liefert "XX" fuer unbekannt / "T1" fuer Tor.
        return preg_match('/^[A-Z0-9]{2}$/', $code) ? $code : null;
    }

    public function isBotUserAgent(?string $userAgent): bool
    {
        $ua = strtolower(trim((string) $userAgent));

        if ($ua === '') {
            return (bool) config('security.bots.flag_empty_user_agent', true);
        }

        foreach ((array) config('security.bots.user_agent_patterns', []) as $pattern) {
            if ($pattern !== '' && str_contains($ua, strtolower((string) $pattern))) {
                return true;
            }
        }

        return false;
    }

    public function isSensitivePath(Request $request): bool
    {
        $path = $request->path();
        foreach ((array) config('security.sensitive_path_patterns', []) as $pattern) {
            if ($pattern !== '' && str_contains($path, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bewertet, ob ein Land laut Konfiguration verdaechtig ist.
     */
    public function isCountrySuspicious(?string $country): bool
    {
        if ($country === null) {
            return false;
        }

        $blocked = (array) $this->opt('blocked_countries', config('security.geo.blocked_countries', []));
        if (in_array($country, $blocked, true)) {
            return true;
        }

        $allowed = (array) $this->opt('allowed_countries', config('security.geo.allowed_countries', []));

        return $allowed !== [] && ! in_array($country, $allowed, true);
    }

    /**
     * Erfasst ein Security-Event: persistiert (async), loggt und alarmiert.
     *
     * @param  array<string, mixed>  $meta
     */
    public function record(string $type, string $severity, Request $request, array $meta = [], bool $blocked = false): void
    {
        if (! $this->enabled()) {
            return;
        }

        $data = [
            'type' => $type,
            'severity' => $severity,
            'ip_address' => $this->clientIp($request),
            'country' => $this->country($request),
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'path' => Str::limit($request->path(), 255, ''),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'blocked' => $blocked,
            'metadata' => $meta,
            'created_at' => now(),
        ];

        RecordSecurityEvent::dispatch($data);

        Log::channel((string) config('security.alerts.log_channel', 'security'))
            ->warning("security.{$type}", $data);

        if ($this->shouldMail($severity)) {
            $this->notifyAdmins($data);
        }
    }

    private function shouldMail(string $severity): bool
    {
        if (! $this->opt('mail', config('security.alerts.mail', true))) {
            return false;
        }

        $min = (string) $this->opt('mail_min_severity', config('security.alerts.mail_min_severity', 'high'));

        return (self::SEVERITY_RANK[$severity] ?? 1) >= (self::SEVERITY_RANK[$min] ?? 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notifyAdmins(array $data): void
    {
        // Deduplizierung: gleiche (Typ+IP) nicht mehrfach im Fenster mailen.
        $dedupeKey = 'security_alert_sent:'.($data['type'] ?? '').':'.($data['ip_address'] ?? '');
        $dedupeSeconds = (int) config('security.alerts.mail_dedupe_seconds', 3600);
        if ($dedupeSeconds > 0 && ! Cache::add($dedupeKey, 1, $dedupeSeconds)) {
            return;
        }

        try {
            $roles = (array) config('security.alerts.roles', ['Admin']);
            $recipients = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
                ->where('is_active', true)
                ->whereNotNull('email')
                ->get();

            foreach ($recipients as $user) {
                $user->notify(new SecurityAlertNotification($data));
            }

            foreach ((array) config('security.alerts.extra_recipients', []) as $email) {
                Notification::route('mail', $email)->notify(new SecurityAlertNotification($data));
            }
        } catch (\Throwable $e) {
            Log::warning('SecurityMonitor: Alarm-Versand fehlgeschlagen.', ['error' => $e->getMessage()]);
        }
    }

    // ─── Burst-/Cooldown-Zaehler (4xx-Enumeration) ────────────────────────

    public function isTemporarilyBlocked(Request $request): bool
    {
        return Cache::has($this->blockKey($this->clientIp($request)));
    }

    /**
     * Zaehlt Client-Fehler (401/403/404) je IP; ueberschreitet der Zaehler die
     * Schwelle, wird die IP fuer die Cooldown-Dauer markiert (Scanner/Enumeration).
     */
    public function trackClientError(Request $request, int $status): void
    {
        if (! $this->enabled() || ! in_array($status, [401, 403, 404], true)) {
            return;
        }

        $ip = $this->clientIp($request);
        if ($ip === null) {
            return;
        }

        $window = (int) config('security.thresholds.window_seconds', 60);
        $threshold = (int) $this->opt('client_error_burst', config('security.thresholds.client_error_burst', 25));
        $counterKey = 'security_4xx:'.$ip;

        $count = (int) Cache::get($counterKey, 0) + 1;
        Cache::put($counterKey, $count, $window);

        if ($count === $threshold) {
            $cooldown = (int) config('security.thresholds.block_cooldown_seconds', 900);
            if ($this->blockingEnabled() && $cooldown > 0) {
                Cache::put($this->blockKey($ip), 1, $cooldown);
            }

            $this->record('enumeration_burst', 'high', $request, [
                'client_errors' => $count,
                'window_seconds' => $window,
            ], $this->blockingEnabled());
        }
    }

    private function blockKey(?string $ip): string
    {
        return 'security_blocked_ip:'.(string) $ip;
    }
}
