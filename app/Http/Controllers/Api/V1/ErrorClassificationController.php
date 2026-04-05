<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ErrorClassification;
use App\Services\ErrorClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErrorClassificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $query = ErrorClassification::query()->with('reviewer:id,name');

        // Filter
        $filters = $request->query('filter', []);

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['classification'])) {
            $query->where('classification', $filters['classification']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        $search = $request->query('search');
        if ($search) {
            $escaped = addcslashes($search, '%_');
            $query->where(function ($q) use ($escaped) {
                $q->where('exception_class', 'LIKE', "%{$escaped}%")
                    ->orWhere('file', 'LIKE', "%{$escaped}%")
                    ->orWhere('message', 'LIKE', "%{$escaped}%")
                    ->orWhere('ai_title', 'LIKE', "%{$escaped}%");
            });
        }

        // Sortierung: critical zuerst, dann nach Häufigkeit
        $sortField = $request->query('sort', 'last_seen_at');
        $sortOrder = $request->query('order', 'desc');
        $allowedSorts = ['last_seen_at', 'occurrence_count', 'severity', 'created_at'];

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->query('per_page', 50), 200);

        return response()->json(
            $query->paginate($perPage)
        );
    }

    public function classify(Request $request, ErrorClassificationService $service): JsonResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'limit'   => 'sometimes|integer|min:1|max:200',
            'dry_run' => 'sometimes|boolean',
        ]);

        $result = $service->parseAndClassify(
            limit: (int) $request->input('limit', 50),
            dryRun: (bool) $request->input('dry_run', false),
        );

        return response()->json($result);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeAdmin($request);

        $record = ErrorClassification::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|string|in:new,reviewed,forwarded,resolved',
            'notes'  => 'sometimes|nullable|string|max:5000',
        ]);

        $updates = $validated;

        if (isset($updates['status']) && $updates['status'] !== 'new') {
            $updates['reviewed_by'] = $request->user()->id;
            $updates['reviewed_at'] = now();
        }

        $record->update($updates);

        return response()->json($record->fresh(['reviewer:id,name']));
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $filters = $request->query('filter', []);

        if (! empty($filters['status'])) {
            ErrorClassification::where('status', $filters['status'])->delete();
        } elseif (! empty($filters['older_than_days'])) {
            $days = (int) $filters['older_than_days'];
            ErrorClassification::where('last_seen_at', '<', now()->subDays($days))->delete();
        } else {
            ErrorClassification::query()->delete();
        }

        return response()->json(null, 204);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('Admin')) {
            abort(403, 'Nur Administratoren dürfen auf die Fehlerklassifikation zugreifen.');
        }
    }
}
