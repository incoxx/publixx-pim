<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attribute;
use App\Models\ConnectorConnection;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\TranslationJob;
use App\Models\TranslationJobItem;
use App\Services\Connectors\DeepL\DeepLTranslationService;
use App\Services\Tms\TmsClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TranslationJobService
{
    public function __construct(
        protected DeepLTranslationService $deeplService,
        protected TmsClient $tmsClient,
    ) {}

    /**
     * Create a translation job from product IDs and attribute IDs.
     */
    public function createFromProducts(
        string $name,
        array $productIds,
        array $attributeIds,
        string $sourceLanguage,
        string $targetLanguage,
        string $userId,
        ?array $filters = null,
    ): TranslationJob {
        return DB::transaction(function () use ($name, $productIds, $attributeIds, $sourceLanguage, $targetLanguage, $userId, $filters) {
            $job = TranslationJob::create([
                'name' => $name,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'scope' => 'products',
                'status' => 'draft',
                'filters' => $filters,
                'attribute_ids' => $attributeIds,
                'created_by' => $userId,
            ]);

            $items = $this->collectProductItems($productIds, $attributeIds, $sourceLanguage, $targetLanguage);

            foreach ($items as $item) {
                $job->items()->create($item);
            }

            $job->update(['total_items' => count($items)]);

            return $job;
        });
    }

    /**
     * Create a translation job from system entities.
     */
    public function createFromSystem(
        string $name,
        array $entityTypes,
        string $sourceLanguage,
        string $targetLanguage,
        string $userId,
    ): TranslationJob {
        return DB::transaction(function () use ($name, $entityTypes, $sourceLanguage, $targetLanguage, $userId) {
            $job = TranslationJob::create([
                'name' => $name,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'scope' => 'system',
                'status' => 'draft',
                'created_by' => $userId,
            ]);

            $items = $this->collectSystemItems($entityTypes, $sourceLanguage, $targetLanguage);

            foreach ($items as $item) {
                $job->items()->create($item);
            }

            $job->update(['total_items' => count($items)]);

            return $job;
        });
    }

    /**
     * Create a mixed translation job.
     */
    public function createMixed(
        string $name,
        array $productIds,
        array $attributeIds,
        array $entityTypes,
        string $sourceLanguage,
        string $targetLanguage,
        string $userId,
        ?array $filters = null,
    ): TranslationJob {
        return DB::transaction(function () use ($name, $productIds, $attributeIds, $entityTypes, $sourceLanguage, $targetLanguage, $userId, $filters) {
            $job = TranslationJob::create([
                'name' => $name,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'scope' => 'mixed',
                'status' => 'draft',
                'filters' => $filters,
                'attribute_ids' => $attributeIds,
                'created_by' => $userId,
            ]);

            $productItems = $this->collectProductItems($productIds, $attributeIds, $sourceLanguage, $targetLanguage);
            $systemItems = $this->collectSystemItems($entityTypes, $sourceLanguage, $targetLanguage);
            $allItems = array_merge($productItems, $systemItems);

            foreach ($allItems as $item) {
                $job->items()->create($item);
            }

            $job->update(['total_items' => count($allItems)]);

            return $job;
        });
    }

    /**
     * Collect product attribute values that need translation.
     */
    protected function collectProductItems(array $productIds, array $attributeIds, string $sourceLanguage, string $targetLanguage): array
    {
        $items = [];

        // Get source texts for requested attributes
        $sourceValues = ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $attributeIds)
            ->where('language', $sourceLanguage)
            ->whereNotNull('value_string')
            ->where('value_string', '!=', '')
            ->get();

        // Get existing translations to skip
        $existingTranslations = ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $attributeIds)
            ->where('language', $targetLanguage)
            ->whereNotNull('value_string')
            ->where('value_string', '!=', '')
            ->get()
            ->groupBy(fn ($v) => $v->product_id . '|' . $v->attribute_id);

        foreach ($sourceValues as $value) {
            $key = $value->product_id . '|' . $value->attribute_id;
            if ($existingTranslations->has($key)) {
                continue;
            }

            $items[] = [
                'entity_type' => 'product',
                'entity_id' => $value->product_id,
                'attribute_id' => $value->attribute_id,
                'source_text' => $value->value_string,
                'status' => 'pending',
            ];
        }

        return $items;
    }

    /**
     * Resolve the translatable field prefix for an entity type.
     * Most entities use "name_*", but ValueListEntry uses "display_value_*".
     */
    protected function resolveFieldPrefix(string $entityType): string
    {
        return match ($entityType) {
            'value_list_entry' => 'display_value',
            default => 'name',
        };
    }

    /**
     * Collect system entity texts that need translation.
     */
    protected function collectSystemItems(array $entityTypes, string $sourceLanguage, string $targetLanguage): array
    {
        $items = [];

        foreach ($entityTypes as $entityType) {
            $modelClass = $this->resolveModelClass($entityType);
            if (!$modelClass) {
                continue;
            }

            $fieldPrefix = $this->resolveFieldPrefix($entityType);
            $sourceField = "{$fieldPrefix}_{$sourceLanguage}";
            $targetField = "{$fieldPrefix}_{$targetLanguage}";
            $jsonField = "{$fieldPrefix}_json";

            $entities = $modelClass::whereNotNull($sourceField)
                ->where($sourceField, '!=', '')
                ->get();

            foreach ($entities as $entity) {
                $jsonData = $entity->$jsonField ? (is_string($entity->$jsonField) ? json_decode($entity->$jsonField, true) : $entity->$jsonField) : [];

                // Check if translation exists in fixed column or JSON
                $hasTranslation = false;
                if (in_array($targetLanguage, ['de', 'en'])) {
                    $hasTranslation = !empty($entity->$targetField);
                } else {
                    $hasTranslation = !empty($jsonData[$targetLanguage] ?? null);
                }

                if ($hasTranslation) {
                    continue;
                }

                $items[] = [
                    'entity_type' => $entityType,
                    'entity_id' => $entity->id,
                    'attribute_id' => null,
                    'source_text' => $entity->$sourceField,
                    'status' => 'pending',
                ];
            }
        }

        return $items;
    }

    /**
     * Import approved translations into the database.
     */
    public function importTranslations(TranslationJob $job): int
    {
        $imported = 0;

        $translatedItems = $job->items()->where('status', 'translated')->get();

        foreach ($translatedItems as $item) {
            if ($item->entity_type === 'product') {
                ProductAttributeValue::updateOrCreate(
                    [
                        'product_id' => $item->entity_id,
                        'attribute_id' => $item->attribute_id,
                        'language' => $job->target_language,
                    ],
                    [
                        'value_string' => $item->translated_text,
                    ],
                );
                $imported++;
            } else {
                // System entity – update json or fixed column
                $modelClass = $this->resolveModelClass($item->entity_type);
                if (!$modelClass) {
                    continue;
                }

                $entity = $modelClass::find($item->entity_id);
                if (!$entity) {
                    continue;
                }

                $fieldPrefix = $this->resolveFieldPrefix($item->entity_type);
                $targetField = "{$fieldPrefix}_{$job->target_language}";
                $jsonField = "{$fieldPrefix}_json";

                if (in_array($job->target_language, ['de', 'en'])) {
                    $entity->$targetField = $item->translated_text;
                } else {
                    $jsonData = $entity->$jsonField ? (is_string($entity->$jsonField) ? json_decode($entity->$jsonField, true) : $entity->$jsonField) : [];
                    $jsonData[$job->target_language] = $item->translated_text;
                    $entity->$jsonField = json_encode($jsonData, JSON_UNESCAPED_UNICODE);
                }
                $entity->save();
                $imported++;
            }
        }

        // Sync translated items to TMS
        $this->syncToTms($job, $translatedItems);

        return $imported;
    }

    /**
     * Sync translated items to the Translation Memory System.
     */
    protected function syncToTms(TranslationJob $job, $translatedItems): void
    {
        if (!$this->tmsClient->isEnabled()) {
            return;
        }

        try {
            $tmsItems = [];
            foreach ($translatedItems as $item) {
                $fieldPrefix = $this->resolveFieldPrefix($item->entity_type);

                $tmsItems[] = [
                    'source_text' => $item->source_text,
                    'source_lang' => $job->source_language,
                    'target_lang' => $job->target_language,
                    'translated_text' => $item->translated_text,
                    'entity_type' => $item->entity_type,
                    'entity_id' => $item->entity_id,
                    'field_name' => "{$fieldPrefix}_{$job->source_language}",
                    'provider' => 'deepl',
                ];
            }

            $this->tmsClient->importTranslations($tmsItems);
        } catch (\Throwable $e) {
            Log::warning('TMS sync after translation import failed', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the DeepL API key from the active connection.
     */
    public function getDeepLApiKey(): ?string
    {
        $connection = ConnectorConnection::where('connector_type', 'deepl')
            ->where('is_active', true)
            ->first();

        return $connection?->access_token;
    }

    protected function resolveModelClass(string $entityType): ?string
    {
        return match ($entityType) {
            'attribute' => \App\Models\Attribute::class,
            'attribute_type' => \App\Models\AttributeType::class,
            'value_list_entry' => \App\Models\ValueListEntry::class,
            'value_list' => \App\Models\ValueList::class,
            'hierarchy_node' => \App\Models\HierarchyNode::class,
            'hierarchy' => \App\Models\Hierarchy::class,
            'product_type' => \App\Models\ProductType::class,
            'unit_group' => \App\Models\UnitGroup::class,
            'price_type' => \App\Models\PriceType::class,
            'relation_type' => \App\Models\ProductRelationType::class,
            default => null,
        };
    }
}
