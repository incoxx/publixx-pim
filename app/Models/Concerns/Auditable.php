<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Jobs\WriteAuditLog;
use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Auditable – zentraler Model-Hook für das Änderungsprotokoll.
 *
 * Ein Model, das dieses Trait einbindet, schreibt bei jeder verändernden
 * Eloquent-Operation (created/updated/deleted) automatisch einen Eintrag in
 * die audit_logs-Tabelle – über denselben WriteAuditLog-Job wie die übrige
 * Audit-Infrastruktur. Die Einträge erscheinen im Änderungsprotokoll
 * (AuditLogController / JournalView).
 *
 * Grundsätze:
 * - Es wird nur protokolliert, wenn ein angemeldeter Benutzer die Änderung
 *   auslöst (GUI-/API-Bearbeitung). System-, Queue- und Seed-Läufe ohne
 *   Auth-Kontext werden bewusst NICHT protokolliert.
 * - Für gewollte Massenoperationen mit Auth-Kontext (Demo-Daten, synchroner
 *   Import) lässt sich das Auditing per {@see AuditContext::withoutAuditing()}
 *   temporär deaktivieren.
 * - created_at/updated_at werden nie protokolliert; weitere Felder lassen sich
 *   pro Model über die Eigenschaft $auditExclude ausschließen.
 */
trait Auditable
{
    /**
     * Registriert die Eloquent-Hooks. Wird von Laravel automatisch beim
     * Booten des Models aufgerufen (boot{TraitName}-Konvention).
     */
    public static function bootAuditable(): void
    {
        static::created(static fn (Model $model) => $model->recordAuditEntry('created'));
        static::updated(static fn (Model $model) => $model->recordAuditEntry('updated'));
        static::deleted(static fn (Model $model) => $model->recordAuditEntry('deleted'));
    }

    /**
     * Felder, die nie ins Audit geschrieben werden (zusätzlich zu den
     * Zeitstempeln). Pro Model über die Eigenschaft $auditExclude setzbar.
     *
     * @return array<int, string>
     */
    protected function auditExcluded(): array
    {
        /** @phpstan-ignore-next-line property.notFound (optionale Model-Eigenschaft) */
        return property_exists($this, 'auditExclude') ? $this->auditExclude : [];
    }

    protected function recordAuditEntry(string $action): void
    {
        if (! AuditContext::enabled()) {
            return;
        }

        // Nur benutzerausgelöste Änderungen protokollieren. Ohne angemeldeten
        // Nutzer (Queue-Worker, Konsole, Seeding) wird bewusst nicht geloggt,
        // damit Massen-/Systemoperationen die Tabelle nicht fluten.
        $request = request();
        $userId = $request?->user()?->id;

        if ($userId === null) {
            return;
        }

        [$oldValues, $newValues] = $this->buildAuditValues($action);

        // Reine Zeitstempel-Updates o. Ä. erzeugen keinen leeren Eintrag.
        if ($action === 'updated' && $newValues === []) {
            return;
        }

        WriteAuditLog::dispatch(
            auditableType: class_basename($this),
            auditableId: (string) $this->getKey(),
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            userId: $userId,
            ipAddress: $request?->ip(),
            userAgent: $request?->userAgent(),
            productVersionId: AuditContext::productVersionId(),
        )->afterCommit();
    }

    /**
     * Ermittelt Vorher-/Nachher-Werte je nach Aktion.
     *
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    protected function buildAuditValues(string $action): array
    {
        $excluded = array_merge(['created_at', 'updated_at'], $this->auditExcluded());

        return match ($action) {
            'created' => [null, $this->exceptKeys($this->getAttributes(), $excluded)],
            'deleted' => [$this->exceptKeys($this->getOriginal(), $excluded), null],
            default => $this->buildUpdateDiff($excluded),
        };
    }

    /**
     * Für Updates: nur die tatsächlich geänderten Felder mit Alt-/Neu-Wert.
     *
     * @param  array<int, string>  $excluded
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function buildUpdateDiff(array $excluded): array
    {
        $newValues = $this->exceptKeys($this->getChanges(), $excluded);

        if ($newValues === []) {
            return [[], []];
        }

        $oldValues = array_intersect_key($this->getOriginal(), $newValues);

        return [$oldValues, $newValues];
    }

    /**
     * Entfernt ausgeschlossene Schlüssel aus einem Wert-Array.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $excluded
     * @return array<string, mixed>
     */
    protected function exceptKeys(array $values, array $excluded): array
    {
        foreach ($excluded as $key) {
            unset($values[$key]);
        }

        return $values;
    }
}
