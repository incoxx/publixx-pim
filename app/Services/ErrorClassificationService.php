<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ErrorClassification;
use App\Models\User;
use App\Notifications\CriticalErrorAlert;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErrorClassificationService
{
    // Maximale Anzahl Stack-Trace-Zeilen die an die KI gesendet werden
    private const MAX_STACK_LINES = 20;

    // Zeitraum rückwirkend für Log-Parsing (in Stunden)
    private const LOG_LOOKBACK_HOURS = 24;

    public function parseAndClassify(int $limit = 50, bool $dryRun = false): array
    {
        $errors = array_merge(
            $this->readLogErrors(),
            $this->readFailedJobs(),
        );

        $grouped = $this->groupByFingerprint($errors);
        $processed = 0;
        $classified = 0;

        foreach (array_slice($grouped, 0, $limit) as $group) {
            $processed++;

            if ($dryRun) {
                continue;
            }

            $record = $this->upsertRecord($group);

            // KI-Klassifikation nur wenn neu oder noch nicht klassifiziert
            if ($record->wasRecentlyCreated || $record->classified_at === null) {
                $this->classifyWithClaude($record);
                $classified++;

                // Sysadmin-User bei kritischen/hohen Fehlern benachrichtigen
                if (in_array($record->severity, ['critical', 'high'], true)) {
                    $this->notifySysadmins($record);
                }
            } else {
                // Schweregrad bei neuer Häufigkeit neu berechnen
                $newSeverity = ErrorClassification::computeSeverity(
                    $record->occurrence_count,
                    $record->classification,
                );
                if ($newSeverity !== $record->severity) {
                    $record->update(['severity' => $newSeverity]);
                }
            }
        }

        return [
            'errors_found'  => count($errors),
            'groups'        => count($grouped),
            'processed'     => $processed,
            'classified'    => $classified,
            'dry_run'       => $dryRun,
        ];
    }

    /**
     * Liest ERROR/CRITICAL-Einträge aus dem Laravel-Log.
     */
    private function readLogErrors(): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            return [];
        }

        $cutoff = now()->subHours(self::LOG_LOOKBACK_HOURS);
        $errors = [];

        $handle = fopen($logPath, 'r');
        if (! $handle) {
            return [];
        }

        $currentEntry = null;

        while (($line = fgets($handle)) !== false) {
            // Neuer Log-Eintrag beginnt mit Zeitstempel: [YYYY-MM-DD HH:MM:SS]
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] [\w.-]+\.(ERROR|CRITICAL|EMERGENCY|ALERT):(.+)/', $line, $m)) {
                // Vorherigen Eintrag speichern
                if ($currentEntry) {
                    $errors[] = $currentEntry;
                }

                $timestamp = Carbon::parse($m[1]);
                if ($timestamp->lt($cutoff)) {
                    $currentEntry = null;
                    continue;
                }

                $currentEntry = [
                    'timestamp' => $timestamp,
                    'level'     => $m[2],
                    'raw'       => trim($m[3]),
                    'source'    => 'log',
                    'lines'     => [],
                ];
            } elseif ($currentEntry !== null) {
                $currentEntry['lines'][] = rtrim($line);
            }
        }

        if ($currentEntry) {
            $errors[] = $currentEntry;
        }

        fclose($handle);

        return array_map([$this, 'parseLogEntry'], $errors);
    }

    /**
     * Liest fehlgeschlagene Queue-Jobs aus der failed_jobs-Tabelle.
     */
    private function readFailedJobs(): array
    {
        $cutoff = now()->subHours(self::LOG_LOOKBACK_HOURS);

        return DB::table('failed_jobs')
            ->where('failed_at', '>=', $cutoff)
            ->orderBy('failed_at', 'desc')
            ->limit(200)
            ->get()
            ->map(function ($job) {
                $exceptionText = $job->exception ?? '';
                $parsed = $this->parseExceptionText($exceptionText);

                return [
                    'timestamp'       => Carbon::parse($job->failed_at),
                    'exception_class' => $parsed['exception_class'],
                    'message'         => $parsed['message'],
                    'file'            => $parsed['file'],
                    'line'            => $parsed['line'],
                    'stack_trace'     => $parsed['stack_trace'],
                    'source'          => 'queue',
                ];
            })
            ->filter(fn ($e) => $e['exception_class'] !== 'Unknown')
            ->values()
            ->toArray();
    }

    /**
     * Parst einen rohen Log-Eintrag in ein strukturiertes Array.
     */
    private function parseLogEntry(array $entry): array
    {
        $raw = $entry['raw'];

        // JSON-Kontext extrahieren (am Ende der Zeile: {...})
        $jsonContext = null;
        if (preg_match('/\{.+\}$/', $raw, $jm)) {
            $decoded = json_decode($jm[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $jsonContext = $decoded;
            }
        }

        // Exception-Klasse aus dem Rohtext
        $exceptionClass = 'Unknown';
        if (preg_match('/^([\w\\\\]+Exception[\w\\\\]*|[\w\\\\]+Error[\w\\\\]*)/', $raw, $em)) {
            $exceptionClass = $em[1];
        }

        // Fehlermeldung (erster Satz)
        $message = preg_replace('/\s*\{.+\}$/', '', $raw);
        $message = mb_substr($message, 0, 500);

        // Stack Trace aus JSON-Kontext oder Folgezeilen
        $stackTrace = null;
        $file = 'unknown';
        $line = 0;

        if (isset($jsonContext['exception'])) {
            $parsed = $this->parseExceptionText($jsonContext['exception']);
            $exceptionClass = $parsed['exception_class'] ?: $exceptionClass;
            $file = $parsed['file'];
            $line = $parsed['line'];
            $stackTrace = $parsed['stack_trace'];
        } elseif (! empty($entry['lines'])) {
            $traceLines = array_slice($entry['lines'], 0, self::MAX_STACK_LINES);
            $stackTrace = implode("\n", $traceLines);

            // Datei + Zeile aus erstem #0-Frame
            if (preg_match('/#0 ([^(]+)\((\d+)\)/', $stackTrace, $fm)) {
                $file = trim($fm[1]);
                $line = (int) $fm[2];
            }
        }

        return [
            'timestamp'       => $entry['timestamp'],
            'exception_class' => $exceptionClass,
            'message'         => $message,
            'file'            => $this->normalizePath($file),
            'line'            => $line,
            'stack_trace'     => $stackTrace,
            'source'          => $entry['source'],
        ];
    }

    /**
     * Parst einen Exception-Text-String (wie er in failed_jobs.exception steht).
     */
    private function parseExceptionText(string $text): array
    {
        $lines = explode("\n", $text);
        $first = $lines[0] ?? '';

        // Format: "ExceptionClass: Meldung in /path/file.php:123"
        $exceptionClass = 'Unknown';
        $message = $first;
        $file = 'unknown';
        $line = 0;

        if (preg_match('/^([\w\\\\]+):\s*(.+?) in (.+?):(\d+)/', $first, $m)) {
            $exceptionClass = $m[1];
            $message = $m[2];
            $file = $m[3];
            $line = (int) $m[4];
        } elseif (preg_match('/^([\w\\\\]+):\s*(.+)/', $first, $m)) {
            $exceptionClass = $m[1];
            $message = $m[2];
        }

        // Stack Trace kürzen
        $traceLines = array_filter(
            array_slice($lines, 1, self::MAX_STACK_LINES),
            fn ($l) => trim($l) !== '',
        );
        $stackTrace = implode("\n", $traceLines) ?: null;

        return [
            'exception_class' => $exceptionClass,
            'message'         => mb_substr($message, 0, 500),
            'file'            => $this->normalizePath($file),
            'line'            => $line,
            'stack_trace'     => $stackTrace,
        ];
    }

    /**
     * Gruppiert Fehler nach Fingerprint (exception + file + line).
     * Zählt Häufigkeit, merkt sich first/last seen.
     */
    private function groupByFingerprint(array $errors): array
    {
        $groups = [];

        foreach ($errors as $error) {
            $key = md5(($error['exception_class'] ?? 'Unknown') . '|' . ($error['file'] ?? '') . '|' . ($error['line'] ?? 0));

            if (! isset($groups[$key])) {
                $groups[$key] = $error;
                $groups[$key]['count'] = 1;
            } else {
                $groups[$key]['count']++;
                // first_seen = ältester Timestamp
                if ($error['timestamp']->lt($groups[$key]['timestamp'])) {
                    $groups[$key]['timestamp'] = $error['timestamp'];
                }
            }
        }

        // Nach Häufigkeit sortieren (häufigste zuerst)
        uasort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_values($groups);
    }

    /**
     * Legt einen neuen Klassifikations-Datensatz an oder aktualisiert die Häufigkeit.
     */
    private function upsertRecord(array $group): ErrorClassification
    {
        $exceptionClass = $group['exception_class'] ?? 'Unknown';
        $file = $group['file'] ?? 'unknown';
        $line = (int) ($group['line'] ?? 0);
        $count = (int) ($group['count'] ?? 1);

        $existing = ErrorClassification::where('exception_class', $exceptionClass)
            ->where('file', $file)
            ->where('line', $line)
            ->first();

        if ($existing) {
            $newCount = $existing->occurrence_count + $count;
            $existing->update([
                'occurrence_count' => $newCount,
                'last_seen_at'     => $group['timestamp'],
                'message'          => $group['message'] ?? $existing->message,
                'source'           => $group['source'] ?? $existing->source,
            ]);
            $existing->wasRecentlyCreated = false;

            return $existing;
        }

        $record = ErrorClassification::create([
            'exception_class'  => $exceptionClass,
            'file'             => $file,
            'line'             => $line,
            'occurrence_count' => $count,
            'first_seen_at'    => $group['timestamp'],
            'last_seen_at'     => $group['timestamp'],
            'message'          => $group['message'] ?? '',
            'stack_trace'      => $group['stack_trace'] ?? null,
            'source'           => $group['source'] ?? 'log',
        ]);

        return $record;
    }

    /**
     * Ruft die Claude-API für die Klassifikation auf.
     */
    private function classifyWithClaude(ErrorClassification $record): void
    {
        $apiKey = config('connectors.claude_ai.api_key', '');

        if (empty($apiKey)) {
            Log::warning('ErrorClassificationService: Kein CLAUDE_AI_API_KEY konfiguriert.');

            return;
        }

        $userPrompt = $this->buildTriagePrompt($record);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
                ->timeout(30)
                ->post(config('connectors.claude_ai.base_url', 'https://api.anthropic.com/v1') . '/messages', [
                    'model'      => config('connectors.claude_ai.model', 'claude-sonnet-4-6'),
                    'max_tokens' => 512,
                    'system'     => $this->systemPrompt(),
                    'messages'   => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            $response->throw();
            $data = $response->json();
            $text = $data['content'][0]['text'] ?? '';

            $result = $this->parseClaudeResponse($text);

            if ($result) {
                $severity = ErrorClassification::computeSeverity(
                    $record->occurrence_count,
                    $result['classification'],
                );

                $record->update([
                    'classification' => $result['classification'],
                    'severity'       => $severity,
                    'ai_title'       => $result['title'] ?? null,
                    'ai_description' => $result['description'] ?? null,
                    'ai_hint'        => $result['hint'] ?? null,
                    'ai_confidence'  => $result['confidence'] ?? null,
                    'classified_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('ErrorClassificationService: KI-Klassifikation fehlgeschlagen.', [
                'error' => $e->getMessage(),
                'record_id' => $record->id,
            ]);
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Du bist ein Laravel-Fehler-Analyst für ein B2B-PIM-System (Produktinformationsmanagement).
Analysiere den Fehler und erstelle einen verständlichen Bericht für ein technisches Support-Team.

Klassifiziere als:
- "real_bug": Fehler tritt bei valider Nutzeraktion auf. Code-Logik ist fehlerhaft. Fix nötig.
- "user_error": Fehler ist direkte Folge einer ungültigen Nutzereingabe oder fehlenden Ressource. Kein Code-Fix nötig.
- "uncertain": Nicht eindeutig klassifizierbar.

Antworte NUR als gültiges JSON ohne Markdown-Wrapper:
{
  "classification": "real_bug|user_error|uncertain",
  "confidence": 0.0-1.0,
  "title": "Kurztitel in max 80 Zeichen, verständlich für Support-Team",
  "description": "Was genau ist passiert? Wo im System? Für wen verständlich (kein Code-Jargon).",
  "hint": "Mögliche Ursache oder was geprüft werden sollte."
}
PROMPT;
    }

    private function buildTriagePrompt(ErrorClassification $record): string
    {
        $lines = [];
        $lines[] = "Exception: {$record->exception_class}";
        $lines[] = "Datei: {$record->file}:{$record->line}";
        $lines[] = "Meldung: {$record->message}";
        $lines[] = "Häufigkeit: {$record->occurrence_count}x aufgetreten";
        $lines[] = "Quelle: " . ($record->source === 'queue' ? 'Background-Job (Queue)' : 'HTTP-Request');

        if ($record->stack_trace) {
            $truncated = implode("\n", array_slice(explode("\n", $record->stack_trace), 0, self::MAX_STACK_LINES));
            $lines[] = "\nStack Trace (gekürzt):\n{$truncated}";
        }

        return implode("\n", $lines);
    }

    private function parseClaudeResponse(string $text): ?array
    {
        // JSON aus dem Response extrahieren (auch wenn Markdown-Wrapper vorhanden)
        if (preg_match('/\{[\s\S]+\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Benachrichtigt alle aktiven Sysadmin-User per E-Mail über den klassifizierten Fehler.
     */
    private function notifySysadmins(ErrorClassification $record): void
    {
        $sysadmins = User::role('Sysadmin')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();

        if ($sysadmins->isEmpty()) {
            return;
        }

        foreach ($sysadmins as $user) {
            try {
                $user->notify(new CriticalErrorAlert($record));
            } catch (\Throwable $e) {
                Log::warning('ErrorClassificationService: Sysadmin-Benachrichtigung fehlgeschlagen.', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Kürzt absoluten Server-Pfad auf relativen App-Pfad.
     */
    private function normalizePath(string $path): string
    {
        $base = base_path() . '/';

        return str_starts_with($path, $base)
            ? substr($path, strlen($base))
            : $path;
    }
}
