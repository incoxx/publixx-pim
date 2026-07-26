<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttributeValue;

/**
 * ProductSnapshotSerializer – erzeugt und vergleicht vollständige Produkt-Snapshots.
 *
 * Ein Snapshot bildet den kompletten fachlichen Zustand eines Produkts ab:
 * Basisfelder, Attributwerte, Preise, Medien-Zuordnungen, Produktbeziehungen
 * (inkl. Beziehungs-Attributwerte) und Output-Hierarchie-Zuordnungen.
 *
 * Jede Sektion speichert pro Eintrag:
 *  - `raw`   → exakte Spaltenwerte für einen verlustfreien Restore
 *  - `label` → lesbare Bezeichnung (für die Diff-Ansicht)
 *  - `value` → veränderlicher Zustand als kompakter String (für den Diff)
 *
 * Die Sektionen sind assoziativ nach einem stabilen Identitäts-Schlüssel abgelegt,
 * damit {@see self::compare()} Hinzufügen/Entfernen/Ändern erkennen kann.
 */
class ProductSnapshotSerializer
{
    /** Aktuelle Snapshot-Schema-Version (nicht abwärtskompatibel zu Version 1). */
    public const SCHEMA_VERSION = 2;

    /** Versionierte Basisfelder des Produkts. */
    public const BASE_FIELDS = [
        'name',
        'sku',
        'ean',
        'status',
        'master_hierarchy_node_id',
        'manufacturer_id',
        'workflow_status',
        'workflow_assignee_id',
    ];

    /** Sektion → Diff-Typ (für die Frontend-Gruppierung). */
    public const SECTIONS = [
        'attributes' => 'attribute',
        'prices' => 'price',
        'media' => 'media',
        'relations' => 'relation',
        'output_hierarchies' => 'output_hierarchy',
    ];

    /**
     * Vollständigen Snapshot eines Produkts erzeugen.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Product $product): array
    {
        $product->loadMissing([
            'attributeValues.attribute',
            'attributeValues.valueListEntry',
            'attributeValues.unit',
            'prices.priceType',
            'mediaAssignments.media',
            'mediaAssignments.usageType',
            'outgoingRelations.targetProduct',
            'outgoingRelations.relationType',
            'outgoingRelations.attributeValues.attribute',
            'outputHierarchyAssignments.hierarchyNode',
        ]);

        $snapshot = ['_schema' => self::SCHEMA_VERSION];

        foreach (self::BASE_FIELDS as $field) {
            $snapshot[$field] = $product->{$field};
        }

        $snapshot['attributes'] = $this->buildAttributes($product);
        $snapshot['prices'] = $this->buildPrices($product);
        $snapshot['media'] = $this->buildMedia($product);
        $snapshot['relations'] = $this->buildRelations($product);
        $snapshot['output_hierarchies'] = $this->buildOutputHierarchies($product);

        return $snapshot;
    }

    /**
     * Zwei Snapshots vergleichen. Ergebnis: flache Feldliste für die Diff-Ansicht.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array{fields: array<int, array<string, mixed>>}
     */
    public function compare(array $old, array $new): array
    {
        $fields = [];

        // Basisfelder
        foreach (self::BASE_FIELDS as $field) {
            $oldValue = $old[$field] ?? null;
            $newValue = $new[$field] ?? null;
            $fields[] = [
                'field' => $field,
                'label' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'changed' => $oldValue !== $newValue,
                'type' => 'base',
            ];
        }

        // Keyed Sektionen (attributes/prices/media/relations/output_hierarchies)
        foreach (self::SECTIONS as $section => $type) {
            $oldItems = $old[$section] ?? [];
            $newItems = $new[$section] ?? [];
            $keys = array_unique(array_merge(array_keys($oldItems), array_keys($newItems)));
            sort($keys);

            foreach ($keys as $key) {
                $oldItem = $oldItems[$key] ?? null;
                $newItem = $newItems[$key] ?? null;
                $label = $newItem['label'] ?? $oldItem['label'] ?? $key;

                // Attribute: eine Zeile pro Attribut (direkter Wertvergleich).
                if ($section === 'attributes') {
                    $oldValue = $this->displayOf($oldItem);
                    $newValue = $this->displayOf($newItem);
                    $fields[] = $this->row("{$type}:{$key}", $label, $oldValue, $newValue, $oldValue !== $newValue, $type);

                    continue;
                }

                // Hinzugefügt oder entfernt → eine Zusammenfassungszeile.
                if ($oldItem === null || $newItem === null) {
                    $fields[] = $this->row(
                        "{$type}:{$key}",
                        $label,
                        $this->displayOf($oldItem),
                        $this->displayOf($newItem),
                        true,
                        $type,
                    );

                    continue;
                }

                // In beiden vorhanden → alle Detailfelder vergleichen (tiefer Diff).
                $oldDesc = $this->describeItem($section, $oldItem);
                $newDesc = $this->describeItem($section, $newItem);
                $subKeys = array_unique(array_merge(array_keys($oldDesc), array_keys($newDesc)));
                sort($subKeys);

                $changedRows = [];
                foreach ($subKeys as $sub) {
                    $subOld = $oldDesc[$sub] ?? null;
                    $subNew = $newDesc[$sub] ?? null;
                    if ($subOld !== $subNew) {
                        $changedRows[] = $this->row("{$type}:{$key}:{$sub}", "{$label} · {$sub}", $subOld, $subNew, true, $type);
                    }
                }

                if ($changedRows === []) {
                    // Unverändert → kompakte Zusammenfassungszeile.
                    $fields[] = $this->row(
                        "{$type}:{$key}",
                        $label,
                        $this->displayOf($oldItem),
                        $this->displayOf($newItem),
                        false,
                        $type,
                    );
                } else {
                    array_push($fields, ...$changedRows);
                }
            }
        }

        return ['fields' => $fields];
    }

