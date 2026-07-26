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
     * Hinterlegt die ID der Produkt-Version, die zur gerade laufenden Änderung
     * gehört. Wird vom Audit-Hook / Listener am erzeugten Audit-Eintrag als
     * Referenz gespeichert. Bewusst am Request-Attribut-Bag abgelegt
     * (request-scoped, kein Leak zwischen Requests im Worker).
     */
    public static function setProductVersionId(?string $id): void
    {
        request()?->attributes->set('audit.product_version_id', $id);
    }

    public static function productVersionId(): ?string
    {
        return request()?->attributes->get('audit.product_version_id');
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
