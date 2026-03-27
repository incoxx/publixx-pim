<?php

declare(strict_types=1);

namespace App\Services\Connectors\Shopware;

use App\Models\Attribute;
use App\Models\ConnectorSyncLog;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

/**
 * Synchronisiert PIM Selection-Attribute als Shopware 6 Property Groups + Options.
 *
 * PIM                          → Shopware
 * Attribute (Selection)        → Property Group
 * ValueListEntry               → Property Group Option
 * Produkt-Attributwert         → Product ↔ Option Zuordnung (properties[])
 */
class ShopwarePropertyService
{
    /**
     * Synchronisiert alle Property Groups + Options für die gegebenen Attribute.
     *
     * Legt fehlende Groups/Options in Shopware an, merkt sich die IDs im Cache.
     *
     * @param  string[]  $attributeIds  PIM-Attribut-IDs die als Properties synchronisiert werden sollen
     * @return array<string, array{group_id: string, options: array<string, string>}>
     *         Map: PIM-Attribut-ID → {group_id: Shopware-UUID, options: {PIM-Entry-ID → Shopware-Option-ID}}
     */
    public function syncPropertyGroups(
        PendingRequest $http,
        string $shopUrl,
        string $connectionId,
        array $attributeIds,
        string $language = 'de',
    ): array {
        $shopUrl = rtrim($shopUrl, '/');
        $propertyMap = [];

        // Selection-Attribute mit ValueLists laden
        $attributes = Attribute::whereIn('id', $attributeIds)
            ->where('data_type', 'Selection')
            ->whereNotNull('value_list_id')
            ->with(['valueList.entries' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->get();

        if ($attributes->isEmpty()) {
            return [];
        }

        // Batch: Alle bestehenden Group-Logs in einer Query laden (statt N+1)
        $existingGroupLogs = ConnectorSyncLog::where('connector_connection_id', $connectionId)
            ->where('action', 'property_group_sync')
            ->where('entity_type', 'attribute')
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->whereIn('entity_id', $attributes->pluck('id'))
            ->get()
            ->groupBy('entity_id')
            ->map(fn ($logs) => $logs->sortByDesc('created_at')->first()->external_id);

        // Property Groups gebündelt anlegen/aktualisieren
        $newGroups = [];
        $updateGroups = [];
        $groupIdMap = []; // attribute_id → groupId

        foreach ($attributes as $attribute) {
            $valueList = $attribute->valueList;
            if (!$valueList || $valueList->entries->isEmpty()) {
                continue;
            }

            $existingGroupId = $existingGroupLogs->get($attribute->id);
            $name = $language === 'en' && $attribute->name_en
                ? $attribute->name_en
                : ($attribute->name_de ?: $attribute->technical_name);

            if (!$existingGroupId) {
                $groupId = str_replace('-', '', Str::uuid()->toString());
                $newGroups[] = [
                    'id'          => $groupId,
                    'name'        => $name,
                    'sortingType' => 'alphanumeric',
                    'displayType' => 'text',
                ];
                $groupIdMap[$attribute->id] = $groupId;
            } else {
                $updateGroups[] = [
                    'id'   => $existingGroupId,
                    'name' => $name,
                ];
                $groupIdMap[$attribute->id] = $existingGroupId;
            }
        }

        // Neue Groups in einem API-Call anlegen
        if (!empty($newGroups)) {
            try {
                $http->post("{$shopUrl}/api/_action/sync", [
                    'write-property-groups' => [
                        'action'  => 'upsert',
                        'entity'  => 'property_group',
                        'payload' => $newGroups,
                    ],
                ])->throw();
            } catch (\Throwable) {
                // Fallback: einzeln anlegen bei Batch-Fehler
                foreach ($newGroups as $group) {
                    try {
                        $http->post("{$shopUrl}/api/_action/sync", [
                            'write-property-group' => [
                                'action'  => 'upsert',
                                'entity'  => 'property_group',
                                'payload' => [$group],
                            ],
                        ])->throw();
                    } catch (\Throwable) {
                        // Group konnte nicht angelegt werden — aus Map entfernen
                        $attrId = array_search($group['id'], $groupIdMap, true);
                        if ($attrId !== false) {
                            unset($groupIdMap[$attrId]);
                        }
                    }
                }
            }
        }

        // Bestehende Groups aktualisieren (gebündelt)
        if (!empty($updateGroups)) {
            try {
                $http->post("{$shopUrl}/api/_action/sync", [
                    'update-property-groups' => [
                        'action'  => 'upsert',
                        'entity'  => 'property_group',
                        'payload' => $updateGroups,
                    ],
                ])->throw();
            } catch (\Throwable) {
                // Nicht kritisch — Gruppen existieren bereits
            }
        }

        // Options für alle Attribute synchronisieren
        // Zunächst alle Entry-IDs sammeln für Batch-Log-Query
        $allEntryIds = [];
        foreach ($attributes as $attribute) {
            if (!isset($groupIdMap[$attribute->id])) {
                continue;
            }
            $entries = $attribute->valueList?->entries;
            if ($entries) {
                foreach ($entries as $entry) {
                    $allEntryIds[] = $entry->id;
                }
            }
        }

        // Batch: Alle bestehenden Option-Logs in einer Query laden
        $existingOptionLogs = [];
        if (!empty($allEntryIds)) {
            $existingOptionLogs = ConnectorSyncLog::where('connector_connection_id', $connectionId)
                ->where('action', 'property_option_sync')
                ->where('entity_type', 'value_list_entry')
                ->where('status', 'success')
                ->whereNotNull('external_id')
                ->whereIn('entity_id', $allEntryIds)
                ->get()
                ->mapWithKeys(fn ($log) => [$log->entity_id => $log->external_id])
                ->toArray();
        }

        // Options pro Attribute synchronisieren
        foreach ($attributes as $attribute) {
            $groupId = $groupIdMap[$attribute->id] ?? null;
            if (!$groupId) {
                continue;
            }

            $entries = $attribute->valueList?->entries;
            if (!$entries || $entries->isEmpty()) {
                continue;
            }

            $optionMap = $this->syncPropertyOptions(
                $http, $shopUrl, $groupId, $entries, $language, $existingOptionLogs,
            );

            $propertyMap[$attribute->id] = [
                'group_id' => $groupId,
                'options'  => $optionMap,
            ];
        }

        return $propertyMap;
    }

    /**
     * Synchronisiert ValueList-Entries als Property Group Options.
     *
     * @param  array<string, string>  $existingOptionLogs  Vorgeladene Option-Log-Map (entry_id → external_id)
     * @return array<string, string>  Map: PIM-ValueListEntry-ID → Shopware-Option-ID
     */
    private function syncPropertyOptions(
        PendingRequest $http,
        string $shopUrl,
        string $groupId,
        $entries,
        string $language,
        array $existingOptionLogs = [],
    ): array {
        $optionMap = [];

        $payload = [];
        foreach ($entries as $entry) {
            $optionId = $existingOptionLogs[$entry->id] ?? str_replace('-', '', Str::uuid()->toString());
            $optionMap[$entry->id] = $optionId;

            $name = $language === 'en' && $entry->display_value_en
                ? $entry->display_value_en
                : $entry->display_value_de;

            $payload[] = [
                'id'      => $optionId,
                'groupId' => $groupId,
                'name'    => $name ?: $entry->technical_name,
            ];
        }

        // In Chunks von 50 senden
        foreach (array_chunk($payload, 50) as $chunk) {
            try {
                $http->post("{$shopUrl}/api/_action/sync", [
                    'write-property-options' => [
                        'action'  => 'upsert',
                        'entity'  => 'property_group_option',
                        'payload' => $chunk,
                    ],
                ])->throw();
            } catch (\Throwable) {
                // Einzelne Options können fehlschlagen, Map bleibt intakt
            }
        }

        return $optionMap;
    }

    /**
     * Ermittelt die Property-Option-IDs für ein Produkt basierend auf dessen Attributwerten.
     *
     * @param  array<string, array{group_id: string, options: array<string, string>}>  $propertyMap
     * @return array<array{id: string}>  Array von Option-Referenzen für Shopware product.properties
     */
    public function resolveProductProperties(
        Product $product,
        array $propertyMap,
        string $language = 'de',
    ): array {
        if (empty($propertyMap)) {
            return [];
        }

        $attributeIds = array_keys($propertyMap);
        $properties = [];

        // Alle Selection-Attributwerte dieses Produkts für die gemappten Attribute laden
        $values = ProductAttributeValue::where('product_id', $product->id)
            ->whereIn('attribute_id', $attributeIds)
            ->whereNotNull('value_selection_id')
            ->get();

        foreach ($values as $value) {
            $attrId = $value->attribute_id;
            $entryId = $value->value_selection_id;

            if (!isset($propertyMap[$attrId]['options'][$entryId])) {
                continue;
            }

            $shopwareOptionId = $propertyMap[$attrId]['options'][$entryId];
            $properties[] = ['id' => $shopwareOptionId];
        }

        return $properties;
    }

    /**
     * Ermittelt die Property-Option-IDs für mehrere Produkte in einem Batch (Performance).
     *
     * @param  array<string>  $productIds
     * @param  array<string, array{group_id: string, options: array<string, string>}>  $propertyMap
     * @return array<string, array<array{id: string}>>  Map: product_id → properties array
     */
    public function resolveProductPropertiesBulk(
        array $productIds,
        array $propertyMap,
    ): array {
        if (empty($propertyMap) || empty($productIds)) {
            return [];
        }

        $attributeIds = array_keys($propertyMap);

        // Eine Query für alle Produkte + alle Property-Attribute
        $values = ProductAttributeValue::whereIn('product_id', $productIds)
            ->whereIn('attribute_id', $attributeIds)
            ->whereNotNull('value_selection_id')
            ->get()
            ->groupBy('product_id');

        $result = [];
        foreach ($productIds as $productId) {
            $productValues = $values->get($productId, collect());
            $properties = [];

            foreach ($productValues as $value) {
                $attrId = $value->attribute_id;
                $entryId = $value->value_selection_id;

                if (!isset($propertyMap[$attrId]['options'][$entryId])) {
                    continue;
                }

                $shopwareOptionId = $propertyMap[$attrId]['options'][$entryId];
                $properties[] = ['id' => $shopwareOptionId];
            }

            $result[$productId] = $properties;
        }

        return $result;
    }
}
