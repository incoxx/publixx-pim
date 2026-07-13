<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ProcessAudioVideoMedia;
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
 * - ffmpeg/ffprobe-Verarbeitung auslösen für Audio/Video-Assets
 * - Typesense-Index und Storage bereinigen bei Löschung
 */
class MediaObserver
{
    public function created(Media $media): void
    {
        $this->handlePdfProcessing($media);
        $this->handleAvProcessing($media);
    }

    public function updated(Media $media): void
    {
        $this->handlePdfProcessing($media);

        // Bei Datei-Änderung ODER manueller Umklassifizierung (z.B. media_type per UI von
        // 'other' auf 'audio'/'video' korrigiert) neu verarbeiten — nicht bei sonstigen
        // Metadaten-Edits (Titel, Beschreibung, ...) und nicht bei den
        // updateQuietly()-Statusschreibvorgängen aus ProcessAudioVideoMedia selbst
        // (sonst würde jeder Statuswechsel den Job erneut dispatchen).
        if ($media->wasChanged(['file_path', 'file_size', 'mime_type', 'media_type'])) {
            $this->handleAvProcessing($media);
        }
    }

    /**
     * Media wird gelöscht → Typesense-Index + Storage-Dateien bereinigen.
     */
    public function deleted(Media $media): void
    {
        if ($media->video_thumbnail_path) {
            try {
                Storage::disk('public')->delete($media->video_thumbnail_path);
            } catch (\Throwable $e) {
                Log::warning('MediaObserver: Failed to delete video thumbnail', [
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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

    /**
     * Prüft ob das Media-Asset Audio/Video ist und startet die ffmpeg-Verarbeitung.
     */
    private function handleAvProcessing(Media $media): void
    {
        if (!in_array($media->media_type, ['audio', 'video'], true) || empty($media->file_path)) {
            return;
        }

        try {
            $media->forceFill([
                'av_processing_status' => 'pending',
                'av_error_message' => null,
            ])->saveQuietly();

            dispatch(new ProcessAudioVideoMedia($media->id))->afterCommit();

            Log::debug('MediaObserver: AV processing dispatched', [
                'media_id' => $media->id,
                'media_type' => $media->media_type,
            ]);
        } catch (\Throwable $e) {
            Log::warning('MediaObserver: Failed to dispatch AV processing', [
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
