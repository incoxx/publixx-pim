<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Security Monitor (Angriffs-Erkennung)
|--------------------------------------------------------------------------
|
| Konfiguration des SecurityMonitor: erkennt verdaechtige Muster (Login-Bursts,
| Bot-Zugriffe auf sensible Pfade, 4xx-Enumeration, Geo-Anomalien), schreibt
| Security-Events ins Audit/Log und alarmiert Admins. Harte Signale koennen
| aktiv geblockt werden (403/429).
|
*/

return [

    'enabled' => (bool) env('SECURITY_MONITOR_ENABLED', true),

    // Aktives Blockieren harter Signale (403/429). false = nur erkennen & loggen.
    'block' => (bool) env('SECURITY_MONITOR_BLOCK', true),

    /*
    | Client-IP-Ermittlung. Hinter Cloudflare/Proxy liefert $request->ip() nur die
    | Proxy-IP; der echte Besucher steht dann in einem Header (CF-Connecting-IP).
    */
    'ip_header' => env('SECURITY_IP_HEADER', 'CF-Connecting-IP'),

    /*
    | Geo (Laender-Code aus dem Cloudflare-Header CF-IPCountry). Leere Listen =
    | keine Geo-Regel. allowed = Whitelist (alles andere verdaechtig),
    | blocked = Blacklist (nur diese Laender verdaechtig).
    */
    'geo' => [
        'country_header' => env('SECURITY_COUNTRY_HEADER', 'CF-IPCountry'),
        'allowed_countries' => array_values(array_filter(array_map(
            'trim',
            explode(',', strtoupper((string) env('SECURITY_ALLOWED_COUNTRIES', ''))),
        ))),
        'blocked_countries' => array_values(array_filter(array_map(
            'trim',
            explode(',', strtoupper((string) env('SECURITY_BLOCKED_COUNTRIES', ''))),
        ))),

        /*
        | MaxMind GeoLite2: IP→Land-Auflösung OHNE CDN/Proxy. Greift als Fallback,
        | wenn kein Länder-Header ankommt. Benötigt die lokale .mmdb-Datei
        | (kostenlos von MaxMind, siehe `php artisan pim:geoip-update`).
        */
        'maxmind' => [
            'enabled' => (bool) env('SECURITY_GEOIP_ENABLED', true),
            'database' => env('SECURITY_GEOIP_DB', storage_path('app/geoip/GeoLite2-Country.mmdb')),
            // Für den Download-Command (kostenloser MaxMind-Account → License Key).
            'license_key' => env('MAXMIND_LICENSE_KEY'),
            'edition' => env('MAXMIND_EDITION', 'GeoLite2-Country'),
        ],
    ],

    /*
    | Bot-Erkennung. Ein Treffer wird nur dann als Angriff gewertet, wenn der
    | Request UNauthentifiziert einen sensiblen Pfad trifft — legitime
    | Server-zu-Server-Integrationen (gueltiges Token) werden nie geblockt.
    */
    'bots' => [
        'user_agent_patterns' => [
            'curl', 'wget', 'python-requests', 'python-urllib', 'go-http-client',
            'java/', 'okhttp', 'libwww-perl', 'scrapy', 'httpclient', 'httpie',
            'zgrab', 'nikto', 'sqlmap', 'nmap', 'masscan', 'nuclei', 'dirbuster',
            'gobuster', 'wpscan', 'fuzz', 'bytespider', 'phantomjs',
        ],
        'flag_empty_user_agent' => true,
    ],

    /*
    | Sensible Pfade (Substring, ohne fuehrenden Slash). Der oeffentliche Katalog
    | (api/v1/catalog) und der Healthcheck sind bewusst NICHT enthalten.
    */
    'sensitive_path_patterns' => [
        'api/v1/auth', 'api/v1/users', 'api/v1/admin', 'api/v1/license',
        'api/v1/export', 'api/v1/import', 'api/v1/debug', 'api/v1/mcp',
        'api/v1/pql', 'api/v1/database',
    ],

    'thresholds' => [
        // 401/403/404 pro Client-IP innerhalb des Fensters, bevor geblockt wird.
        'client_error_burst' => (int) env('SECURITY_4XX_BURST', 25),
        'window_seconds' => (int) env('SECURITY_WINDOW_SECONDS', 60),
        'block_cooldown_seconds' => (int) env('SECURITY_BLOCK_COOLDOWN', 900),
    ],

    'alerts' => [
        'log_channel' => 'security',
        'mail' => (bool) env('SECURITY_ALERT_MAIL', true),
        // Ab welcher Severity per E-Mail alarmiert wird: low|medium|high.
        'mail_min_severity' => env('SECURITY_ALERT_MIN_SEVERITY', 'high'),
        // Rollen, deren aktive Nutzer benachrichtigt werden.
        'roles' => ['Admin', 'Sysadmin'],
        // Zusaetzliche feste Empfaenger (kommagetrennt).
        'extra_recipients' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SECURITY_ALERT_EMAILS', '')),
        ))),
        // Deduplizierung: gleiche (Typ+IP)-Alarme werden fuer N Sekunden nicht erneut gemailt.
        'mail_dedupe_seconds' => (int) env('SECURITY_ALERT_DEDUPE', 3600),
    ],
];