    private function row(string $field, string $label, mixed $oldValue, mixed $newValue, bool $changed, string $type): array
    {
        return [
            'field' => $field,
            'label' => $label,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed' => $changed,
            'type' => $type,
        ];
    }

    /**
     * Zerlegt einen Sektions-Eintrag in vergleichbare, lesbare Detailfelder.
     *
     * @return array<string, mixed>
     */
    private function describeItem(string $section, array $item): array
    {
        $raw = $item['raw'] ?? [];

        return match ($section) {
            'prices' => [
                'Betrag' => trim(((string) ($raw['amount'] ?? '')) . ' ' . ($raw['currency'] ?? '')),
                'Gültig ab' => $raw['valid_from'] ?? null,
                'Gültig bis' => $raw['valid_to'] ?? null,
                'Staffel ab' => $raw['scale_from'] ?? null,
                'Staffel bis' => $raw['scale_to'] ?? null,
                'Region' => $raw['price_region_id'] ?? null,
            ],
            'media' => [
                'Position' => (string) ($raw['sort_order'] ?? 0),
                'Primär' => ! empty($raw['is_primary']) ? 'Ja' : 'Nein',
            ],
            'relations' => $this->describeRelation($item),
            'output_hierarchies' => [
                'Position' => (string) ($raw['sort_order'] ?? 0),
            ],
            default => ['Wert' => $item['value'] ?? null],
        };
    }

    /**
     * Beziehung → Position + je Beziehungs-Attributwert eine lesbare Zeile.
     *
     * @return array<string, mixed>
     */
    private function describeRelation(array $item): array
    {
        $raw = $item['raw'] ?? [];
        $labels = $item['attr_labels'] ?? [];

        $desc = ['Position' => (string) ($raw['sort_order'] ?? 0)];

        foreach ($raw['attributes'] ?? [] as $ra) {
            $name = $labels[$ra['attribute_id']] ?? $ra['attribute_id'];
            $lang = ! empty($ra['language']) ? " ({$ra['language']})" : '';
            $desc["Attr: {$name}{$lang}"] = $this->relationAttributeValue($ra);
        }

        return $desc;
    }

    private function relationAttributeValue(array $ra): ?string
    {
        return $ra['value_string']
            ?? ($ra['value_number'] ?? null)
            ?? ($ra['value_date'] ?? null)
            ?? (isset($ra['value_flag']) && $ra['value_flag'] !== null ? ($ra['value_flag'] ? 'Ja' : 'Nein') : null)
            ?? ($ra['value_selection_id'] ?? null);
    }

    /**
     * Stabiler, ordnungsunabhängiger Vergleich zweier Snapshots (für Dedup).
     */
    public function equals(array $a, array $b): bool
    {
        return $this->canonical($this->restoreProjection($a))
            === $this->canonical($this->restoreProjection($b));
    }

