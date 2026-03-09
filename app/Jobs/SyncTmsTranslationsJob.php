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
use App\Models\ValueListEntry;
use App\Services\Tms\TmsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTmsTranslationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 60];

    /**
     * Entity types mapped to their model class, JSON field, and source text fields.
     */
    private const array ENTITY_MAP = [
        'unit_group' => [
            'model' => UnitGroup::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
        'attribute_view' => [
            'model' => AttributeView::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
        'attribute_group' => [
            'model' => AttributeType::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
        'value_list' => [
            'model' => ValueList::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
        'attribute' => [
            'model' => Attribute::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
        'product_type' => [
            'model' => ProductType::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
        'price_type' => [
            'model' => PriceType::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
        'relation_type' => [
            'model' => ProductRelationType::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
        'hierarchy' => [
            'model' => Hierarchy::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
        'hierarchy_node' => [
            'model' => HierarchyNode::class,
            'json_field' => 'name_json',
            'source_fields' => ['name_de', 'name_en'],
        ],
    ];

    public function handle(TmsClient $client): void
    {
        if (!$client->isEnabled()) {
            Log::info('TMS sync skipped — TMS is disabled.');
            return;
        }

        $targetLangs = config('tms.target_languages', ['en', 'fr', 'es', 'it', 'nl']);
        $totalUpdated = 0;

        foreach (self::ENTITY_MAP as $entityType => $config) {
            $modelClass = $config['model'];
            $jsonField = $config['json_field'];

            $modelClass::chunk(500, function ($records) use ($client, $entityType, $jsonField, $targetLangs, &$totalUpdated) {
                // Build hashes for each record
                $hashMap = [];
                foreach ($records as $record) {
                    $sourceText = $record->name_de ?? ($record->display_value_de ?? ($record->abbreviation ?? ''));
                    if (empty($sourceText)) {
                        continue;
                    }
                    $hash = hash('sha256', 'de' . $sourceText);
                    $hashMap[$hash] = $record;
                }

                if (empty($hashMap)) {
                    return;
                }

                // Resolve translations from TMS
                $translations = $client->resolve(array_keys($hashMap), $targetLangs);

                foreach ($hashMap as $hash => $record) {
                    $resolved = $translations[$hash] ?? null;
                    if (!$resolved) {
                        continue;
                    }

                    // Merge existing name_json with TMS results
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
                        $record->save();
                        $totalUpdated++;
                    }
                }
            });
        }

        // Handle value list entries separately (display_value_json)
        ValueListEntry::chunk(500, function ($entries) use ($client, $targetLangs, &$totalUpdated) {
            $hashMap = [];
            foreach ($entries as $entry) {
                $sourceText = $entry->display_value_de ?? '';
                if (empty($sourceText)) {
                    continue;
                }
                $hash = hash('sha256', 'de' . $sourceText);
                $hashMap[$hash] = $entry;
            }

            if (empty($hashMap)) {
                return;
            }

            $translations = $client->resolve(array_keys($hashMap), $targetLangs);

            foreach ($hashMap as $hash => $entry) {
                $resolved = $translations[$hash] ?? null;
                if (!$resolved) {
                    continue;
                }

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
                    $entry->save();
                    $totalUpdated++;
                }
            }
        });

        Log::info("TMS sync completed: {$totalUpdated} records updated.");
    }
}
