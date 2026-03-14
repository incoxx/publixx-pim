<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAuditController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAdmin($request);

        $query = AuditLog::query()
            ->where('auditable_type', User::class)
            ->with('user:id,name,email');

        $this->applyUserAuditFilters($query, $request);
        $this->applySorting($query, $request, 'created_at', 'desc');

        return AuditLogResource::collection(
            $query->paginate($this->getPerPage($request))
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);

        $query = AuditLog::query()
            ->where('auditable_type', User::class)
            ->with('user:id,name,email');

        $this->applyUserAuditFilters($query, $request);
        $query->orderBy('created_at', 'desc');

        $filename = 'user-audit-log-' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Datum', 'Benutzer', 'Aktion', 'Benutzer-ID', 'Alte Werte', 'Neue Werte', 'IP-Adresse']);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->created_at?->toISOString(),
                        $log->user?->name ?? '',
                        $log->action,
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

        $query = AuditLog::where('auditable_type', User::class);

        if (!empty($filters['date_before'])) {
            try {
                $dateBefore = \Carbon\Carbon::parse($filters['date_before']);
            } catch (\Throwable) {
                abort(422, 'Ungültiges Datumsformat für date_before.');
            }
            $query->where('created_at', '<', $dateBefore);
        }

        $query->delete();

        return response()->json(null, 204);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('Admin')) {
            abort(403, 'Nur Administratoren dürfen auf den Benutzer-Audit-Trail zugreifen.');
        }
    }

    private function applyUserAuditFilters($query, Request $request): void
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
    }
}
