<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\AsyncProfileExportJob;
use App\Models\Attribute;
use App\Models\ExportProfile;
use App\Services\Export\ExportProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportProfileController extends Controller
{
    public function __construct(
        private readonly ExportProfileService $exportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ExportProfile::class);

        $profiles = ExportProfile::visibleTo($request->user()->id)
            ->with('searchProfile')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $this->stripStaleReferences($p));

        return response()->json(['data' => $profiles]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ExportProfile::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_shared' => 'boolean',
            'search_profile_id' => 'nullable|string|uuid|exists:search_profiles,id',
            'include_products' => 'boolean',
            'include_attributes' => 'boolean',
            'include_hierarchies' => 'boolean',
            'include_prices' => 'boolean',
            'include_relations' => 'boolean',
            'include_media' => 'boolean',
            'include_variants' => 'boolean',
            'attribute_ids' => 'nullable|array',
            'attribute_ids.*' => 'string|uuid',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:5',
            'format' => 'nullable|string|in:excel,csv,json,xml',
            'file_name_template' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = $request->user()->id;

        $profile = ExportProfile::create($validated);

        return response()->json(['data' => $profile->load('searchProfile')], 201);
    }

    public function update(Request $request, ExportProfile $exportProfile): JsonResponse
    {
        $this->authorize('update', $exportProfile);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_shared' => 'boolean',
            'search_profile_id' => 'nullable|string|uuid|exists:search_profiles,id',
            'include_products' => 'boolean',
            'include_attributes' => 'boolean',
            'include_hierarchies' => 'boolean',
            'include_prices' => 'boolean',
            'include_relations' => 'boolean',
            'include_media' => 'boolean',
            'include_variants' => 'boolean',
            'attribute_ids' => 'nullable|array',
            'attribute_ids.*' => 'string|uuid',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:5',
            'format' => 'nullable|string|in:excel,csv,json,xml',
            'file_name_template' => 'nullable|string|max:255',
        ]);

        $exportProfile->update($validated);

        return response()->json(['data' => $exportProfile->load('searchProfile')]);
    }

    public function destroy(Request $request, ExportProfile $exportProfile): JsonResponse
    {
        $this->authorize('delete', $exportProfile);

        $exportProfile->delete();

        return response()->json(null, 204);
    }

    public function execute(Request $request, ExportProfile $exportProfile): StreamedResponse|JsonResponse
    {
        $this->authorize('execute', $exportProfile);
        set_time_limit(0);

        $validated = $request->validate([
            'file_name' => 'nullable|string|max:255',
            'zip' => 'boolean',
        ]);

        $fileName = $validated['file_name'] ?? null;
        $zip = $validated['zip'] ?? false;

        return $this->exportService->execute($exportProfile, $fileName, $zip);
    }

    /**
     * POST /api/v1/export-profiles/{id}/execute-async — Async-Export starten.
     */
    public function executeAsync(Request $request, ExportProfile $exportProfile): JsonResponse
    {
        $this->authorize('execute', $exportProfile);

        $validated = $request->validate([
            'zip' => 'boolean',
        ]);

        $zip       = $validated['zip'] ?? false;
        $exportKey = 'profile_' . strtolower(Str::random(16));
        $userId    = $request->user()?->id;

        AsyncProfileExportJob::dispatch($exportKey, $exportProfile->id, $zip);

        // Owner-ID für Auth-Check bei Progress/Download speichern
        Cache::put(AsyncProfileExportJob::cacheKey($exportKey) . ':owner', $userId, 3600);

        return response()->json([
            'data' => [
                'export_key' => $exportKey,
                'status'     => 'queued',
            ],
        ]);
    }

    /**
     * GET /api/v1/export-profiles/progress/{exportKey} — Fortschritt abfragen.
     */
    public function exportProgress(Request $request, string $exportKey): JsonResponse
    {
        $this->authorizeExportAccess($request, $exportKey);

        $progress = Cache::get(AsyncProfileExportJob::cacheKey($exportKey));

        if (!$progress) {
            return response()->json([
                'data' => ['status' => 'queued', 'processed' => 0, 'total' => 0, 'percent' => 0],
            ]);
        }

        return response()->json(['data' => $progress]);
    }

    /**
     * POST /api/v1/export-profiles/cancel/{exportKey} — Async-Export abbrechen.
     */
    public function cancelExport(Request $request, string $exportKey): JsonResponse
    {
        $this->authorizeExportAccess($request, $exportKey);

        AsyncProfileExportJob::cancel($exportKey);

        return response()->json(['data' => ['status' => 'cancelling']]);
    }

    /**
     * GET /api/v1/export-profiles/download/{exportKey} — Fertige Datei herunterladen.
     */
    public function downloadExport(Request $request, string $exportKey): BinaryFileResponse|JsonResponse
    {
        $this->authorizeExportAccess($request, $exportKey);

        $progress = Cache::get(AsyncProfileExportJob::cacheKey($exportKey));

        if (!$progress || $progress['status'] !== 'completed') {
            return response()->json(['error' => 'Export nicht bereit.'], 404);
        }

        $path = $progress['output_path'] ?? null;
        if (!$path || !file_exists($path)) {
            return response()->json(['error' => 'Export-Datei nicht gefunden.'], 404);
        }

        // Path-Traversal verhindern
        $realPath   = realpath($path);
        $allowedDir = storage_path('app/exports');
        if (!$realPath || !str_starts_with($realPath, $allowedDir)) {
            return response()->json(['error' => 'Ungültiger Export-Pfad.'], 403);
        }

        $fileName = $progress['file_name'] ?? 'export';

        // Cache aufräumen
        Cache::forget(AsyncProfileExportJob::cacheKey($exportKey));
        Cache::forget(AsyncProfileExportJob::cacheKey($exportKey) . ':owner');

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * GET /api/v1/export-profiles/{id}/stream — JSON direkt als API-Response streamen.
     */
    public function stream(Request $request, ExportProfile $exportProfile): JsonResponse
    {
        $this->authorize('execute', $exportProfile);

        if ($exportProfile->format !== 'json') {
            return response()->json(['error' => 'Streaming ist nur für JSON-Exporte verfügbar.'], 422);
        }

        return $this->exportService->stream($exportProfile);
    }

    private function authorizeExportAccess(Request $request, string $exportKey): void
    {
        $ownerId = Cache::get(AsyncProfileExportJob::cacheKey($exportKey) . ':owner');
        $userId  = $request->user()?->id;

        if ($ownerId === null || $userId !== $ownerId) {
            abort(403, 'Kein Zugriff auf diesen Export.');
        }
    }

    /**
     * Strip stale attribute references from an export profile
     * so deleted attributes don't cause broken exports.
     */
    private function stripStaleReferences(ExportProfile $profile): ExportProfile
    {
        if (empty($profile->attribute_ids)) {
            return $profile;
        }

        $validIds = Attribute::whereIn('id', $profile->attribute_ids)->pluck('id')->toArray();
        if (count($validIds) !== count($profile->attribute_ids)) {
            $profile->attribute_ids = array_values($validIds);
            $profile->saveQuietly();
        }

        return $profile;
    }
}
