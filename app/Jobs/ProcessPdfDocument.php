<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PdfDocument;
use App\Models\PdfPage;
use App\Services\TypesenseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * ProcessPdfDocument – PDF herunterladen, Seiten rendern und Text extrahieren.
 *
 * Pipeline:
 * 1. PDF herunterladen
 * 2. Seitenanzahl via pdfinfo
 * 3. Seiten rendern via pdftoppm → PNG → WebP (GD)
 * 4. Text extrahieren via pdftotext
 * 5. In Typesense indexieren
 */
class ProcessPdfDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    public int $timeout = 300;

    public function __construct(
        public readonly string $pdfDocumentId,
    ) {
        $this->onQueue('pdf');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->pdfDocumentId))
                ->releaseAfter(60)
                ->expireAfter(300),
        ];
    }

    public function handle(TypesenseService $typesense): void
    {
        $document = PdfDocument::find($this->pdfDocumentId);

        if (!$document) {
            Log::warning('ProcessPdfDocument: Document not found', [
                'pdf_document_id' => $this->pdfDocumentId,
            ]);

            return;
        }

        $document->update(['status' => 'processing', 'error_message' => null]);

        $tmpDir = storage_path('app/private/tmp/pdf-' . $document->id);
        $pdfPath = $tmpDir . '/source.pdf';

        try {
            // Verzeichnis anlegen
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            // 1. PDF herunterladen
            $this->downloadPdf($document->original_url, $pdfPath);

            // 2. Seitenanzahl ermitteln
            $pageCount = $this->getPageCount($pdfPath);
            $document->update(['page_count' => $pageCount]);

            // 3+4. Seiten rendern und Text extrahieren
            $storageDir = 'pdf-pages/' . $document->id;

            // Alte Seiten entfernen
            $document->pages()->delete();

            for ($n = 1; $n <= $pageCount; $n++) {
                // PNG rendern via pdftoppm
                $pngPath = $tmpDir . '/page-' . $n . '.png';
                $this->renderPage($pdfPath, $pngPath, $n);

                // PNG → WebP konvertieren via GD
                $webpRelativePath = $storageDir . '/page-' . $n . '.webp';
                $this->convertToWebp($pngPath, $webpRelativePath);

                // Text extrahieren
                $text = $this->extractText($pdfPath, $n);

                // PdfPage anlegen
                PdfPage::create([
                    'pdf_document_id' => $document->id,
                    'page_number' => $n,
                    'image_path' => $webpRelativePath,
                    'extracted_text' => $text,
                ]);

                // Temporäre PNG löschen
                @unlink($pngPath);
            }

            // 5. In Typesense indexieren
            $document->load('pages', 'media');
            try {
                $typesense->upsertPages($document);
            } catch (\Throwable $e) {
                Log::warning('ProcessPdfDocument: Typesense indexing failed (non-fatal)', [
                    'pdf_document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // 6. Status → ready
            $document->update(['status' => 'ready']);

            Log::info('ProcessPdfDocument: Completed', [
                'pdf_document_id' => $document->id,
                'page_count' => $pageCount,
            ]);
        } catch (\Throwable $e) {
            $document->update([
                'status' => 'error',
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            Log::error('ProcessPdfDocument: Failed', [
                'pdf_document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Temporäre Dateien aufräumen
            $this->cleanupTmpDir($tmpDir);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $document = PdfDocument::find($this->pdfDocumentId);

        if ($document) {
            $document->update([
                'status' => 'error',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
        }
    }

    private function downloadPdf(string $url, string $destination): void
    {
        $response = Http::timeout(60)->withOptions(['sink' => $destination])->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('PDF download failed: HTTP ' . $response->status());
        }

        if (!file_exists($destination) || filesize($destination) === 0) {
            throw new \RuntimeException('PDF download resulted in empty file');
        }
    }

    private function getPageCount(string $pdfPath): int
    {
        $result = Process::run(['pdfinfo', $pdfPath]);

        if (!$result->successful()) {
            throw new \RuntimeException('pdfinfo failed: ' . $result->errorOutput());
        }

        if (preg_match('/Pages:\s+(\d+)/', $result->output(), $matches)) {
            return (int) $matches[1];
        }

        throw new \RuntimeException('Could not determine page count from pdfinfo output');
    }

    private function renderPage(string $pdfPath, string $outputPath, int $pageNumber): void
    {
        $outputPrefix = pathinfo($outputPath, PATHINFO_DIRNAME) . '/' . pathinfo($outputPath, PATHINFO_FILENAME);

        $result = Process::timeout(60)->run([
            'pdftoppm',
            '-f', (string) $pageNumber,
            '-l', (string) $pageNumber,
            '-png',
            '-r', '150',
            '-singlefile',
            $pdfPath,
            $outputPrefix,
        ]);

        if (!$result->successful()) {
            throw new \RuntimeException("pdftoppm failed for page {$pageNumber}: " . $result->errorOutput());
        }

        // pdftoppm mit -singlefile erzeugt {prefix}.png
        $generatedPath = $outputPrefix . '.png';
        if ($generatedPath !== $outputPath && file_exists($generatedPath)) {
            rename($generatedPath, $outputPath);
        }

        if (!file_exists($outputPath)) {
            throw new \RuntimeException("pdftoppm did not create output file for page {$pageNumber}");
        }
    }

    private function convertToWebp(string $pngPath, string $storageRelativePath): void
    {
        $image = @imagecreatefrompng($pngPath);

        if ($image === false) {
            throw new \RuntimeException('Failed to load PNG: ' . $pngPath);
        }

        // In temporären Buffer als WebP schreiben
        ob_start();
        imagewebp($image, null, 85);
        $webpData = ob_get_clean();
        imagedestroy($image);

        if ($webpData === false || $webpData === '') {
            throw new \RuntimeException('WebP conversion failed');
        }

        Storage::disk('public')->put($storageRelativePath, $webpData);
    }

    private function extractText(string $pdfPath, int $pageNumber): string
    {
        $result = Process::timeout(30)->run([
            'pdftotext',
            '-f', (string) $pageNumber,
            '-l', (string) $pageNumber,
            '-layout',
            $pdfPath,
            '-',
        ]);

        if (!$result->successful()) {
            Log::warning('ProcessPdfDocument: pdftotext failed for page', [
                'page' => $pageNumber,
                'error' => $result->errorOutput(),
            ]);

            return '';
        }

        return trim($result->output());
    }

    private function cleanupTmpDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}
