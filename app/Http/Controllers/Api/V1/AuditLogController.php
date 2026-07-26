<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAdmin($request);

        $query = AuditLog::query()->with(['user:id,name,email', 'productVersion:id,version_number,product_id']);

        $this->applyAuditFilters($query, $request);
        $this->applySorting($query, $request, 'created_at', 'desc');

        return AuditLogResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);

        $query = AuditLog::query()->with(['user:id,name,email', 'productVersion:id,version_number,product_id']);
        $this->applyAuditFilters($query, $request);
        $query->orderBy('created_at', 'desc');

        $filename = 'audit-log-' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Datum', 'Benutzer', 'Aktion', 'Typ', 'Objekt-ID', 'Alte Werte', 'Neue Werte', 'IP-Adresse']);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->created_at?->toISOString(),
                        $log->user?->name ?? '',
                        $log->action,
                        $log->auditable_type,
                        $log->auditable_id,
                        $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '',
                        $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '',
                        $log->ip_address ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $filters = $request->query('filter', []);

        if (!empty($filters['date_before'])) {
            try {
                $dateBefore = \Carbon\Carbon::parse($filters['date_before']);
            } catch (\Throwable) {
                abort(422, 'Ungültiges Datumsformat für date_before.');
            }
            AuditLog::where('created_at', '<', $dateBefore)->delete();
        } else {
            AuditLog::query()->delete();
        }

        return response()->json(null, 204);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Admin')) {
            abort(403, 'Nur Administratoren dürfen auf das Änderungsprotokoll zugreifen.');
        }
    }

    private function applyAuditFilters($query, Request $request): void
    {
        $filters = $request->query('filter', []);

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        // SKU search: join products table
        $search = $request->query('search');
        if ($search) {
            $escaped = addcslashes($search, '%_');

            $productIds = Product::where('sku', 'LIKE', "%{$escaped}%")
                ->orWhere('name', 'LIKE', "%{$escaped}%")
                ->pluck('id');

            $query->where(function ($q) use ($productIds, $escaped) {
                $q->whereIn('auditable_id', $productIds)
                    ->orWhere('auditable_id', 'LIKE', "%{$escaped}%");
            });
        }
    }
}
