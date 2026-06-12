<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Baut das Meilisearch-Dokument eines Produkts.
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
     * Dokument bauen; null, wenn das Produkt nicht (mehr) im Index steht.
     */
    public function build(string $productId): ?array
    {
        $row = DB::table('products_search_index')
            ->where('product_id', $productId)
            ->first();

        if (!$row) {
            return null;
        }

        $document = [
            'id' => $row->product_id,
            'sku' => $row->sku,
            'ean' => $row->ean,
            'status' => $row->status,
            'product_type' => $row->product_type,
            'name_de' => $row->name_de,
            'name_en' => $row->name_en,
            'description_de' => $row->description_de !== null
                ? mb_substr(strip_tags($row->description_de), 0, 5000)
                : null,
            'hierarchy_path' => $row->hierarchy_path,
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
            $document['parent_product_id'] = $row->parent_product_id;
        }

        return array_merge($document, $this->filterableAttributeValues($productId));
    }

    /**
     * Filterbare attr_*-Felder (Number normalisiert, Selection als
     * Anzeigewert-Array, Flag als bool).
     */
    private function filterableAttributeValues(string $productId): array
    {
        $schema = $this->schemaService->schema();
        $technicalNames = array_keys($schema['attributes']);

        if ($technicalNames === []) {
            return [];
        }

        $rows = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->leftJoin('units as u', 'u.id', '=', 'pav.unit_id')
            ->leftJoin('value_list_entries as vle', 'vle.id', '=', 'pav.value_selection_id')
            ->where('pav.product_id', $productId)
            ->whereIn('a.technical_name', $technicalNames)
            ->select([
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

        $values = [];

        foreach ($rows as $row) {
            $attr = $schema['attributes'][$row->technical_name];
            $field = $attr['field'];

            switch ($row->data_type) {
                case 'Number':
                    if ($row->value_number === null || isset($values[$field])) {
                        break;
                    }
                    // Normalisierung: gespeicherte Einheit, sonst Default-Einheit des Attributs
                    $factor = $row->unit_factor !== null
                        ? (float) $row->unit_factor
                        : (float) ($attr['default_to_base'] ?? 1.0);
                    $values[$field] = round((float) $row->value_number * $factor, 6);
                    break;

                case 'Flag':
                    if ($row->value_flag !== null && !isset($values[$field])) {
                        $values[$field] = (bool) $row->value_flag;
                    }
                    break;

                case 'Selection':
                    $display = $row->display_value_de ?? $row->display_value_en;
                    if ($display !== null) {
                        $values[$field] ??= [];
                        if (!in_array($display, $values[$field], true)) {
                            $values[$field][] = $display;
                        }
                    }
                    break;
            }
        }

        return $values;
    }
}
