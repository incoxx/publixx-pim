<?php

declare(strict_types=1);

namespace Tms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Tms\Models\TmsTranslation;
use Tms\Models\TmsUnit;
use Tms\Models\TmsUsage;

class ImportTranslationsController
{
    /**
     * POST /api/import-translations
     *
     * Batch import of pre-translated items. Creates TMS units + translations.
     * Used by translation jobs to sync results into the TMS.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|max:500',
            'items.*.source_text' => 'required|string|max:10000',
            'items.*.source_lang' => 'required|string|max:5',
            'items.*.target_lang' => 'required|string|max:5',
            'items.*.translated_text' => 'required|string|max:10000',
            'items.*.entity_type' => 'required|string|max:50',
            'items.*.entity_id' => 'required|string|max:36',
            'items.*.field_name' => 'nullable|string|max:50',
            'items.*.entity_label' => 'nullable|string|max:255',
            'items.*.provider' => 'nullable|string|max:20',
        ]);

        $items = $request->input('items');
        $prefix = config('tms.cache_prefix', 'tms:t:');
        $ttl = config('tms.cache_ttl', 86400);
        $imported = 0;

        foreach ($items as $item) {
            $hash = hash('sha256', $item['source_lang'] . '|' . $item['source_text']);

            // Upsert TMS Unit
            $unit = TmsUnit::firstOrCreate(
                ['text_hash' => $hash],
                [
                    'source_lang' => $item['source_lang'],
                    'source_text' => $item['source_text'],
                    'domain' => $item['entity_type'],
                    'char_count' => mb_strlen($item['source_text']),
                ]
            );

            // Upsert Usage
            TmsUsage::updateOrCreate(
                [
                    'tms_unit_id' => $unit->id,
                    'entity_type' => $item['entity_type'],
                    'entity_id' => $item['entity_id'],
                    'field_name' => $item['field_name'] ?? 'name',
                ],
                [
                    'entity_label' => $item['entity_label'] ?? null,
                    'last_seen_at' => now(),
                ]
            );

            // Upsert Translation
            TmsTranslation::updateOrCreate(
                [
                    'tms_unit_id' => $unit->id,
                    'target_lang' => $item['target_lang'],
                ],
                [
                    'translation' => $item['translated_text'],
                    'provider' => $item['provider'] ?? 'deepl',
                    'status' => 'auto',
                    'confidence' => 0.95,
                ]
            );

            // Update Redis cache
            Redis::setex("{$prefix}{$hash}:{$item['target_lang']}", $ttl, $item['translated_text']);

            $imported++;
        }

        return response()->json([
            'message' => "{$imported} Übersetzungen importiert.",
            'imported' => $imported,
        ]);
    }
}
