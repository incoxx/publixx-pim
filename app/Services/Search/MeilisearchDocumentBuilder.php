<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Baut das Meilisearch-Dokument eines Produkts (Einzelpfad) oder
 * eines ganzen Batches (buildBatch, für den Bulk-Index-Befehl).
 *
 * Quelle ist der bereits denormalisierte products_search_index (Name,
 * Beschreibung, searchable_text, Hierarchiepfad, Listenpreis) — ergänzt um
 * filterbare Attributwerte (attr_*) aus der EAV-Tabelle. Numerische Werte
 * werden gemäß Schema in die Basiseinheit ihrer Einheitengruppe
 * normalisiert, damit Query-Constraints („500 g" vs. „0,5 kg")
 * deterministisch vergleichbar sind.
 */
class MeilisearchDocumentBuilder
{
    public function __construct(private readonly SearchSchemaService $schemaService)
    {
    }

    /**
     * Einzelnes Dokument bauen; null wenn das Produkt nicht im Index steht.
     */
    public function build(string $productId): ?array
    {
        $row = DB::table('products_search_index')
            ->join('products', 'products.id', '=', 'products_search_index.product_id')
            ->where('products_search_index.product_id', $productId)
            ->select('products_search_index.*', 'products.name as product_name')
            ->first();

        if (!$row) {
            return null;
        }

        $attrValues = $this->filterableAttributeValuesBatch([$productId]);
        $hierarchyNames = $this->resolveHierarchyNames([$row->hierarchy_path]);

        return $this->toDocument($row, $attrValues[$productId] ?? [], $hierarchyNames);
    }

    /**
     * Batch-Variante: baut Dokumente für alle übergebenen Produkt-IDs in
     * genau 2 SQL-Queries statt 2×N. Gibt Dokumente ohne null-Einträge zurück.
     *
     * @param  string[] $productIds
     * @return array[]
     */
    public function buildBatch(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = DB::table('products_search_index')
            ->join('products', 'products.id', '=', 'products_search_index.product_id')
            ->whereIn('products_search_index.product_id', $productIds)
            ->select('products_search_index.*', 'products.name as product_name')
            ->get()
            ->keyBy('product_id');

        $attrValues = $this->filterableAttributeValuesBatch($productIds);
        $hierarchyNames = $this->resolveHierarchyNames(
            $rows->pluck('hierarchy_path')->filter()->unique()->values()->all(),
        );

        $documents = [];
        foreach ($rows as $row) {
            $documents[] = $this->toDocument($row, $attrValues[$row->product_id] ?? [], $hierarchyNames);
        }

        return $documents;
    }

    /**
     * stdClass-Zeile → Meilisearch-Dokument-Array.
     *
     * @param array<string, string> $hierarchyNames UUID-Pfad → lesbarer Namenspfad
     */
    private function toDocument(object $row, array $attrValues, array $hierarchyNames = []): array
    {
        $document = [
            'id' => $row->product_id,
            'sku' => $row->sku,
            'ean' => $row->ean,
            'status' => $row->status,
            'product_type' => $row->product_type,
            'product_name' => $row->product_name ?? null,
            'name_de' => $row->name_de,
            'name_en' => $row->name_en,
            'description_de' => $row->description_de !== null
                ? mb_substr(strip_tags($row->description_de), 0, 5000)
                : null,
            // Lesbarer Namenspfad ("Kryotechnik > Schläuche") statt UUID-Pfad —
            // wird angezeigt, durchsucht und embedded. Der UUID-Pfad bleibt
            // unter hierarchy_path_ids für exakte Filter erhalten.
            'hierarchy_path' => $row->hierarchy_path !== null
                ? ($hierarchyNames[$row->hierarchy_path] ?? null)
                : null,
            'hierarchy_path_ids' => $row->hierarchy_path,
            'searchable_text' => $row->searchable_text,
            'media_text' => $row->media_text,
            'primary_image' => $row->primary_image,
            'list_price' => $row->list_price !== null ? (float) $row->list_price : null,
            'attribute_completeness' => $row->attribute_completeness !== null
                ? (int) $row->attribute_completeness
                : null,
            'updated_at' => $row->updated_at
                ? Carbon::parse($row->updated_at)->getTimestamp()
                : null,
        ];

        if (property_exists($row, 'product_type_ref')) {
            $document['product_type_ref'] = $row->product_type_ref;
            $document['parent_product_id'] = $row->parent_product_id ?? null;
        }

        return array_merge($document, $attrValues);
    }

