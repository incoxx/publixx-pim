<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ExecuteCollectionRenderJob;
use App\Models\Collection;
use App\Models\CollectionRenderJob;
use App\Services\Collections\CollectionRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Job-ID/Poll/Download-Pattern gespiegelt von ReportTemplateController, aber ueber
 * CollectionPolicy ('view'-Ability) autorisiert statt der schwaecheren
 * Owner-ID-Pruefung von ReportTemplateController -- Collections hat bereits eine
 * vollwertige Permission-basierte Policy, konsistenter als ein zweiter Auth-Stil hier.
 */
class CollectionRenderController extends Controller
{
    public function __construct(
        private readonly CollectionRenderService $renderService,
    ) {}

    /**
     * POST /api/v1/collections/{collection}/render
     */
    public function execute(Request $request, Collection $collection)
    {
        $this->authorize('view', $collection);

        $validated = $request->validate([
            'format' => 'sometimes|string|in:pdf,xlsx',
            'async' => 'sometimes|boolean',
        ]);

        $format = $validated['format'] ?? 'pdf';
        $this->assertFormatAllowed($collection, $format);

        if ($validated['async'] ?? false) {
            $job = CollectionRenderJob::create([
                'collection_id' => $collection->id,
                'format' => $format,
                'last_status' => 'pending',
                'user_id' => $request->user()?->id,
            ]);

            ExecuteCollectionRenderJob::dispatch($job->id)->onQueue('pdf');

            return response()->json([
                'message' => 'Collection-Render-Job in Warteschlange eingereiht',
                'data' => $job,
            ], 202);
        }

        $result = $this->renderService->render($collection, $format);

        return response()->download(
            $result['path'],
            basename($result['path']),
        )->deleteFileAfterSend(false);
    }

    /**
     * POST /api/v1/collections/{collection}/render/preview
     */
    public function preview(Request $request, Collection $collection)
    {
        $this->authorize('view', $collection);

        $validated = $request->validate([
            'format' => 'sometimes|string|in:pdf,xlsx',
        ]);

        $format = $validated['format'] ?? 'pdf';
        $this->assertFormatAllowed($collection, $format);

        $result = $this->renderService->render($collection, $format, limit: 20);

        return response()->download(
            $result['path'],
            basename($result['path']),
        )->deleteFileAfterSend(true);
    }

    /**
     * GET /api/v1/collection-render-jobs/{id}
     */
    public function jobStatus(Request $request, string $id): JsonResponse
    {
        $job = CollectionRenderJob::findOrFail($id);
        $this->authorize('view', $job->collection);

        return response()->json(['data' => $job]);
    }

    /**
     * GET /api/v1/collection-render-jobs/{id}/download
     */
    public function jobDownload(Request $request, string $id)
    {
        $job = CollectionRenderJob::findOrFail($id);
        $this->authorize('view', $job->collection);

        if (!$job->last_output_path || !file_exists($job->last_output_path)) {
            return response()->json([
                'error' => 'Keine Render-Datei vorhanden. Bitte zuerst den Render-Job ausführen.',
            ], 404);
        }

        return response()->download(
            $job->last_output_path,
            basename($job->last_output_path),
        );
    }

    private function assertFormatAllowed(Collection $collection, string $format): void
    {
        $allowed = $collection->collectionType->allowed_export_formats ?? [];

        if (!in_array($format, $allowed, true)) {
            abort(422, "Format '{$format}' ist fuer diesen Collection-Typ nicht freigegeben.");
        }
    }
}
