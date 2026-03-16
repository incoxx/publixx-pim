<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ProcessPdfDocument;
use App\Models\PdfDocument;
use App\Services\TypesenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PdfController extends Controller
{
    /**
     * GET /pdf/{pdfDocument} — Metadaten eines PDF-Dokuments.
     */
    public function show(PdfDocument $pdfDocument): JsonResponse
    {
        $media = $pdfDocument->media;

        return $this->successResponse([
            'id' => $pdfDocument->id,
            'media_id' => $pdfDocument->media_id,
            'status' => $pdfDocument->status,
            'page_count' => $pdfDocument->page_count,
            'original_url' => $pdfDocument->original_url,
            'error_message' => $pdfDocument->error_message,
            'title' => $media->title_de ?? $media->title_en ?? $media->file_name,
            'created_at' => $pdfDocument->created_at,
            'updated_at' => $pdfDocument->updated_at,
        ]);
    }

    /**
     * GET /pdf/{pdfDocument}/page/{pageNumber} — Redirect auf WebP-Image.
     */
    public function page(PdfDocument $pdfDocument, int $pageNumber): RedirectResponse|JsonResponse
    {
        if ($pdfDocument->status !== 'ready') {
            return $this->errorResponse(
                'pdf_not_ready',
                'PDF nicht bereit',
                409,
                "PDF-Status: {$pdfDocument->status}"
            );
        }

        $page = $pdfDocument->pages()->where('page_number', $pageNumber)->first();

        if (!$page) {
            return $this->errorResponse(
                'not_found',
                'Seite nicht gefunden',
                404,
                "Seite {$pageNumber} existiert nicht."
            );
        }

        $url = Storage::disk('public')->url($page->image_path);

        return redirect($url);
    }

    /**
     * GET /pdf/{pdfDocument}/pages — Alle Page-Image-URLs als Array.
     */
    public function pages(PdfDocument $pdfDocument): JsonResponse
    {
        if ($pdfDocument->status !== 'ready') {
            return $this->successResponse([
                'id' => $pdfDocument->id,
                'status' => $pdfDocument->status,
                'page_count' => $pdfDocument->page_count,
                'pages' => [],
            ]);
        }

        $pages = $pdfDocument->pages->map(fn ($page) => [
            'page_number' => $page->page_number,
            'image_url' => Storage::disk('public')->url($page->image_path),
        ]);

        return $this->successResponse([
            'id' => $pdfDocument->id,
            'status' => $pdfDocument->status,
            'page_count' => $pdfDocument->page_count,
            'pages' => $pages,
        ]);
    }

    /**
     * GET /pdf/{pdfDocument}/status — Polling-Endpoint.
     */
    public function status(PdfDocument $pdfDocument): JsonResponse
    {
        return $this->successResponse([
            'id' => $pdfDocument->id,
            'status' => $pdfDocument->status,
            'page_count' => $pdfDocument->page_count,
            'error_message' => $pdfDocument->error_message,
        ]);
    }

    /**
     * GET /pdf/search — Volltextsuche über PDF-Seiten via Typesense.
     */
    public function search(Request $request, TypesenseService $typesense): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:200',
            'lang' => 'nullable|string|max:5',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $result = $typesense->search(
            $request->input('q'),
            $request->input('lang'),
            (int) $request->input('per_page', 20),
            (int) $request->input('page', 1),
        );

        return $this->successResponse($result);
    }

    /**
     * GET /pdf/by-media/{mediaId} — PdfDocument anhand der Media-ID finden.
     */
    public function byMedia(string $mediaId): JsonResponse
    {
        $pdfDocument = PdfDocument::where('media_id', $mediaId)->first();

        if (!$pdfDocument) {
            return $this->errorResponse(
                'not_found',
                'Kein PDF-Dokument gefunden',
                404,
                'Für dieses Media-Asset existiert kein PDF-Dokument.'
            );
        }

        $media = $pdfDocument->media;

        return $this->successResponse([
            'id' => $pdfDocument->id,
            'media_id' => $pdfDocument->media_id,
            'status' => $pdfDocument->status,
            'page_count' => $pdfDocument->page_count,
            'original_url' => $pdfDocument->original_url,
            'error_message' => $pdfDocument->error_message,
            'title' => $media->title_de ?? $media->title_en ?? $media->file_name,
            'created_at' => $pdfDocument->created_at,
            'updated_at' => $pdfDocument->updated_at,
        ]);
    }

    /**
     * POST /pdf/{pdfDocument}/reprocess — Job erneut starten.
     */
    public function reprocess(PdfDocument $pdfDocument): JsonResponse
    {
        $this->authorize('update', $pdfDocument->media);

        $pdfDocument->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        dispatch(new ProcessPdfDocument($pdfDocument->id))->afterCommit();

        return $this->successResponse([
            'message' => 'Verarbeitung erneut gestartet.',
            'id' => $pdfDocument->id,
            'status' => 'pending',
        ]);
    }
}
