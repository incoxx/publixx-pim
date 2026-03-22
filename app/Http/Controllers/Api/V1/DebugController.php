<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DebugController extends Controller
{
    public function logs(Request $request): Response
    {
        $channel = $request->query('channel', 'laravel');
        $lines = min((int) $request->query('lines', 500), 5000);

        $allowedChannels = ['laravel', 'import'];
        if (! in_array($channel, $allowedChannels, true)) {
            return response('Unknown channel: ' . $channel, 400)
                ->header('Content-Type', 'text/plain');
        }

        $path = storage_path("logs/{$channel}.log");

        if (! file_exists($path)) {
            return response("(empty — no log entries yet)\n", 200)
                ->header('Content-Type', 'text/plain');
        }

        // Read last N lines efficiently
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

        return response(implode('', $output))
            ->header('Content-Type', 'text/plain');
    }

    public function clearLogs(Request $request): Response
    {
        $channel = $request->query('channel', 'laravel');

        $allowedChannels = ['laravel', 'import'];
        if (! in_array($channel, $allowedChannels, true)) {
            return response('Unknown channel: ' . $channel, 400)
                ->header('Content-Type', 'text/plain');
        }

        $path = storage_path("logs/{$channel}.log");

        if (! file_exists($path)) {
            return response("Nothing to clear — no log file yet.\n", 200)
                ->header('Content-Type', 'text/plain');
        }

        file_put_contents($path, '');

        return response("Cleared: {$channel}.log")
            ->header('Content-Type', 'text/plain');
    }
}
