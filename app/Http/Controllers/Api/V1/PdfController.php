<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ProcessPdfDocument;
use App\Models\Media;
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

        $lang = $request->input('lang', 'de');

        $result = $typesense->search(
            $request->input('q'),
            $request->input('lang'),
            (int) $request->input('per_page', 20),
            (int) $request->input('page', 1),
        );

        // Suchergebnisse mit Media-Metadaten und Produkt-Referenzen anreichern
        $mediaIds = collect($result['groups'] ?? [])->pluck('media_id')->filter()->unique()->values()->toArray();

        if (!empty($mediaIds)) {
            $hasSearchIndex = \Illuminate\Support\Facades\DB::getSchemaBuilder()->hasTable('products_search_index');

            $mediaItems = Media::whereIn('id', $mediaIds)
                ->with([
                    'productAssignments.product' => function ($q) {
                        $q->where('status', 'active')->select('id', 'sku');
                    },
                    'hierarchyNodeAssignments.hierarchyNode',
                ])
                ->get()
                ->keyBy('id');

            // Produkt-Namen aus Search-Index holen wenn verfügbar
            $productNames = [];
            if ($hasSearchIndex) {
                $productIds = $mediaItems->flatMap(fn ($m) => $m->productAssignments->pluck('product_id'))->unique()->values()->toArray();
                if (!empty($productIds)) {
                    $nameField = $lang === 'en' ? 'name_en' : 'name_de';
                    $productNames = \Illuminate\Support\Facades\DB::table('products_search_index')
                        ->whereIn('product_id', $productIds)
                        ->pluck($nameField, 'product_id')
                        ->toArray();
                }
            }

            foreach ($result['groups'] as &$group) {
                $media = $mediaItems->get($group['media_id']);
                if ($media) {
                    $group['metadata'] = [
                        'description' => $lang === 'en' ? ($media->description_en ?? $media->description_de) : ($media->description_de ?? $media->description_en),
                        'media_type' => $media->media_type,
                        'usage_purpose' => $media->usage_purpose,
                        'file_size' => $media->file_size,
                        'original_file_name' => $media->original_file_name,
                        'created_at' => $media->created_at,
                    ];
                    $group['products'] = $media->productAssignments
                        ->filter(fn ($a) => $a->product !== null)
                        ->map(fn ($a) => [
                            'id' => $a->product->id,
                            'sku' => $a->product->sku,
                            'name' => $productNames[$a->product->id] ?? $a->product->sku,
                        ])
                        ->unique('id')
                        ->values()
                        ->toArray();

                    $group['hierarchy_nodes'] = $media->hierarchyNodeAssignments
                        ->map(function ($a) use ($lang) {
                            $node = $a->hierarchyNode;
                            if (!$node) return null;
                            return [
                                'node_id' => $node->id,
                                'node_name' => $lang === 'en' && $node->name_en ? $node->name_en : $node->name_de,
                            ];
                        })
                        ->filter()
                        ->values()
                        ->toArray();
                } else {
                    $group['metadata'] = null;
                    $group['products'] = [];
                    $group['hierarchy_nodes'] = [];
                }
            }
            unset($group);
        }

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

    /**
     * POST /admin/pdf/batch-process — Vorschaubilder für alle bestehenden PDFs erzeugen.
     */
    public function batchProcess(Request $request): JsonResponse
    {
        if (!$request->user()?->hasRole('Admin')) {
            abort(403, 'Unauthorized.');
        }

        $mode = $request->input('mode', 'missing'); // missing | failed | all

        $pdfMedia = Media::where(function ($q) {
            $q->where('mime_type', 'application/pdf')
              ->orWhere('file_name', 'like', '%.pdf')
              ->orWhere('file_path', 'like', '%.pdf');
        })->get();

        $dispatched = 0;
        $skipped = 0;

        foreach ($pdfMedia as $media) {
            $existing = PdfDocument::where('media_id', $media->id)->first();

            if ($existing) {
                if ($mode === 'all') {
                    // reprocess all
                } elseif ($mode === 'failed' && in_array($existing->status, ['error', 'pending'])) {
                    // reprocess failed
                } elseif ($existing->status === 'ready') {
                    $skipped++;
                    continue;
                } elseif ($mode === 'missing') {
                    $skipped++;
                    continue;
                }
            }

            $url = $media->file_path;
            if (empty($url)) {
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
        }

        return response()->json([
            'message' => "{$dispatched} PDF-Verarbeitungen gestartet, {$skipped} übersprungen.",
            'dispatched' => $dispatched,
            'skipped' => $skipped,
            'total_pdfs' => $pdfMedia->count(),
        ]);
    }
}
