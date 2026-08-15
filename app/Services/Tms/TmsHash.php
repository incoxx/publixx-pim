<?php

declare(strict_types=1);

namespace App\Services\Tms;

/**
 * Der Hash-Vertrag zwischen PIM und TMS.
 *
 * Ein Übersetzungs-Segment wird ausschließlich über
 * `sha256("{quellsprache}|{quelltext}")` identifiziert — es gibt keine
 * fachliche ID, die PIM und TMS gemeinsam kennen. Ändert sich dieses Format
 * nur auf einer Seite, findet `resolve` schlagartig nichts mehr wieder und das
 * gesamte Translation Memory ist entwertet, ohne dass irgendetwas Alarm
 * schlägt.
 *
 * Die Gegenstücke im TMS-Service:
 *   - tms/app/Jobs/ProcessIngestBatchJob.php
 *   - tms/app/Http/Controllers/ImportTranslationsController.php
 *
 * Jede Änderung hier muss dort mitgezogen werden UND erfordert einen
 * vollständigen Neu-Ingest (`php artisan tms:ingest`), da bestehende
 * tms_units.text_hash-Werte sonst verwaisen.
 *
 * @see \Tests\Unit\Services\TmsHashTest
 */
final class TmsHash
{
    /**
     * Berechnet den TMS-Hash für einen Quelltext.
     */
    public static function for(string $lang, string $text): string
    {
        return hash('sha256', $lang . '|' . $text);
    }
}
