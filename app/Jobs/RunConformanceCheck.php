<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\Conformance\ProfileConformanceChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Prüft ein einzelnes Produkt gegen sein Referenz-Profil (asynchron).
 *
 * Wird u.a. beim Speichern (on-save) über Product-/AttributeValue-Observer
 * ausgelöst. Implementiert ShouldBeUnique pro Produkt: Werden bei einem Bulk-
 * Save viele Attributwerte gespeichert (→ viele Dispatches), läuft trotzdem nur
 * EIN Check pro Produkt statt eines pro Attributzeile.
 */
class RunConformanceCheck implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15];

    /** Eindeutigkeits-Fenster (Sekunden), bis der Lock spätestens freigegeben wird. */
    public int $uniqueFor = 120;

    public function __construct(
        public string $productId,
        public string $trigger = 'save',
    ) {
    }

    /**
     * Eindeutig pro Produkt → bündelt parallele Dispatches.
     */
    public function uniqueId(): string
    {
        return $this->productId;
    }

    public function handle(ProfileConformanceChecker $checker): void
    {
        $product = Product::find($this->productId);

        // Nur Produkte mit referenziertem Profil sind relevant
        if (!$product || $product->reference_profile_id === null) {
            return;
        }

        try {
            $checker->check($product, $this->trigger);
        } catch (\Throwable $e) {
            Log::warning('RunConformanceCheck failed', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
