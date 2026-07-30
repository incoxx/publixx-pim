<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

/**
 * Server-Sent-Events-Helfer für lang laufende Importe/Exporte mit Fortschrittsanzeige.
 *
 * Ursprünglich in BmecatImportController; extrahiert, da JsonExportImportController
 * denselben SSE-Progress/Cancel-Mechanismus für den JSON-Import benötigt.
 */
trait HandlesSseProgress
{
    /**
     * Konfiguriert PHP für lang laufende Streaming-Requests.
     */
    private function configureForLongRunning(string $memoryLimit): void
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', $memoryLimit);
        if (function_exists('fastcgi_finish_request')) {
            ignore_user_abort(true);
        }

        // Output Buffering deaktivieren für Echtzeit-Streaming.
        // In Tests faengt TestResponse::streamedContent() den Output ueber einen
        // eigenen ob_start()-Handler ab; ein Drainen aller Buffer-Level wuerde
        // dessen Puffer vorzeitig zerstoeren ("Failed to delete buffer").
        if (! app()->runningUnitTests()) {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
        }
    }

    /**
     * Erstellt eine SSE-Event-Sender-Funktion.
     */
    private function createSseEventSender(): \Closure
    {
        $lastEventTime = microtime(true);

        return function (string $event, array $data) use (&$lastEventTime) {
            echo "event: {$event}\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
            $lastEventTime = microtime(true);
        };
    }

    /**
     * Erstellt eine SSE-Heartbeat-Funktion.
     */
    private function createSseHeartbeatSender(): \Closure
    {
        $lastEventTime = microtime(true);

        return function () use (&$lastEventTime) {
            if ((microtime(true) - $lastEventTime) > 15) {
                echo ": heartbeat\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                $lastEventTime = microtime(true);
            }
        };
    }
}
