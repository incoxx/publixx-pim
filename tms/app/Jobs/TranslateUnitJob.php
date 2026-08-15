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
use Tms\Models\TmsMtLog;
use Tms\Models\TmsTranslation;
use Tms\Models\TmsUnit;
use Tms\Services\TranslationProviders\ProviderChain;

class TranslateUnitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 120];

    /**
     * @param  bool  $force  true = vorhandene maschinelle Übersetzungen neu
     *                       erzeugen (Retranslate). false = nur echte Lücken
     *                       füllen. Der Ingest nutzt false, damit mehrfach
     *                       eingeplante Units nicht mehrfach kostenpflichtig
     *                       übersetzt werden.
     */
    public function __construct(
        private readonly string $unitId,
        private readonly array $targetLangs,
        private readonly bool $force = false,
    ) {
    }

    public function handle(ProviderChain $providerChain): void
    {
        $unit = TmsUnit::find($this->unitId);
        if (!$unit) {
            Log::warning("TranslateUnitJob: Unit {$this->unitId} not found.");
            return;
        }

        $prefix = config('tms.cache_prefix', 'tms:t:');
        $ttl = config('tms.cache_ttl', 86400);

        foreach ($this->targetLangs as $targetLang) {
            // Skip if source and target are the same
            if ($targetLang === $unit->source_lang) {
                continue;
            }

            $existing = TmsTranslation::where('tms_unit_id', $unit->id)
                ->where('target_lang', $targetLang)
                ->first();

            // Geprüfte Übersetzungen sind tabu — auch beim Retranslate.
            if ($existing && $existing->status === 'reviewed') {
                continue;
            }

            // Ohne force nur echte Lücken füllen. Derselbe Quelltext taucht im
            // Ingest vielfach auf (z.B. "Gewicht" an hunderten Attributen) und
            // wird dadurch mehrfach eingeplant — ohne diese Prüfung zahlt man
            // den MT-Anbieter mehrfach für exakt denselben String.
            if ($existing && !$this->force) {
                continue;
            }

            try {
                $result = $providerChain->translate(
                    $unit->source_text,
                    $unit->source_lang,
                    $targetLang
                );

                if ($result === null) {
                    Log::warning("TranslateUnitJob: No provider could translate unit {$unit->id} to {$targetLang}");
                    continue;
                }

                // Save translation
                TmsTranslation::updateOrCreate(
                    ['tms_unit_id' => $unit->id, 'target_lang' => $targetLang],
                    [
                        'translation' => $result->text,
                        'provider' => $result->provider,
                        'status' => 'auto',
                        'confidence' => $result->confidence,
                    ]
                );

                // Update cache
                Redis::setex("{$prefix}{$unit->text_hash}:{$targetLang}", $ttl, $result->text);

                // Log MT usage
                TmsMtLog::create([
                    'provider' => $result->provider,
                    'source_lang' => $unit->source_lang,
                    'target_lang' => $targetLang,
                    'char_count' => mb_strlen($unit->source_text),
                    'cost_estimate' => $result->cost,
                    'tms_unit_id' => $unit->id,
                ]);
            } catch (\Throwable $e) {
                Log::error("TranslateUnitJob failed for unit {$unit->id} to {$targetLang}: {$e->getMessage()}");
            }
        }
    }
}
