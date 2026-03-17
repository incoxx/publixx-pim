<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessPdfDocument;
use App\Models\Media;
use App\Models\PdfDocument;
use Illuminate\Console\Command;

/**
 * Erzeugt Vorschaubilder für bestehende PDFs, die noch kein PdfDocument haben.
 *
 * Verwendung:
 *   php artisan pim:process-pdfs              # Alle fehlenden
 *   php artisan pim:process-pdfs --reprocess  # Auch fehlerhafte erneut
 *   php artisan pim:process-pdfs --all        # Alle PDFs neu verarbeiten
 */
class ProcessExistingPdfs extends Command
{
    protected $signature = 'pim:process-pdfs
                            {--reprocess : Fehlerhafte PDFs erneut verarbeiten}
                            {--all : Alle PDFs neu verarbeiten (auch bereits fertige)}';

    protected $description = 'Vorschaubilder für bestehende PDF-Assets erzeugen';

    public function handle(): int
    {
        $reprocess = $this->option('reprocess');
        $all = $this->option('all');

        // Find all PDF media assets
        $pdfMedia = Media::where(function ($q) {
            $q->where('mime_type', 'application/pdf')
              ->orWhere('file_name', 'like', '%.pdf')
              ->orWhere('file_path', 'like', '%.pdf');
        })->get();

        $this->info("Gefundene PDF-Assets: {$pdfMedia->count()}");

        $dispatched = 0;
        $skipped = 0;

        foreach ($pdfMedia as $media) {
            $existing = PdfDocument::where('media_id', $media->id)->first();

            if ($existing) {
                if ($all) {
                    // Reprocess everything
                } elseif ($reprocess && in_array($existing->status, ['error', 'pending'])) {
                    // Reprocess failed ones
                } elseif ($existing->status === 'ready') {
                    $skipped++;
                    continue;
                } elseif (!$reprocess) {
                    $skipped++;
                    continue;
                }
            }

            $url = $media->file_path;
            if (empty($url)) {
                $this->warn("  Übersprungen (kein file_path): {$media->file_name}");
                $skipped++;
                continue;
            }

            $pdfDocument = PdfDocument::updateOrCreate(
                ['media_id' => $media->id],
                [
                    'original_url' => $url,
                    'status' => 'pending',
                    'error_message' => null,
                ]
            );

            ProcessPdfDocument::dispatch($pdfDocument->id);
            $dispatched++;

            $this->line("  → Dispatched: {$media->file_name}");
        }

        $this->info("Fertig: {$dispatched} Jobs dispatched, {$skipped} übersprungen.");

        return self::SUCCESS;
    }
}
