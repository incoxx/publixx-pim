<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Jobs\ExecuteTranslationJob;
use App\Models\TranslationJob;
use App\Services\TranslationJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationJobController extends Controller
{
    private const SUPPORTED_LANGUAGES = ['de', 'en', 'fr', 'it', 'es', 'nl', 'pl', 'pt', 'cs', 'da', 'sv', 'bg', 'el', 'et', 'fi', 'hu', 'id', 'ja', 'ko', 'lt', 'lv', 'nb', 'ro', 'ru', 'sk', 'sl', 'tr', 'uk', 'zh'];

    public function __construct(
        protected TranslationJobService $service,
    ) {}

    /**
     * GET /api/v1/translation-jobs
     */
    public function index(Request $request): JsonResponse
    {
        $query = TranslationJob::with('createdBy:id,name,email')
            ->where('created_by', $request->user()->id)
            ->recent();

        if ($status = $request->input('status')) {
            $query->byStatus($status);
        }

        $jobs = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'total' => $jobs->total(),
                'per_page' => $jobs->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/translation-jobs
     */
    public function store(Request $request): JsonResponse
    {
        $languageRule = 'required|string|in:' . implode(',', self::SUPPORTED_LANGUAGES);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'source_language' => $languageRule,
            'target_language' => $languageRule . '|different:source_language',
            'scope' => 'required|string|in:products,system,mixed',
            'product_ids' => 'required_if:scope,products,mixed|array|min:1',
            'product_ids.*' => 'string|uuid|distinct',
            'attribute_ids' => 'required_if:scope,products,mixed|array|min:1',
            'attribute_ids.*' => 'string|uuid|distinct',
            'entity_types' => 'required_if:scope,system,mixed|array|min:1',
            'entity_types.*' => 'string|in:attribute,attribute_type,value_list_entry,value_list,hierarchy_node,hierarchy,product_type,unit_group,price_type,relation_type',
            'filters' => 'nullable|array',
        ]);

        $userId = $request->user()->id;

        $job = match ($validated['scope']) {
            'products' => $this->service->createFromProducts(
                $validated['name'],
                $validated['product_ids'],
                $validated['attribute_ids'],
                $validated['source_language'],
                $validated['target_language'],
                $userId,
                $validated['filters'] ?? null,
            ),
            'system' => $this->service->createFromSystem(
                $validated['name'],
                $validated['entity_types'],
                $validated['source_language'],
                $validated['target_language'],
                $userId,
            ),
            'mixed' => $this->service->createMixed(
                $validated['name'],
                $validated['product_ids'] ?? [],
                $validated['attribute_ids'] ?? [],
                $validated['entity_types'] ?? [],
                $validated['source_language'],
                $validated['target_language'],
                $userId,
                $validated['filters'] ?? null,
            ),
        };

        $job->load('createdBy:id,name,email');

        return response()->json(['data' => $job], 201);
    }

    /**
     * GET /api/v1/translation-jobs/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $job = TranslationJob::with('createdBy:id,name,email')->findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        $itemsQuery = $job->items();

        if ($status = $request->input('item_status')) {
            $itemsQuery->where('status', $status);
        }

        $items = $itemsQuery->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => $job,
            'items' => $items->items(),
            'items_meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
                'per_page' => $items->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/translation-jobs/{id}/submit
     */
    public function submit(string $id, Request $request): JsonResponse
    {
        $job = TranslationJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        if (!in_array($job->status, ['draft', 'failed'])) {
            return response()->json(['message' => 'Job kann nur im Status "Entwurf" oder "Fehlgeschlagen" abgesendet werden.'], 422);
        }

        $apiKey = $this->service->getDeepLApiKey();
        if (!$apiKey) {
            return response()->json(['message' => 'Keine aktive DeepL-Verbindung gefunden.'], 422);
        }

        $job->update([
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        ExecuteTranslationJob::dispatch($job);

        return response()->json(['data' => $job->fresh()]);
    }

    /**
     * POST /api/v1/translation-jobs/{id}/approve
     */
    public function approve(string $id, Request $request): JsonResponse
    {
        $job = TranslationJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        if ($job->status !== 'completed') {
            return response()->json(['message' => 'Nur abgeschlossene Jobs können freigegeben werden.'], 422);
        }

        $imported = $this->service->importTranslations($job);

        return response()->json([
            'message' => "{$imported} Übersetzungen importiert.",
            'imported_count' => $imported,
        ]);
    }

    /**
     * POST /api/v1/translation-jobs/{id}/cancel
     */
    public function cancel(string $id, Request $request): JsonResponse
    {
        $job = TranslationJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        if (in_array($job->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Job kann nicht abgebrochen werden.'], 422);
        }

        $job->update(['status' => 'cancelled']);

        return response()->json(['data' => $job->fresh()]);
    }

    /**
     * POST /api/v1/translation-jobs/{id}/retry
     */
    public function retry(string $id, Request $request): JsonResponse
    {
        $job = TranslationJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        if ($job->status !== 'failed') {
            return response()->json(['message' => 'Nur fehlgeschlagene Jobs können erneut gestartet werden.'], 422);
        }

        $apiKey = $this->service->getDeepLApiKey();
        if (!$apiKey) {
            return response()->json(['message' => 'Keine aktive DeepL-Verbindung gefunden.'], 422);
        }

        // Reset failed items to pending
        $job->items()->where('status', 'failed')->update(['status' => 'pending', 'error_message' => null]);

        $job->update([
            'status' => 'pending',
            'failed_items' => 0,
        ]);

        ExecuteTranslationJob::dispatch($job);

        return response()->json(['data' => $job->fresh()]);
    }

    /**
     * DELETE /api/v1/translation-jobs/{id}
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $job = TranslationJob::findOrFail($id);
        $this->authorizeJobAccess($request, $job);

        if ($job->status === 'in_progress') {
            return response()->json(['message' => 'Laufende Jobs können nicht gelöscht werden.'], 422);
        }

        $job->delete();

        return response()->json(null, 204);
    }

    /**
     * Verify the current user owns the job (or is Admin).
     */
    private function authorizeJobAccess(Request $request, TranslationJob $job): void
    {
        $user = $request->user();
        if ($user->hasRole('Admin')) {
            return;
        }
        if ($job->created_by !== $user->id) {
            abort(403, 'Kein Zugriff auf diesen Übersetzungsjob.');
        }
    }
}
