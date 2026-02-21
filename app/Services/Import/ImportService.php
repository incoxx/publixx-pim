<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Events\ImportCompleted;
use App\Jobs\ExecuteImportJob;
use App\Models\ImportJob;
use App\Models\ImportJobError;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orchestriert den 3-Phasen-Import-Prozess:
 *
 * Phase 1: Upload — Datei speichern, Sheets erkennen
 * Phase 2: Validierung — Schema-Prüfung, Referenz-Auflösung, Fuzzy-Matching
 * Phase 3: Ausführung — Daten schreiben (sync oder async via Queue)
 *
 * Wird vom ImportController (Agent 3) aufgerufen.
 */
class ImportService
{
    /** Ab dieser Zeilenzahl wird async importiert. */
    private const int ASYNC_THRESHOLD = 100;

    public function __construct(
        private readonly SheetParser $parser,
        private readonly SheetValidator $validator,
        private readonly ImportExecutor $executor,
        private readonly TemplateGenerator $templateGenerator,
    ) {}

    // ──────────────────────────────────────────────
    //  Phase 1: Upload
    // ──────────────────────────────────────────────

    /**
     * Speichert die hochgeladene Datei und erkennt vorhandene Sheets.
     *
     * @param UploadedFile $file   Hochgeladene Excel-Datei
     * @param string       $userId ID des eingeloggten Users
     * @return ImportJob
     */
    public function upload(UploadedFile $file, string $userId): ImportJob
    {
        $uuid = Str::uuid()->toString();
        $fileName = $file->getClientOriginalName();
        $storagePath = "imports/{$uuid}.xlsx";

        Storage::disk('local')->putFileAs('imports', $file, "{$uuid}.xlsx");
        $fullPath = Storage::disk('local')->path($storagePath);

        // Sheets erkennen
        $parseResult = $this->parser->parse($fullPath);

        // ImportJob anlegen
        $importJob = ImportJob::create([
            'id' => $uuid,
            'user_id' => $userId,
            'file_name' => $fileName,
            'file_path' => $storagePath,
            'status' => 'uploaded',
            'sheets_found' => $parseResult->sheetsFound,
            'summary' => null,
            'result' => null,
        ]);

        Log::channel('import')->info("Import hochgeladen: {$uuid}", [
            'file' => $fileName,
            'sheets' => $parseResult->sheetsFound,
            'total_rows' => $parseResult->totalRows(),
        ]);

        // Automatisch validieren (Phase 2)
        $this->validate($importJob);

        return $importJob->fresh();
    }

    // ──────────────────────────────────────────────
    //  Phase 2: Validierung
    // ──────────────────────────────────────────────

    /**
     * Validiert die importierte Datei: Schema, Referenzen, Fuzzy-Matching.
     */
    public function validate(ImportJob $importJob): ImportJob
    {
        $importJob->update(['status' => 'validating']);

        $fullPath = Storage::disk('local')->path($importJob->file_path);
        $parseResult = $this->parser->parse($fullPath);
        $validationResult = $this->validator->validate($parseResult);

        // Alte Fehler löschen und neue speichern
        ImportJobError::where('import_job_id', $importJob->id)->delete();

        foreach ($validationResult->errors as $error) {
            ImportJobError::create([
                'id' => Str::uuid()->toString(),
                'import_job_id' => $importJob->id,
                'sheet' => $error['sheet'],
                'row' => $error['row'],
                'column' => $error['column'],
                'field' => $error['field'],
                'value' => $error['value'] !== null ? (string) $error['value'] : null,
                'error' => $error['error'],
                'suggestion' => $error['suggestion'],
            ]);
        }

        $importJob->update([
            'status' => 'validated',
            'summary' => $validationResult->toArray(),
        ]);

        Log::channel('import')->info("Import validiert: {$importJob->id}", [
            'has_errors' => $validationResult->hasErrors,
            'error_count' => count($validationResult->errors),
        ]);

        return $importJob->fresh();
    }

    // ──────────────────────────────────────────────
    //  Phase 2.5: Preview
    // ──────────────────────────────────────────────

    /**
     * Gibt eine Diff-Vorschau zurück (Create/Update/Skip pro Sheet).
     */
    public function preview(ImportJob $importJob): array
    {
        $summary = $importJob->summary ?? [];
        $errors = ImportJobError::where('import_job_id', $importJob->id)->get();

        return [
            'import_id' => $importJob->id,
            'status' => $importJob->status,
            'sheets_found' => $importJob->sheets_found,
            'summary' => $summary['summary'] ?? [],
            'errors' => $errors->map(fn($e) => [
                'sheet' => $e->sheet,
                'row' => $e->row,
                'column' => $e->column,
                'field' => $e->field,
                'value' => $e->value,
                'error' => $e->error,
                'suggestion' => $e->suggestion,
            ])->toArray(),
        ];
    }

