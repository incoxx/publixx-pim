<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Zentraler Laufzeit-Schalter für das Änderungs-Auditing.
 *
 * Massenoperationen mit Auth-Kontext (Demo-Daten-Generierung, synchroner
 * Import) würden die audit_logs-Tabelle sonst pro Datensatz fluten. Solche
 * Pfade kapseln ihre Schreibvorgänge daher in {@see self::withoutAuditing()}.
 */
final class AuditContext
{
    private static bool $enabled = true;

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Führt den Callback aus, ohne dass verändernde Model-Operationen
     * protokolliert werden. Der vorherige Zustand wird anschließend
     * wiederhergestellt (reentrant-sicher, auch bei Exceptions).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }
}
