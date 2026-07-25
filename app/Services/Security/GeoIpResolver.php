<?php

declare(strict_types=1);

namespace App\Services\Security;

use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Log;

/**
 * Löst eine IP-Adresse über die lokale MaxMind-GeoLite2-Datenbank in einen
 * ISO-Ländercode auf. Funktioniert ohne CDN/Proxy.
 *
 * Defensiv: Fehlt das geoip2-Paket oder die .mmdb-Datei, wird null geliefert —
 * der SecurityMonitor fällt dann einfach auf die Header-Erkennung zurück.
 */
class GeoIpResolver
{
    private ?Reader $reader = null;

    private bool $attempted = false;

    public function available(): bool
    {
        return $this->reader() !== null;
    }

    public function databasePath(): string
    {
        return (string) config('security.geo.maxmind.database', '');
    }

    /**
     * ISO-3166 alpha-2 Ländercode für eine IP (oder null).
     */
    public function countryForIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $reader = $this->reader();
        if ($reader === null) {
            return null;
        }

        try {
            $code = $reader->country($ip)->country->isoCode;

            return $code ? strtoupper($code) : null;
        } catch (\Throwable) {
            // IP nicht in der DB (z.B. private/reservierte Adresse) → kein Land.
            return null;
        }
    }

    private function reader(): ?Reader
    {
        if ($this->attempted) {
            return $this->reader;
        }
        $this->attempted = true;

        if (! config('security.geo.maxmind.enabled', true)) {
            return null;
        }

        if (! class_exists(Reader::class)) {
            return null;
        }

        $path = $this->databasePath();
        if ($path === '' || ! is_file($path)) {
            return null;
        }

        try {
            $this->reader = new Reader($path);
        } catch (\Throwable $e) {
            Log::warning('GeoIpResolver: MaxMind-DB konnte nicht geladen werden.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            $this->reader = null;
        }

        return $this->reader;
    }
}
