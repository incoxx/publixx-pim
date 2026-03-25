<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExcelTemplate;
use App\Models\SearchProfile;
use App\Services\ExcelDesigner\ExcelDataCollector;
use App\Services\ExcelDesigner\ExcelSheetWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nur bei echten Exceptions abbrechen, nicht bei Timeout.
     */
    public int $maxExceptions = 1;

    /**
     * Job darf bis zu 30 Minuten laufen.
     */
    public int $timeout = 1800;

    private const CACHE_PREFIX = 'excel_export:';

    private const CANCEL_PREFIX = 'excel_export_cancel:';

    public function __construct(
        public readonly string $templateId,
        public readonly ?string $searchProfileId,
        public readonly string $exportKey,
    ) {}

    /**
     * Job darf bis zu 45 Minuten nach Dispatch versucht werden.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(45);
    }

    public function handle(ExcelDataCollector $dataCollector, ExcelSheetWriter $sheetWriter): void
    {
        $template = ExcelTemplate::findOrFail($this->templateId);
        $searchProfile = $this->searchProfileId ? SearchProfile::find($this->searchProfileId) : null;

        // Fallback: Suchprofil vom Template
        if (!$searchProfile && $template->search_profile_id) {
            $searchProfile = SearchProfile::find($template->search_profile_id);
        }

        $this->updateProgress('counting', 0, 0);

        // Gesamtanzahl ermitteln
        $total = $dataCollector->countProducts($template, $searchProfile);

        $customFileName = $template->excel_settings['fileName'] ?? null;
        $baseName = !empty($customFileName) ? Str::slug($customFileName) : Str::slug($template->name);
        $fileName = $baseName . '-' . date('Y-m-d') . '.xlsx';

        $outputPath = storage_path('app/exports/' . $this->exportKey . '.xlsx');
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($total === 0) {
            $this->updateProgress('completed', 0, 0, outputPath: $this->buildEmptyFile($outputPath), fileName: $fileName);
            return;
        }

        $this->updateProgress('collecting', 0, $total);

        // Prüfe ob Gruppierung aktiv ist
        $hasGrouping = $this->hasGrouping($template->template_json);

        if ($hasGrouping) {
            // Gruppierung: Muss alle Produkte laden für groupBy
            $this->exportGrouped($dataCollector, $sheetWriter, $template, $searchProfile, $total, $outputPath, $fileName);
        } else {
            // Kein Grouping: Chunk-weise direkt ins Spreadsheet schreiben
            $this->exportStreaming($dataCollector, $sheetWriter, $template, $searchProfile, $total, $outputPath, $fileName);
        }
    }

    /**
     * Streaming-Export: Chunk-weise Produkte laden und direkt Zeilen schreiben.
     * Hält nur einen Chunk (500 Produkte) gleichzeitig im RAM.
     */
    private function exportStreaming(
        ExcelDataCollector $dataCollector,
        ExcelSheetWriter $sheetWriter,
        ExcelTemplate $template,
        ?SearchProfile $searchProfile,
        int $total,
        string $outputPath,
        string $fileName,
    ): void {
        $language = $template->language ?? 'de';
        $excelSettings = $template->excel_settings ?? [];
        $sheetDef = $template->template_json['sheets'][0] ?? [];
        $columns = $sheetDef['columns'] ?? [];

        // Spreadsheet vorbereiten mit Header
        $spreadsheet = $sheetWriter->buildShell($sheetDef, $columns, $language, $excelSettings);
        $worksheet = $spreadsheet->getActiveSheet();

        // Startzeile nach Header bestimmen
        $row = $sheetWriter->getDataStartRow($sheetDef, $excelSettings);

        $processed = 0;

        $dataCollector->streamProducts(
            $template,
            $searchProfile,
            chunkSize: 500,
            callback: function ($products) use ($sheetWriter, $worksheet, $columns, $language, &$row, &$processed, $total) {
                foreach ($products as $product) {
                    $row = $sheetWriter->writeProductRowDirect($worksheet, $product, $columns, $language, $row);
                    $processed++;
                }

                $this->updateProgress('writing', min($processed, $total), $total);

                // Abbruch prüfen
                if (Cache::get(self::CANCEL_PREFIX . $this->exportKey)) {
                    return false;
                }
                return true;
            },
        );

        // Abgebrochen?
        if (Cache::get(self::CANCEL_PREFIX . $this->exportKey)) {
            $this->handleCancellation($spreadsheet, $processed, $total);
            return;
        }

        // Footer-Zeilen
        $sheetWriter->writeFooterRowsDirect($worksheet, $sheetDef, $columns, $language, $row, $total);

        // Spaltenbreite setzen
        $sheetWriter->applyColumnWidthsDirect($worksheet, $columns);

        // Datei schreiben
        $this->writeAndFinish($spreadsheet, $outputPath, $fileName, $total);
    }

    /**
     * Gruppierter Export: Sammelt alle Produkte für groupBy-Logik.
     * Nutzt collectChunked mit Progress-Callback.
     */
    private function exportGrouped(
        ExcelDataCollector $dataCollector,
        ExcelSheetWriter $sheetWriter,
        ExcelTemplate $template,
        ?SearchProfile $searchProfile,
        int $total,
        string $outputPath,
        string $fileName,
    ): void {
        $processed = 0;

        $data = $dataCollector->collectChunked(
            $template,
            $searchProfile,
            chunkSize: 500,
            onChunk: function (int $chunkCount) use (&$processed, $total) {
                $processed += $chunkCount;
                $this->updateProgress('collecting', min($processed, $total), $total);

                if (Cache::get(self::CANCEL_PREFIX . $this->exportKey)) {
                    return false;
                }
                return true;
            },
        );

        // Abgebrochen?
        if (Cache::get(self::CANCEL_PREFIX . $this->exportKey)) {
            $this->updateProgress('cancelled', $processed, $total);
            Cache::forget(self::CANCEL_PREFIX . $this->exportKey);
            Log::info("ExcelExportJob: Abgebrochen bei {$processed}/{$total}.", ['key' => $this->exportKey]);
            return;
        }

        $this->updateProgress('writing', $processed, $total);

        $spreadsheet = $sheetWriter->build(
            $data['grouped'],
            $template->template_json,
            $template->language ?? 'de',
            $template->excel_settings ?? [],
        );

        $this->writeAndFinish($spreadsheet, $outputPath, $fileName, $total);
    }

    private function writeAndFinish(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $outputPath, string $fileName, int $total): void
    {
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        $this->updateProgress('completed', $total, $total, outputPath: $outputPath, fileName: $fileName);
        Log::info("ExcelExportJob: Fertig – {$total} Produkte.", ['key' => $this->exportKey, 'file' => $outputPath]);
    }

    private function handleCancellation(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, int $processed, int $total): void
    {
        $spreadsheet->disconnectWorksheets();
        $this->updateProgress('cancelled', $processed, $total);
        Cache::forget(self::CANCEL_PREFIX . $this->exportKey);
        Log::info("ExcelExportJob: Abgebrochen bei {$processed}/{$total}.", ['key' => $this->exportKey]);
    }

    /**
     * Prüft ob der grouped-Pfad (alle Produkte im RAM) nötig ist.
     * Das ist der Fall bei: Gruppierung oder arrayMode=expand Spalten.
     */
    private function hasGrouping(array $templateJson): bool
    {
        foreach ($templateJson['sheets'] ?? [] as $sheet) {
            if (($sheet['field'] ?? 'none') !== 'none') {
                return true;
            }
            // expand-Spalten brauchen alle Produkte für max-count Berechnung
            foreach ($sheet['columns'] ?? [] as $column) {
                if (($column['arrayMode'] ?? null) === 'expand') {
                    return true;
                }
            }
            // Verschachtelte Sheets prüfen
            if (!empty($sheet['sheets']) && $this->hasGrouping(['sheets' => $sheet['sheets']])) {
                return true;
            }
        }
        return false;
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('failed', 0, 0, error: $exception->getMessage());
        Log::error('ExcelExportJob failed: ' . $exception->getMessage(), ['key' => $this->exportKey]);

        $outputPath = storage_path('app/exports/' . $this->exportKey . '.xlsx');
        if (file_exists($outputPath)) {
            @unlink($outputPath);
        }
    }

    private function updateProgress(
        string $status,
        int $processed,
        int $total,
        ?string $error = null,
        ?string $outputPath = null,
        ?string $fileName = null,
    ): void {
        Cache::put(self::CACHE_PREFIX . $this->exportKey, [
            'status' => $status,
            'processed' => $processed,
            'total' => $total,
            'percent' => $total > 0 ? round(($processed / $total) * 100, 1) : 0,
            'error' => $error,
            'output_path' => $outputPath,
            'file_name' => $fileName,
            'updated_at' => now()->toIso8601String(),
        ], 3600); // 1h TTL

    }

    private function buildEmptyFile(string $outputPath): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Leer');
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Keine Produkte gefunden.');

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        return $outputPath;
    }

    public static function cacheKey(string $exportKey): string
    {
        return self::CACHE_PREFIX . $exportKey;
    }

    public static function cancel(string $exportKey): void
    {
        Cache::put(self::CANCEL_PREFIX . $exportKey, true, 600);
    }
}
