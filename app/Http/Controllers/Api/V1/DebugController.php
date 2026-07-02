<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DebugController extends Controller
{
    private const ALLOWED_CHANNELS = ['laravel', 'import', 'export', 'artisan-cockpit'];

    public function logs(Request $request): Response
    {
        $channel = $request->query('channel', 'laravel');
        $lines = min((int) $request->query('lines', 500), 5000);

        if (! in_array($channel, self::ALLOWED_CHANNELS, true)) {
            return response('Unknown channel: ' . $channel, 400)
                ->header('Content-Type', 'text/plain');
        }

        $path = $this->resolveLogPath($channel);

        if ($path === null) {
            return response("(empty — no log entries yet)\n", 200)
                ->header('Content-Type', 'text/plain');
        }

        $rawLines = $this->readTail($path, $lines);

        return response(implode('', $rawLines))
            ->header('Content-Type', 'text/plain');
    }

    public function parsedLogs(Request $request): JsonResponse
    {
        $channel = $request->query('channel', 'laravel');
        $lines = min((int) $request->query('lines', 500), 5000);
        $levelFilter = strtoupper((string) $request->query('level', ''));
        $search = (string) $request->query('search', '');

        if (! in_array($channel, self::ALLOWED_CHANNELS, true)) {
            return response()->json(['error' => 'Unknown channel: ' . $channel], 400);
        }

        $path = $this->resolveLogPath($channel);

        if ($path === null) {
            return response()->json([
                'entries' => [],
                'meta' => [
                    'channel' => $channel,
                    'total_entries' => 0,
                    'file_size' => 0,
                    'file_size_human' => '0 B',
                ],
            ]);
        }

        $fileSize = filesize($path);
        $rawLines = $this->readTail($path, $lines);

        // Parse log entries
        $entries = [];
        $current = null;
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \S+\.(\w+): (.+)$/';

        foreach ($rawLines as $line) {
            $line = rtrim($line, "\n\r");
            if ($line === '') {
                continue;
            }

            if (preg_match($pattern, $line, $m)) {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = [
                    'timestamp' => $m[1],
                    'level' => strtoupper($m[2]),
                    'message' => $m[3],
                    'stack_trace' => '',
                ];
            } elseif ($current !== null) {
                $current['stack_trace'] .= ($current['stack_trace'] !== '' ? "\n" : '') . $line;
            }
        }
        if ($current !== null) {
            $entries[] = $current;
        }

        // Filter by level
        if ($levelFilter !== '') {
            $entries = array_filter($entries, fn (array $e) => $e['level'] === $levelFilter);
        }

        // Filter by search
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $entries = array_filter($entries, fn (array $e) => str_contains(mb_strtolower($e['message']), $searchLower));
        }

        $entries = array_values($entries);

        // Newest first
        $entries = array_reverse($entries);

        return response()->json([
            'entries' => $entries,
            'meta' => [
                'channel' => $channel,
                'total_entries' => count($entries),
                'file_size' => $fileSize,
                'file_size_human' => $this->humanFileSize((int) $fileSize),
            ],
        ]);
    }

    public function clearLogs(Request $request): Response
    {
        $channel = $request->query('channel', 'laravel');

        if (! in_array($channel, self::ALLOWED_CHANNELS, true)) {
            return response('Unknown channel: ' . $channel, 400)
                ->header('Content-Type', 'text/plain');
        }

        $path = $this->resolveLogPath($channel);

        if ($path === null) {
            return response("Nothing to clear — no log file yet.\n", 200)
                ->header('Content-Type', 'text/plain');
        }

        file_put_contents($path, '');

        return response('Cleared: ' . basename($path))
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Ermittelt die aktuell aktive Log-Datei für einen Channel.
     *
     * Channels mit `driver => 'single'` (z.B. "laravel") schreiben in eine feste
     * Datei ("laravel.log"). Channels mit `driver => 'daily'` (z.B. "import",
     * "export", "artisan-cockpit") schreiben stattdessen tagesaktuell in
     * "{channel}-YYYY-MM-DD.log" – ohne diese Auflösung würde immer die falsche,
     * nie existierende Datei "{channel}.log" gesucht und der Log wirkte leer.
     */
    private function resolveLogPath(string $channel): ?string
    {
        $exactPath = storage_path("logs/{$channel}.log");
        if (file_exists($exactPath)) {
            return $exactPath;
        }

        $dailyFiles = glob(storage_path("logs/{$channel}-*.log")) ?: [];
        if (empty($dailyFiles)) {
            return null;
        }

        // Dateinamen enthalten das Datum (YYYY-MM-DD), alphabetische Sortierung
        // entspricht damit der chronologischen Reihenfolge.
        sort($dailyFiles);

        return end($dailyFiles);
    }

    /**
     * Letzte N Zeilen einer Datei lesen.
     *
     * @return string[]
     */
    private function readTail(string $path, int $lines): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $output = [];
        $file->seek($startLine);

        while (! $file->eof()) {
            $output[] = $file->current();
            $file->next();
        }

        return $output;
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) $bytes : number_format($size, 1)) . ' ' . $units[$i];
    }
}
