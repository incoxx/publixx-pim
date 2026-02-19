<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Attribute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AttributeObserver – reagiert auf Änderungen an Attribut-Definitionen.
 *
 * Verantwortlich für:
 * - attributes:all Cache invalidieren
 * - Attribut-View-Caches invalidieren
 */
class AttributeObserver
{
    /**
     * Attribut wurde erstellt.
     */
    public function created(Attribute $attribute): void
    {
        $this->invalidateAttributeCaches($attribute);

        Log::debug('AttributeObserver::created', [
            'attribute_id' => $attribute->id,
            'technical_name' => $attribute->technical_name,
        ]);
    }

    /**
     * Attribut wurde aktualisiert.
     */
    public function updated(Attribute $attribute): void
    {
        $this->invalidateAttributeCaches($attribute);

        Log::debug('AttributeObserver::updated', [
            'attribute_id' => $attribute->id,
            'dirty' => $attribute->getDirty(),
        ]);
    }

    /**
     * Attribut wurde gelöscht.
     */
    public function deleted(Attribute $attribute): void
    {
        $this->invalidateAttributeCaches($attribute);

        Log::debug('AttributeObserver::deleted', [
            'attribute_id' => $attribute->id,
        ]);
    }

    /**
     * Alle Attribut-bezogenen Caches invalidieren.
     */
    private function invalidateAttributeCaches(Attribute $attribute): void
    {
        try {
            // Globaler Attribut-Cache (alle Definitionen)
            Cache::forget('attributes:all');

            // Tag-basierter Cache für Attribut-Views
            Cache::tags(['attributes'])->flush();
        } catch (\Throwable $e) {
            Log::warning('AttributeObserver: Cache flush failed', [
                'attribute_id' => $attribute->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
