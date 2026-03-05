<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\ImportJob;
use App\Models\ImportLog;
use Illuminate\Support\Str;

/**
 * Strukturiertes Logging für Import-Vorgänge.
 *
 * Schreibt sowohl in die import_logs-Tabelle als auch in den Laravel Log-Channel.
 * Ergänzt die bestehenden ImportJobError-Einträge um Info-/Warning-Level und
 * ermöglicht phasenbasiertes Tracking.
 */
class ImportLogger
{
    public function info(ImportJob $job, string $phase, string $message, array $context = []): void
    {
        $this->log($job, 'info', $phase, $message, null, null, null, $context);
    }

    public function warning(ImportJob $job, string $phase, string $message, array $context = []): void
    {
        $this->log($job, 'warning', $phase, $message, null, null, null, $context);
    }

    public function error(ImportJob $job, string $phase, string $message, array $context = []): void
    {
        $this->log($job, 'error', $phase, $message, null, null, null, $context);
    }

    public function logRow(
        ImportJob $job,
        string $phase,
        string $level,
        string $sheet,
        int $row,
        ?string $column,
        string $message,
        array $context = [],
    ): void {
        $this->log($job, $level, $phase, $message, $sheet, $row, $column, $context);
    }

    private function log(
        ImportJob $job,
        string $level,
        string $phase,
        string $message,
        ?string $sheet,
        ?int $row,
        ?string $column,
        array $context,
    ): void {
        ImportLog::create([
            'id' => Str::uuid()->toString(),
            'import_job_id' => $job->id,
            'level' => $level,
            'phase' => $phase,
            'sheet' => $sheet,
            'row' => $row,
            'column' => $column,
            'message' => $message,
            'context' => !empty($context) ? $context : null,
        ]);
    }
}
