<?php

declare(strict_types=1);

namespace Tms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tms\Models\TmsUnit;
use Tms\Models\TmsUsage;

class ProcessIngestBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 60];

    /**
     * @param  string[]  $targetLanguages  Vom PIM vorgegebene Zielsprachen.
     *                                     Leer = eigene Konfiguration nutzen.
     */
    public function __construct(
        private readonly array $entities,
        private readonly array $targetLanguages = [],
    ) {
    }

    public function handle(): void
    {
        $targetLangs = !empty($this->targetLanguages)
            ? $this->targetLanguages
            : config('tms.target_languages', ['en', 'fr', 'es', 'it', 'nl']);
        $prefix = config('tms.cache_prefix', 'tms:t:');
        $ttl = config('tms.cache_ttl', 86400);
        $newUnits = 0;
        $queuedForTranslation = 0;

        // Derselbe Quelltext kommt in einem Batch vielfach vor (ein Attributname
        // wiederholt sich über hunderte Entitäten). Ohne diese Sperre würde
        // dieselbe Unit mehrfach eingeplant.
        $alreadyQueued = [];

        foreach ($this->entities as $entity) {
            $entityType = $entity['entity_type'];
            $entityId = $entity['entity_id'];
            $entityLabel = $entity['entity_label'] ?? null;
            $fields = $entity['fields'] ?? [];

            foreach ($fields as $field) {
                $text = $field['text'];
                $lang = $field['lang'];
                $fieldName = $field['field'];
                // Hash-Vertrag mit dem PIM (App\Services\Tms\TmsHash) — nur
                // gemeinsam aenderbar, siehe tests/Unit/HashContractTest.php
                $hash = hash('sha256', $lang . '|' . $text);

                // Derive domain from entity_type
                $domain = $entityType;

                // Upsert TMS Unit
                $unit = TmsUnit::firstOrCreate(
                    ['text_hash' => $hash],
                    [
                        'source_lang' => $lang,
                        'source_text' => $text,
                        'domain' => $domain,
                        'char_count' => mb_strlen($text),
                    ]
                );

                $wasRecentlyCreated = $unit->wasRecentlyCreated;
                $existingTranslations = $wasRecentlyCreated
                    ? collect()
                    : $unit->translations;

                // Upsert Usage
                TmsUsage::updateOrCreate(
                    [
                        'tms_unit_id' => $unit->id,
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                        'field_name' => $fieldName,
                    ],
                    [
                        'entity_label' => $entityLabel,
                        'last_seen_at' => now(),
                    ]
                );

                if ($wasRecentlyCreated) {
                    $newUnits++;
                }

                // Cache fuer vorhandene Uebersetzungen aufwaermen
                foreach ($existingTranslations as $t) {
                    $cacheKey = "{$prefix}{$hash}:{$t->target_lang}";
                    Redis::setex($cacheKey, $ttl, $t->translation);
                }

                // Fehlende Zielsprachen ermitteln und NUR diese uebersetzen.
                //
                // Frueher lief das nur fuer frisch angelegte Units. Damit blieb
                // eine neu hinzugefuegte Zielsprache (z.B. 'fi') dauerhaft leer:
                // beim naechsten Ingest existierten alle Units bereits, es wurde
                // kein einziger TranslateUnitJob ausgeloest — lautlos, der Ingest
                // meldete Erfolg. Jetzt zieht jeder Ingest fehlende Sprachen nach.
                $missingLangs = array_values(array_diff(
                    array_map('trim', $targetLangs),
                    $existingTranslations->pluck('target_lang')->all(),
                    [$lang], // Quellsprache nie in sich selbst uebersetzen
                ));

                if (!empty($missingLangs) && !isset($alreadyQueued[$unit->id])) {
                    $alreadyQueued[$unit->id] = true;
                    TranslateUnitJob::dispatch($unit->id, $missingLangs);
                    $queuedForTranslation++;
                }
            }
        }

        Log::info("TMS ingest batch processed: {$newUnits} new units, {$queuedForTranslation} units queued for translation, from " . count($this->entities) . " entities.");
    }
}
