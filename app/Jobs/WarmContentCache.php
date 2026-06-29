<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Navigation;
use App\Services\Content\ContentCacheWarmer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Wärmt den Content-Cache einer Navigation (async). Wird optional nach dem
 * Veröffentlichen einer Seite ausgelöst (config content.cache.warm_on_publish).
 */
class WarmContentCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string> $langs */
    public function __construct(
        public string $navigationId,
        public array $langs = ['de'],
    ) {}

    public function handle(ContentCacheWarmer $warmer): void
    {
        $navigation = Navigation::find($this->navigationId);
        if ($navigation !== null) {
            $warmer->warmNavigation($navigation, $this->langs);
        }
    }
}