    /**
     * Reduziert einen Snapshot auf die restore-relevanten Daten (Basisfelder +
     * raw je Sektion). Anzeige-Felder (label/value/attr_labels) bleiben außen vor,
     * damit reine Namens-/Label-Änderungen den Dedup-Vergleich nicht beeinflussen.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function restoreProjection(array $snapshot): array
    {
        $projection = [];

        foreach (self::BASE_FIELDS as $field) {
            $projection[$field] = $snapshot[$field] ?? null;
        }

        foreach (array_keys(self::SECTIONS) as $section) {
            $projection[$section] = [];
            foreach ($snapshot[$section] ?? [] as $key => $item) {
                $projection[$section][$key] = $item['raw'] ?? [];
            }
        }

        return $projection;
    }

    private function displayOf(?array $item): mixed
    {
        if ($item === null) {
            return null;
        }

        return $item['value'] ?? $item['label'] ?? null;
    }

    // --- Sektions-Builder -----------------------------------------------------

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildAttributes(Product $product): array
    {
        $attrs = [];

        foreach ($product->attributeValues as $av) {
            $key = $av->attribute?->technical_name ?? $av->attribute_id;
            $lang = $av->language ? "_{$av->language}" : '';
            $multiIdx = ($av->multiplied_index ?? 0) > 0 ? ":{$av->multiplied_index}" : '';
            $hier = $av->output_hierarchy_id ? "@{$av->output_hierarchy_id}" : '';
            $attrKey = "{$key}{$lang}{$multiIdx}{$hier}";

            $attrs[$attrKey] = [
                'label' => $av->attribute?->name_de ?? (string) $key,
                'value' => $this->extractAttributeValue($av),
                'raw' => [
                    'attribute_id' => $av->attribute_id,
                    'value_string' => $av->value_string,
                    'value_number' => $av->value_number !== null ? (string) $av->value_number : null,
                    'value_date' => $av->value_date?->format('Y-m-d'),
                    'value_flag' => $av->value_flag,
                    'value_selection_id' => $av->value_selection_id,
                    'unit_id' => $av->unit_id,
                    'comparison_operator_id' => $av->comparison_operator_id,
                    'language' => $av->language,
                    'multiplied_index' => $av->multiplied_index ?? 0,
                    'output_hierarchy_id' => $av->output_hierarchy_id,
                ],
            ];
        }

        return $attrs;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildPrices(Product $product): array
    {
        $prices = [];

        foreach ($product->prices as $price) {
            $key = implode('|', [
                $price->price_type_id,
                $price->currency,
                $price->price_region_id ?? '',
                $price->scale_from ?? '',
            ]);

            $amount = $price->amount !== null ? (string) $price->amount : null;
            $scale = $price->scale_from !== null ? " (ab {$price->scale_from})" : '';

            $prices[$key] = [
                'label' => ($price->priceType?->technical_name ?? 'Preis') . $scale,
                'value' => trim("{$amount} {$price->currency}"),
                'raw' => [
                    'price_type_id' => $price->price_type_id,
                    'amount' => $amount,
                    'currency' => $price->currency,
                    'valid_from' => $price->valid_from?->format('Y-m-d'),
                    'valid_to' => $price->valid_to?->format('Y-m-d'),
                    'price_region_id' => $price->price_region_id,
                    'scale_from' => $price->scale_from,
                    'scale_to' => $price->scale_to,
                ],
            ];
        }

        return $prices;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildMedia(Product $product): array
    {
        $media = [];

        foreach ($product->mediaAssignments as $assignment) {
            $key = implode('|', [
                $assignment->media_id,
                $assignment->usage_type_id ?? '',
                $assignment->motif_id ?? '',
            ]);

            $fileName = $assignment->media?->file_name ?? $assignment->media_id;
            $usage = $assignment->usageType?->technical_name;

            $media[$key] = [
                'label' => $usage ? "{$fileName} ({$usage})" : (string) $fileName,
                'value' => 'Pos ' . ($assignment->sort_order ?? 0) . ($assignment->is_primary ? ', primär' : ''),
                'raw' => [
                    'media_id' => $assignment->media_id,
                    'motif_id' => $assignment->motif_id,
                    'usage_type_id' => $assignment->usage_type_id,
                    'sort_order' => $assignment->sort_order ?? 0,
                    'is_primary' => (bool) $assignment->is_primary,
                    'inherited_from_virtual_product_id' => $assignment->inherited_from_virtual_product_id,
                ],
            ];
        }

        return $media;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildRelations(Product $product): array
    {
        $relations = [];

        foreach ($product->outgoingRelations as $relation) {
            $key = implode('|', [
                $relation->relation_type_id,
                $relation->target_product_id,
            ]);

            $type = $relation->relationType?->technical_name ?? 'Beziehung';
            $targetSku = $relation->targetProduct?->sku ?? $relation->target_product_id;

            // Beziehungs-Attributwerte stabil nach attribute_id sortieren, damit
            // Snapshot/Dedup reihenfolgeunabhängig sind.
            $sortedRav = $relation->attributeValues->sortBy('attribute_id')->values();

            $relAttrs = [];
            $attrLabels = [];
            foreach ($sortedRav as $rav) {
                $relAttrs[] = [
                    'attribute_id' => $rav->attribute_id,
                    'value_string' => $rav->value_string,
                    'value_number' => $rav->value_number !== null ? (string) $rav->value_number : null,
                    'value_date' => $rav->value_date?->format('Y-m-d'),
                    'value_flag' => $rav->value_flag,
                    'value_selection_id' => $rav->value_selection_id,
                    'unit_id' => $rav->unit_id,
                    'language' => $rav->language,
                    'multiplied_index' => $rav->multiplied_index ?? 0,
                ];
                $attrLabels[$rav->attribute_id] = $rav->attribute?->name_de
                    ?? $rav->attribute?->technical_name
                    ?? $rav->attribute_id;
            }

            $relations[$key] = [
                'label' => "{$type} → {$targetSku}",
                'value' => 'Pos ' . ($relation->sort_order ?? 0)
                    . (count($relAttrs) > 0 ? ', ' . count($relAttrs) . ' Attr.' : ''),
                'attr_labels' => $attrLabels,
                'raw' => [
                    'target_product_id' => $relation->target_product_id,
                    'relation_type_id' => $relation->relation_type_id,
                    'sort_order' => $relation->sort_order ?? 0,
                    'attributes' => $relAttrs,
                ],
            ];
        }

        return $relations;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildOutputHierarchies(Product $product): array
    {
        $nodes = [];

        foreach ($product->outputHierarchyAssignments as $assignment) {
            $key = (string) $assignment->hierarchy_node_id;
            $name = $assignment->hierarchyNode?->name_de
                ?? $assignment->hierarchyNode?->name_en
                ?? $assignment->hierarchy_node_id;

            $nodes[$key] = [
                'label' => (string) $name,
                'value' => 'Pos ' . ($assignment->sort_order ?? 0),
                'raw' => [
                    'hierarchy_node_id' => $assignment->hierarchy_node_id,
                    'sort_order' => $assignment->sort_order ?? 0,
                ],
            ];
        }

        return $nodes;
    }

    // --- Wert-Auflösung (aus dem bisherigen Versioning-Service) ----------------

    private function extractAttributeValue(ProductAttributeValue $av): mixed
    {
        $dataType = $av->attribute?->data_type;

        return match ($dataType) {
            'String' => $av->value_string,
            'Number', 'Float' => $av->value_number !== null
                ? rtrim(rtrim((string) $av->value_number, '0'), '.')
                : null,
            'Date' => $av->value_date?->format('Y-m-d'),
            'Flag' => $av->value_flag !== null ? ($av->value_flag ? 'Ja' : 'Nein') : null,
            'Selection' => $this->resolveSelectionLabel($av),
            'Dictionary' => $this->resolveDictionaryLabel($av),
            default => $av->value_string
                ?? ($av->value_number !== null ? rtrim(rtrim((string) $av->value_number, '0'), '.') : null)
                ?? $av->value_date?->format('Y-m-d')
                ?? ($av->value_flag !== null ? ($av->value_flag ? 'Ja' : 'Nein') : null)
                ?? $this->resolveSelectionLabel($av),
        };
    }

    private function resolveSelectionLabel(ProductAttributeValue $av): ?string
    {
        return $av->valueListEntry?->display_value_de;
    }

    private function resolveDictionaryLabel(ProductAttributeValue $av): ?string
    {
        return $av->dictionaryEntry?->short_text_de;
    }

    /**
     * Kanonische, ordnungsunabhängige JSON-Repräsentation für den Gleichheitstest.
     *
     * @param  array<string, mixed>  $data
     */
    private function canonical(array $data): string
    {
        $normalize = function (&$value) use (&$normalize): void {
            if (is_array($value)) {
                ksort($value);
                foreach ($value as &$child) {
                    $normalize($child);
                }
            }
        };

        $normalize($data);

        return (string) json_encode($data);
    }
}