    // ──────────────────────────────────────────────
    //  Phase 3: Ausführung
    // ──────────────────────────────────────────────

    /**
     * Führt den Import aus. Bei > 100 Zeilen async via Queue.
     *
     * @param ImportJob $importJob Der validierte ImportJob
     * @param bool      $force     Import trotz Validierungsfehlern erzwingen
     * @return ImportJob
     */
    public function execute(ImportJob $importJob, bool $force = false): ImportJob
    {
        if ($importJob->status !== 'validated') {
            throw new \RuntimeException(
                "Import kann nur im Status 'validated' ausgeführt werden. Aktueller Status: {$importJob->status}"
            );
        }

        // Prüfe ob Fehler vorhanden
        if (!$force) {
            $errorCount = ImportJobError::where('import_job_id', $importJob->id)->count();
            if ($errorCount > 0) {
                throw new \RuntimeException(
                    "Import hat {$errorCount} Validierungsfehler. Verwende force=true um trotzdem zu importieren."
                );
            }
        }

        $fullPath = Storage::disk('local')->path($importJob->file_path);
        $parseResult = $this->parser->parse($fullPath);

        if ($parseResult->totalRows() > self::ASYNC_THRESHOLD) {
            return $this->executeAsync($importJob, $parseResult);
        }

        return $this->executeSync($importJob, $parseResult);
    }

    /**
     * Synchrone Ausführung (kleiner Import).
     */
    private function executeSync(ImportJob $importJob, ParseResult $parseResult): ImportJob
    {
        $importJob->update([
            'status' => 'executing',
            'started_at' => now(),
        ]);

        try {
            $result = $this->executor->execute($parseResult);

            $importJob->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => $result->toArray(),
            ]);

            event(new ImportCompleted(
                importJobId: $importJob->id,
                productIds: $result->affectedProductIds,
            ));

            Log::channel('import')->info("Import synchron abgeschlossen: {$importJob->id}", $result->stats);
        } catch (\Throwable $e) {
            $importJob->update([
                'status' => 'failed',
                'completed_at' => now(),
                'result' => ['error' => $e->getMessage()],
            ]);

            Log::channel('import')->error("Import fehlgeschlagen: {$importJob->id}", ['error' => $e->getMessage()]);
            throw $e;
        }

        return $importJob->fresh();
    }

    /**
     * Asynchrone Ausführung via Queue (großer Import).
     */
    private function executeAsync(ImportJob $importJob, ParseResult $parseResult): ImportJob
    {
        $importJob->update([
            'status' => 'executing',
            'started_at' => now(),
        ]);

        ExecuteImportJob::dispatch($importJob->id, $parseResult);

        Log::channel('import')->info("Import in Queue eingereiht: {$importJob->id}", [
            'total_rows' => $parseResult->totalRows(),
        ]);

        return $importJob->fresh();
    }

    // ──────────────────────────────────────────────
    //  Ergebnis
    // ──────────────────────────────────────────────

    /**
     * Gibt den vollständigen Import-Report zurück.
     */
    public function result(ImportJob $importJob): array
    {
        $errors = ImportJobError::where('import_job_id', $importJob->id)
            ->get()
            ->map(fn($e) => [
                'sheet' => $e->sheet,
                'row' => $e->row,
                'column' => $e->column,
                'field' => $e->field,
                'value' => $e->value,
                'error' => $e->error,
                'suggestion' => $e->suggestion,
            ])
            ->toArray();

        return [
            'import_id' => $importJob->id,
            'status' => $importJob->status,
            'file_name' => $importJob->file_name,
            'sheets_found' => $importJob->sheets_found,
            'summary' => $importJob->summary,
            'result' => $importJob->result,
            'started_at' => $importJob->started_at?->toIso8601String(),
            'completed_at' => $importJob->completed_at?->toIso8601String(),
            'errors' => $errors,
        ];
    }

    // ──────────────────────────────────────────────
    //  Template
    // ──────────────────────────────────────────────

    /**
     * Erzeugt ein leeres Import-Template zum Download.
     *
     * @return string Pfad zur erzeugten .xlsx-Datei
     */
    public function generateTemplate(): string
    {
        $path = Storage::disk('local')->path('imports/template_pim_import.xlsx');
        $this->templateGenerator->generate($path);
        return $path;
    }
}
