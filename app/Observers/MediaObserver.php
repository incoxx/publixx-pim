<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ProcessPdfDocument;
use App\Models\Media;
use App\Models\PdfDocument;
use App\Services\TypesenseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * MediaObserver – reagiert auf CRUD-Operationen am Media-Model.
 *
 * Verantwortlich für:
 * - PDF-Verarbeitung auslösen wenn ein PDF-Asset angelegt/aktualisiert wird
 * - Typesense-Index und Storage bereinigen bei Löschung
 */
class MediaObserver
{
    public function created(Media $media): void
    {
        $this->handlePdfProcessing($media);
    }

    public function updated(Media $media): void
    {
        $this->handlePdfProcessing($media);
    }

    /**
     * Media wird gelöscht → Typesense-Index + Storage-Dateien bereinigen.
     */
    public function deleted(Media $media): void
    {
        $pdfDocument = PdfDocument::where('media_id', $media->id)->first();

        if (!$pdfDocument) {
            return;
        }

        // Typesense-Index bereinigen
        try {
            app(TypesenseService::class)->deletePagesForDocument($pdfDocument->id);
        } catch (\Throwable $e) {
            Log::warning('MediaObserver: Failed to delete from Typesense', [
                'pdf_document_id' => $pdfDocument->id,
                'error' => $e->getMessage(),
            ]);
        }

        // WebP-Bilder aus Storage löschen
        try {
            Storage::disk('public')->deleteDirectory('pdf-pages/' . $pdfDocument->id);
        } catch (\Throwable $e) {
            Log::warning('MediaObserver: Failed to delete PDF page images', [
                'pdf_document_id' => $pdfDocument->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::debug('MediaObserver: PDF cleanup completed', [
            'media_id' => $media->id,
            'pdf_document_id' => $pdfDocument->id,
        ]);
    }

    /**
     * Prüft ob das Media-Asset ein PDF ist und startet die Verarbeitung.
     */
    private function handlePdfProcessing(Media $media): void
    {
        if (!$this->isPdf($media)) {
            return;
        }

        $url = $media->file_path;

        if (empty($url)) {
            return;
        }

        try {
            // Alte Typesense-Einträge bereinigen falls PdfDocument bereits existiert
            $existing = PdfDocument::where('media_id', $media->id)->first();
            if ($existing) {
                try {
                    app(TypesenseService::class)->deletePagesForDocument($existing->id);
                } catch (\Throwable $e) {
                    Log::warning('MediaObserver: Typesense cleanup failed', [
                        'pdf_document_id' => $existing->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $pdfDocument = PdfDocument::updateOrCreate(
                ['media_id' => $media->id],
                [
                    'original_url' => $url,
                    'status' => 'pending',
                    'error_message' => null,
                ]
            );

            dispatch(new ProcessPdfDocument($pdfDocument->id))->afterCommit();

            Log::debug('MediaObserver: PDF processing dispatched', [
                'media_id' => $media->id,
                'pdf_document_id' => $pdfDocument->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MediaObserver: Failed to dispatch PDF processing', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isPdf(Media $media): bool
    {
        if ($media->mime_type === 'application/pdf') {
            return true;
        }

        if ($media->file_path && str_ends_with(strtolower($media->file_path), '.pdf')) {
            return true;
        }

        if ($media->file_name && str_ends_with(strtolower($media->file_name), '.pdf')) {
            return true;
        }

        return false;
    }
}
