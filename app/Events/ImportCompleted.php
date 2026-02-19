<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: Import wurde erfolgreich abgeschlossen.
 *
 * Konsumenten:
 * - Agent 9 (Performance): Wärmt Cache auf, aktualisiert Search-Index
 */
class ImportCompleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        /** Import-Job UUID. */
        public readonly string $importJobId,
        /** @var string[] IDs aller angelegten/aktualisierten Produkte. */
        public readonly array $productIds,
    ) {}
}
