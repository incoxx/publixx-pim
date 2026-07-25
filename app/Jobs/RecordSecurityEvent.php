<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SecurityEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Persistiert ein Security-Event asynchron, damit die Erkennung den Request
 * nicht ausbremst.
 */
class RecordSecurityEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly array $attributes,
    ) {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        SecurityEvent::create($this->attributes);
    }
}
