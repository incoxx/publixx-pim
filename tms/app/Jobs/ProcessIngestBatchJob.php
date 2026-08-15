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

    public function __construct(private readonly array $entities)
    {
    }

    public function handle(): void
    {
        $targetLangs = config('tms.target_languages', ['en', 'fr', 'es', 'it', 'nl']);
        $prefix = config('tms.cache_prefix', 'tms:t:');
        $ttl = config('tms.cache_ttl', 86400);
        $newUnits = 0;

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

                // If unit is new, dispatch translation jobs
                if ($wasRecentlyCreated) {
                    $newUnits++;
                    TranslateUnitJob::dispatch($unit->id, $targetLangs);
                } else {
                    // Warm cache for existing translations
                    foreach ($unit->translations as $t) {
                        $cacheKey = "{$prefix}{$hash}:{$t->target_lang}";
                        Redis::setex($cacheKey, $ttl, $t->translation);
                    }
                }
            }
        }

        Log::info("TMS ingest batch processed: {$newUnits} new units from " . count($this->entities) . " entities.");
    }
}
