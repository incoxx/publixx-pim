<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\ErrorClassification;
use App\Models\User;
use App\Notifications\ForwardedErrorNotification;
use App\Services\ErrorClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);

        $query = ErrorClassification::query()->with('reviewer:id,name');

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

        $sortField = $request->query('sort', 'last_seen_at');
        $sortOrder = $request->query('order', 'desc');
        $allowedSorts = ['last_seen_at', 'occurrence_count', 'severity', 'created_at'];

        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $records = $query->get();

        $severityLabels = [
            'critical' => 'Kritisch',
            'high'     => 'Hoch',
            'medium'   => 'Mittel',
            'low'      => 'Niedrig',
        ];
        $classificationLabels = [
            'real_bug'   => 'Echter Bug',
            'user_error' => 'Nutzerfehler',
            'uncertain'  => 'Unsicher',
        ];
        $statusLabels = [
            'new'        => 'Neu',
            'reviewed'   => 'Geprüft',
            'forwarded'  => 'Weitergeleitet',
            'resolved'   => 'Erledigt',
        ];
        $sourceLabels = [
            'log'   => 'Log',
            'queue' => 'Queue-Job',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Fehlerklassifikation');

        $headers = [
            'Schweregrad', 'Titel', 'Exception-Klasse', 'Datei', 'Zeile',
            'Häufigkeit', 'Zuletzt gesehen', 'Zuerst gesehen',
            'Klassifikation', 'Status', 'Quelle',
            'KI-Beschreibung', 'KI-Hinweis', 'KI-Konfidenz (%)',
            'Notizen', 'Geprüft von',
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $row = 2;
        foreach ($records as $record) {
            $sheet->setCellValue([1,  $row], $severityLabels[$record->severity] ?? $record->severity ?? '');
            $sheet->setCellValue([2,  $row], $record->ai_title ?: $record->exception_class);
            $sheet->setCellValue([3,  $row], $record->exception_class);
            $sheet->setCellValue([4,  $row], $record->file);
            $sheet->setCellValue([5,  $row], $record->line);
            $sheet->setCellValue([6,  $row], $record->occurrence_count);
            $sheet->setCellValue([7,  $row], $record->last_seen_at?->format('d.m.Y H:i') ?? '');
            $sheet->setCellValue([8,  $row], $record->first_seen_at?->format('d.m.Y H:i') ?? '');
            $sheet->setCellValue([9,  $row], $classificationLabels[$record->classification] ?? '');
            $sheet->setCellValue([10, $row], $statusLabels[$record->status] ?? $record->status);
            $sheet->setCellValue([11, $row], $sourceLabels[$record->source] ?? $record->source ?? '');
            $sheet->setCellValue([12, $row], $record->ai_description ?? '');
            $sheet->setCellValue([13, $row], $record->ai_hint ?? '');
            $sheet->setCellValue([14, $row], $record->ai_confidence !== null ? (int) round($record->ai_confidence * 100) : '');
            $sheet->setCellValue([15, $row], $record->notes ?? '');
            $sheet->setCellValue([16, $row], $record->reviewer?->name ?? '');
            $row++;
        }

        $fileName = 'fehler-export-' . date('Y-m-d') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json([
            'has_claude_api_key' => ! empty(config('connectors.claude_ai.api_key')),
        ]);
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

        if (($updates['status'] ?? null) === 'forwarded') {
            $this->notifyForwarded($record->fresh(['reviewer:id,name']), $request->user());
        }

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

    private function notifyForwarded(ErrorClassification $record, User $forwardedBy): void
    {
        $notification = new ForwardedErrorNotification($record, $forwardedBy);

        // E-Mail an konfigurierbare Entwickler-Adresse
        $email = config('services.error_forward.email');
        if ($email) {
            try {
                Notification::route('mail', $email)->notify($notification);
            } catch (\Throwable $e) {
                Log::warning('Weiterleiten: E-Mail fehlgeschlagen.', ['error' => $e->getMessage()]);
            }
        }

        // Slack Incoming Webhook
        $webhook = config('services.error_forward.slack_webhook');
        if ($webhook) {
            $title    = $record->ai_title ?? $record->exception_class;
            $severity = match ($record->severity) {
                'critical' => '🔴',
                'high'     => '🟠',
                'medium'   => '🟡',
                'low'      => '🟢',
                default    => '',
            };
            $url = rtrim(config('app.url'), '/') . '/errors';

            $blocks = [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "{$severity} *<{$url}|{$title}>*\nWeitergeleitet von *{$forwardedBy->name}*",
                    ],
                ],
                [
                    'type'   => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Exception:*\n`{$record->exception_class}`"],
                        ['type' => 'mrkdwn', 'text' => "*Häufigkeit:*\n{$record->occurrence_count}x"],
                        ['type' => 'mrkdwn', 'text' => "*Datei:*\n`{$record->file}:{$record->line}`"],
                        ['type' => 'mrkdwn', 'text' => '*Zuletzt gesehen:*\n' . ($record->last_seen_at?->format('d.m.Y H:i') ?? '–')],
                    ],
                ],
            ];

            if ($record->ai_hint) {
                $blocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*Hinweis:* {$record->ai_hint}"]];
            }

            if ($record->notes) {
                $blocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*Admin-Notiz:* {$record->notes}"]];
            }

            try {
                Http::post($webhook, [
                    'text'   => "{$severity} Fehler weitergeleitet von {$forwardedBy->name}: {$title}",
                    'blocks' => $blocks,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Weiterleiten: Slack fehlgeschlagen.', ['error' => $e->getMessage()]);
            }
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('Admin')) {
            abort(403, 'Nur Administratoren dürfen auf die Fehlerklassifikation zugreifen.');
        }
    }
}
