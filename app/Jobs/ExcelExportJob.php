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

    public int $timeout = 600; // 10 Minuten

    public int $tries = 1;

    private const CACHE_PREFIX = 'excel_export:';

    private const CANCEL_PREFIX = 'excel_export_cancel:';

    public function __construct(
        private readonly string $templateId,
        private readonly ?string $searchProfileId,
        private readonly string $exportKey,
    ) {}

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
        $this->updateProgress('collecting', 0, $total);

        // Prüfe ob sich ein Async-Export lohnt (unter 1000 → sofort)
        if ($total === 0) {
            $this->updateProgress('completed', 0, 0, outputPath: $this->buildEmptyFile($template));
            return;
        }

        // Daten sammeln mit Progress-Callback
        $processed = 0;
        $data = $dataCollector->collectChunked(
            $template,
            $searchProfile,
            chunkSize: 500,
            onChunk: function (int $chunkCount) use (&$processed, $total) {
                $processed += $chunkCount;
                $this->updateProgress('collecting', min($processed, $total), $total);

                // Abbruch prüfen
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

        // Spreadsheet bauen
        $spreadsheet = $sheetWriter->build(
            $data['grouped'],
            $template->template_json,
            $template->language ?? 'de',
            $template->excel_settings ?? [],
        );

        // In Temp-Datei schreiben
        $customFileName = $template->excel_settings['fileName'] ?? null;
        $baseName = !empty($customFileName) ? Str::slug($customFileName) : Str::slug($template->name);
        $fileName = $baseName . '-' . date('Y-m-d') . '.xlsx';
        $outputPath = storage_path('app/exports/' . $this->exportKey . '.xlsx');

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        $this->updateProgress('completed', $total, $total, outputPath: $outputPath, fileName: $fileName);
        Log::info("ExcelExportJob: Fertig – {$total} Produkte.", ['key' => $this->exportKey, 'file' => $outputPath]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress('failed', 0, 0, error: $exception->getMessage());
        Log::error('ExcelExportJob failed: ' . $exception->getMessage(), ['key' => $this->exportKey]);
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
        ], 1800); // 30 min TTL
    }

    private function buildEmptyFile(ExcelTemplate $template): string
    {
        $outputPath = storage_path('app/exports/' . $this->exportKey . '.xlsx');
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Leer');
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'Keine Produkte gefunden.');

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        return $outputPath;
    }

    /**
     * Cache-Key für Progress-Abfrage.
     */
    public static function cacheKey(string $exportKey): string
    {
        return self::CACHE_PREFIX . $exportKey;
    }

    /**
     * Cancel-Key setzen.
     */
    public static function cancel(string $exportKey): void
    {
        Cache::put(self::CANCEL_PREFIX . $exportKey, true, 600);
    }
}