    /**
     * UUID-Pfade ("/uuid1/uuid2/") in lesbare Namenspfade auflösen
     * ("Kryotechnik > Schläuche") — batch, ein Query für alle Pfade.
     *
     * @param  array<?string> $paths
     * @return array<string, string> UUID-Pfad → Namenspfad
     */
    private function resolveHierarchyNames(array $paths): array
    {
        $nodeIds = [];
        foreach ($paths as $path) {
            foreach (array_filter(explode('/', (string) $path)) as $id) {
                $nodeIds[$id] = true;
            }
        }

        if ($nodeIds === []) {
            return [];
        }

        $names = DB::table('hierarchy_nodes')
            ->whereIn('id', array_keys($nodeIds))
            ->pluck('name_de', 'id')
            ->all();

        $map = [];
        foreach (array_unique(array_filter($paths)) as $path) {
            $segments = [];
            foreach (array_filter(explode('/', $path)) as $id) {
                if (isset($names[$id]) && $names[$id] !== '') {
                    $segments[] = $names[$id];
                }
            }
            if ($segments !== []) {
                $map[$path] = implode(' > ', $segments);
            }
        }

        return $map;
    }

    /**
     * Filterbare attr_*-Felder für einen Batch von Produkt-IDs in einem Query.
     *
     * @param  string[] $productIds
     * @return array<string, array> product_id → [field => value]
     */
    private function filterableAttributeValuesBatch(array $productIds): array
    {
        $schema = $this->schemaService->schema();
        $technicalNames = array_keys($schema['attributes']);

        if ($technicalNames === [] || $productIds === []) {
            return [];
        }

        $rows = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->leftJoin('units as u', 'u.id', '=', 'pav.unit_id')
            ->leftJoin('value_list_entries as vle', 'vle.id', '=', 'pav.value_selection_id')
            ->whereIn('pav.product_id', $productIds)
            ->whereIn('a.technical_name', $technicalNames)
            ->select([
                'pav.product_id',
                'a.technical_name',
                'a.data_type',
                'pav.value_number',
                'pav.value_flag',
                'u.conversion_factor as unit_factor',
                'vle.display_value_de',
                'vle.display_value_en',
            ])
            ->orderBy('pav.multiplied_index')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $pid = $row->product_id;
            $attr = $schema['attributes'][$row->technical_name];
            $field = $attr['field'];

            switch ($row->data_type) {
                case 'Number':
                    if ($row->value_number === null || isset($result[$pid][$field])) {
                        break;
                    }
                    $factor = $row->unit_factor !== null
                        ? (float) $row->unit_factor
                        : (float) ($attr['default_to_base'] ?? 1.0);
                    $result[$pid][$field] = round((float) $row->value_number * $factor, 6);
                    break;

                case 'Flag':
                    if ($row->value_flag !== null && !isset($result[$pid][$field])) {
                        $result[$pid][$field] = (bool) $row->value_flag;
                    }
                    break;

                case 'Selection':
                    $display = $row->display_value_de ?? $row->display_value_en;
                    if ($display !== null) {
                        $result[$pid][$field] ??= [];
                        if (!in_array($display, $result[$pid][$field], true)) {
                            $result[$pid][$field][] = $display;
                        }
                    }
                    break;
            }
        }

        return $result;
    }
}
