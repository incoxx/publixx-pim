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

        foreach ($attributes as $attribute) {
            $valueList = $attribute->valueList;
            if (!$valueList || $valueList->entries->isEmpty()) {
                continue;
            }

            // Prüfen ob bereits synchronisiert (aus Sync-Logs)
            $existingGroupLog = ConnectorSyncLog::where('connector_connection_id', $connectionId)
                ->where('action', 'property_group_sync')
                ->where('entity_type', 'attribute')
                ->where('entity_id', $attribute->id)
                ->where('status', 'success')
                ->whereNotNull('external_id')
                ->latest()
                ->first();

            $groupId = $existingGroupLog?->external_id;

            // Property Group anlegen oder bestätigen
            if (!$groupId) {
                $groupId = $this->createPropertyGroup($http, $shopUrl, $attribute, $language);
            } else {
                // Bestehende Group aktualisieren (Name könnte sich geändert haben)
                $this->updatePropertyGroup($http, $shopUrl, $groupId, $attribute, $language);
            }

            if (!$groupId) {
                continue;
            }

            // Options synchronisieren
            $optionMap = $this->syncPropertyOptions(
                $http, $shopUrl, $connectionId, $groupId, $valueList->entries, $language,
            );

            $propertyMap[$attribute->id] = [
                'group_id' => $groupId,
                'options'  => $optionMap,
            ];
        }

        return $propertyMap;
    }

    /**
     * Erstellt eine Property Group in Shopware.
     */
    private function createPropertyGroup(
        PendingRequest $http,
        string $shopUrl,
        Attribute $attribute,
        string $language,
    ): ?string {
        $groupId = Str::uuid()->toString();
        $name = $language === 'en' && $attribute->name_en
            ? $attribute->name_en
            : $attribute->name_de;

        try {
            $http->post("{$shopUrl}/api/_action/sync", [
                'write-property-group' => [
                    'action'  => 'upsert',
                    'entity'  => 'property_group',
                    'payload' => [
                        [
                            'id'           => $groupId,
                            'name'         => $name ?: $attribute->technical_name,
                            'sortingType'  => 'alphanumeric',
                            'displayType'  => 'text',
                        ],
                    ],
                ],
            ])->throw();

            return $groupId;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Aktualisiert den Namen einer bestehenden Property Group.
     */
    private function updatePropertyGroup(
        PendingRequest $http,
        string $shopUrl,
        string $groupId,
        Attribute $attribute,
        string $language,
    ): void {
        $name = $language === 'en' && $attribute->name_en
            ? $attribute->name_en
            : $attribute->name_de;

        try {
            $http->post("{$shopUrl}/api/_action/sync", [
                'update-property-group' => [
                    'action'  => 'upsert',
                    'entity'  => 'property_group',
                    'payload' => [
                        [
                            'id'   => $groupId,
                            'name' => $name ?: $attribute->technical_name,
                        ],
                    ],
                ],
            ])->throw();
        } catch (\Throwable) {
            // Nicht kritisch — Gruppe existiert bereits
        }
    }

    /**
     * Synchronisiert ValueList-Entries als Property Group Options.
     *
     * @return array<string, string>  Map: PIM-ValueListEntry-ID → Shopware-Option-ID
     */
    private function syncPropertyOptions(
        PendingRequest $http,
        string $shopUrl,
        string $connectionId,
        string $groupId,
        $entries,
        string $language,
    ): array {
        $optionMap = [];

        // Bestehende Option-IDs aus Sync-Logs laden
        $existingLogs = ConnectorSyncLog::where('connector_connection_id', $connectionId)
            ->where('action', 'property_option_sync')
            ->where('entity_type', 'value_list_entry')
            ->where('status', 'success')
            ->whereNotNull('external_id')
            ->whereIn('entity_id', $entries->pluck('id'))
            ->get()
            ->mapWithKeys(fn ($log) => [$log->entity_id => $log->external_id])
            ->toArray();

        $payload = [];
        foreach ($entries as $entry) {
            $optionId = $existingLogs[$entry->id] ?? Str::uuid()->toString();
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

            // Shopware Option-ID für diesen ValueList-Eintrag
            $shopwareOptionId = $propertyMap[$attrId]['options'][$entryId];
            $properties[] = ['id' => $shopwareOptionId];
        }

        return $properties;
    }
}
