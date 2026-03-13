<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Jobs\WriteAuditLog;

/**
 * Provides audit logging helpers for controllers.
 *
 * Call $this->audit(...) from any controller method to log a change
 * to the audit_logs table via a queued job.
 */
trait AuditsChanges
{
    protected function audit(
        string $action,
        string $auditableType,
        string $auditableId,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        $request = request();

        WriteAuditLog::dispatch(
            auditableType: $auditableType,
            auditableId: $auditableId,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            userId: $request->user()?->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        )->afterCommit();
    }
}
