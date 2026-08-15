<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\AttributeView;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\PriceType;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\UnitGroup;
use App\Models\ValueList;
use App\Models\Language;
use App\Models\ValueListEntry;
use App\Services\Tms\TmsClient;
use App\Services\Tms\TmsHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTmsTranslationsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 60];
    public int $uniqueFor = 300; // 5 minutes

    /**
     * Entity types mapped to their model class, JSON field, and source text field.
     */
    private const array ENTITY_MAP = [
        'unit_group' => [
            'model' => UnitGroup::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
        'attribute_view' => [
            'model' => AttributeView::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
        'attribute_group' => [
            'model' => AttributeType::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
        'value_list' => [
            'model' => ValueList::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
        'attribute' => [
            'model' => Attribute::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
        'product_type' => [
            'model' => ProductType::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
        'price_type' => [
            'model' => PriceType::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
        'relation_type' => [
            'model' => ProductRelationType::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
        'hierarchy' => [
            'model' => Hierarchy::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
        'hierarchy_node' => [
            'model' => HierarchyNode::class,
            'json_field' => 'name_json',
            'source_field' => 'name_de',
        ],
    ];

    public function handle(TmsClient $client): array
    {
        if (!$client->isEnabled()) {
            Log::info('TMS sync skipped — TMS is disabled.');
            return ['total_updated' => 0, 'skipped' => true, 'message' => 'TMS is disabled.'];
        }

        $targetLangs = Language::targetCodes();
        $sourceLang = Language::sourceCode();
        $totalUpdated = 0;

        foreach (self::ENTITY_MAP as $entityType => $config) {
            $modelClass = $config['model'];
            $jsonField = $config['json_field'];
            $sourceField = $config['source_field'];

            $modelClass::chunk(500, function ($records) use ($client, $jsonField, $sourceField, $targetLangs, $sourceLang, &$totalUpdated) {
                // Use multi-map to handle duplicate source texts correctly
                $hashMap = []; // hash => [records...]
                $uniqueHashes = [];

                foreach ($records as $record) {
                    $sourceText = $record->{$sourceField} ?? '';
                    if (empty($sourceText)) {
                        continue;
                    }
                    $hash = TmsHash::for($sourceLang, $sourceText);
                    $hashMap[$hash][] = $record;
                    $uniqueHashes[$hash] = true;
                }

                if (empty($uniqueHashes)) {
                    return;
                }

                $translations = $client->resolve(array_keys($uniqueHashes), $targetLangs);

                foreach ($hashMap as $hash => $recordGroup) {
                    $resolved = $translations[$hash] ?? null;
                    if (!$resolved) {
                        continue;
                    }

                    foreach ($recordGroup as $record) {
                        $existing = $record->{$jsonField} ?? [];
                        $updated = false;

                        foreach ($resolved as $lang => $text) {
                            if ($text !== null && ($existing[$lang] ?? null) !== $text) {
                                $existing[$lang] = $text;
                                $updated = true;
                            }
                        }

                        if ($updated) {
                            $record->{$jsonField} = $existing;
                            $record->saveQuietly();
                            $totalUpdated++;
                        }
                    }
                }
            });
        }

        // Handle value list entries separately (display_value_json)
        ValueListEntry::chunk(500, function ($entries) use ($client, $targetLangs, $sourceLang, &$totalUpdated) {
            $hashMap = [];
            $uniqueHashes = [];

            foreach ($entries as $entry) {
                $sourceText = $entry->display_value_de ?? '';
                if (empty($sourceText)) {
                    continue;
                }
                $hash = TmsHash::for($sourceLang, $sourceText);
                $hashMap[$hash][] = $entry;
                $uniqueHashes[$hash] = true;
            }

            if (empty($uniqueHashes)) {
                return;
            }

            $translations = $client->resolve(array_keys($uniqueHashes), $targetLangs);

            foreach ($hashMap as $hash => $entryGroup) {
                $resolved = $translations[$hash] ?? null;
                if (!$resolved) {
                    continue;
                }

                foreach ($entryGroup as $entry) {
                    $existing = $entry->display_value_json ?? [];
                    $updated = false;

                    foreach ($resolved as $lang => $text) {
                        if ($text !== null && ($existing[$lang] ?? null) !== $text) {
                            $existing[$lang] = $text;
                            $updated = true;
                        }
                    }

                    if ($updated) {
                        $entry->display_value_json = $existing;
                        $entry->saveQuietly();
                        $totalUpdated++;
                    }
                }
            }
        });

        Log::info("TMS sync completed: {$totalUpdated} records updated.");

        return ['total_updated' => $totalUpdated, 'skipped' => false, 'message' => "{$totalUpdated} Datensätze aktualisiert."];
    }
}
