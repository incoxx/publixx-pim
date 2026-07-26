<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class WriteAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly string $auditableType,
        public readonly string $auditableId,
        public readonly string $action,
        public readonly ?array $oldValues = null,
        public readonly ?array $newValues = null,
        public readonly ?string $userId = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $productVersionId = null,
    ) {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        AuditLog::create([
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'product_version_id' => $this->productVersionId,
            'action' => $this->action,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'user_id' => $this->userId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'created_at' => now(),
        ]);
    }
}
