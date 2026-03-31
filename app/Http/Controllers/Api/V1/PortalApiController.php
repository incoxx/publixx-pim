<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\DictionaryEntry;
use App\Models\PortalConfig;
use App\Models\ProductAttributeValue;
use App\Models\ValueListEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

/**
 * Oeffentliche Portal-API — liefert Konfiguration und Filter-Werte fuer Portal-Widgets.
 */
class PortalApiController extends BaseController
{
    /**
     * GET /api/v1/portal/{slug}/config
     *
     * Portal-Konfiguration: Branding, Filter-Steps, Katalogvorlage-Slug.
     */
    public function config(string $slug): JsonResponse
    {
        $portal = PortalConfig::where('slug', $slug)
            ->where('is_active', true)
            ->with('catalogTemplate:id,name,slug')
            ->firstOrFail();

        return response()->json([
            'data' => [
                'name' => $portal->name,
                'slug' => $portal->slug,
                'branding' => $portal->branding,
                'filter_steps' => $portal->filter_steps,
                'catalog_template_slug' => $portal->catalogTemplate?->slug,
                'css_variables' => $portal->css_variables,
                'custom_css' => $portal->custom_css,
                'default_locale' => $portal->default_locale,
            ],
        ]);
    }

    /**
     * GET /api/v1/portal/{slug}/filter-values
     *
     * Verfuegbare Werte fuer jeden konfigurierten Filter-Step.
     * Aggregiert die tatsaechlich vorhandenen Attributwerte aus dem PIM.
     */
    public function filterValues(string $slug): JsonResponse
    {
        $portal = PortalConfig::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $steps = $portal->filter_steps ?? [];
        $result = [];

        // Batch-load aller benoetigten Attribute
        $attributeIds = collect($steps)->pluck('attribute_id')->filter()->unique()->values();
        $attributesById = Attribute::whereIn('id', $attributeIds)->get()->keyBy('id');

        foreach ($steps as $step) {
            $attributeId = $step['attribute_id'] ?? null;
            $attribute = $attributeId ? ($attributesById[$attributeId] ?? null) : null;
            if (!$attribute) {
                continue;
            }

            $aggregated = $this->aggregateAttributeValues($attribute, $step);

            $result[] = [
                'key' => $step['key'] ?? $attribute->technical_name,
                'attribute_id' => $attributeId,
                'label' => $step['label'] ?? $attribute->name_de ?? $attribute->technical_name,
                'widget' => $step['widget'] ?? 'filter-dropdown',
                'data_type' => $aggregated['data_type'],
                'values' => $aggregated['values'],
            ] + (isset($aggregated['min']) ? [
                'min' => $aggregated['min'],
                'max' => $aggregated['max'],
                'unit' => $aggregated['unit'],
            ] : []);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Aggregiert einzigartige Attributwerte mit Produktanzahl.
     * Unterstuetzt alle Datentypen analog zu CatalogController::facets().
     */
    private function aggregateAttributeValues(Attribute $attribute, array $step): array
    {
        $dataType = $attribute->data_type;
        $baseQuery = ProductAttributeValue::where('attribute_id', $attribute->id);

        // DelimitedValue: Einzelwerte aus Delimiter-String extrahieren
        if ($dataType === 'DelimitedValue') {
            $delimiter = $attribute->delimiter ?? '|';
            $rows = (clone $baseQuery)
                ->whereNotNull('value_string')
                ->where('value_string', '!=', '')
                ->select('value_string', 'product_id')
                ->get();

            $valueCounts = [];
            foreach ($rows as $row) {
                $parts = array_map('trim', explode($delimiter, $row->value_string));
                $seen = [];
                foreach ($parts as $part) {
                    if ($part === '' || isset($seen[$part])) {
                        continue;
                    }
                    $seen[$part] = true;
                    $valueCounts[$part] = ($valueCounts[$part] ?? 0) + 1;
                }
            }
            ksort($valueCounts);

            return [
                'data_type' => 'ValueList',
                'values' => collect($valueCounts)->map(fn ($count, $value) => [
                    'value' => $value,
                    'label' => $value,
                    'count' => $count,
                ])->values()->all(),
            ];
        }

        // MultiSelection: JSON-Array mit value_selection_ids
        if ($dataType === 'MultiSelection') {
            $rows = (clone $baseQuery)
                ->whereNotNull('value_string')
                ->where('value_string', '!=', '')
                ->select('value_string', 'product_id')
                ->get();

            $valueCounts = [];
            foreach ($rows as $row) {
                $ids = json_decode($row->value_string, true);
                if (!is_array($ids)) {
                    continue;
                }
                $seen = [];
                foreach ($ids as $id) {
                    if (isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    $valueCounts[$id] = ($valueCounts[$id] ?? 0) + 1;
                }
            }

            arsort($valueCounts);
            $topIds = array_keys(array_slice($valueCounts, 0, 50, true));
            $entries = ValueListEntry::whereIn('id', $topIds)->get()->keyBy('id');

            $values = [];
            foreach ($topIds as $id) {
                $entry = $entries->get($id);
                if (!$entry) {
                    continue;
                }
                $values[] = [
                    'value' => $entry->display_value_de ?? (string) $id,
                    'value_id' => $id,
                    'count' => $valueCounts[$id],
                ];
            }

            return ['data_type' => 'ValueList', 'values' => $values];
        }

        // ValueList, Selection, Dictionary: Auswahl-Werte mit Display-Namen
        if (in_array($dataType, ['ValueList', 'Selection', 'Dictionary'])) {
            $rows = (clone $baseQuery)
                ->whereNotNull('value_selection_id')
                ->select('value_selection_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                ->groupBy('value_selection_id')
                ->orderByDesc('cnt')
                ->limit(50)
                ->get();

            $entryIds = $rows->pluck('value_selection_id')->toArray();
            if ($dataType === 'Dictionary') {
                $entries = DictionaryEntry::whereIn('id', $entryIds)->get()->keyBy('id');
            } else {
                $entries = ValueListEntry::whereIn('id', $entryIds)->get()->keyBy('id');
            }

            $values = [];
            foreach ($rows as $row) {
                $entry = $entries->get($row->value_selection_id);
                if (!$entry) {
                    continue;
                }
                $displayValue = $dataType === 'Dictionary'
                    ? ($entry->short_text_de ?? $entry->short_text_en ?? (string) $row->value_selection_id)
                    : ($entry->display_value_de ?? $entry->display_value_en ?? (string) $row->value_selection_id);

                $values[] = [
                    'value' => $displayValue,
                    'value_id' => $row->value_selection_id,
                    'count' => $row->cnt,
                ];
            }

            return ['data_type' => 'ValueList', 'values' => $values];
        }

        // Boolean / Flag
        if (in_array($dataType, ['Boolean', 'Flag'])) {
            $counts = (clone $baseQuery)
                ->whereNotNull('value_flag')
                ->select('value_flag', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                ->groupBy('value_flag')
                ->get()
                ->keyBy('value_flag');

            return [
                'data_type' => 'Boolean',
                'values' => [
                    ['value' => 'Ja', 'filter_value' => '1', 'count' => $counts->get(1)?->cnt ?? 0],
                    ['value' => 'Nein', 'filter_value' => '0', 'count' => $counts->get(0)?->cnt ?? 0],
                ],
            ];
        }

        // Dezimal / Integer / Number: Min/Max Range
        if (in_array($dataType, ['Decimal', 'Integer', 'Number', 'Float'])) {
            $stats = (clone $baseQuery)
                ->whereNotNull('value_number')
                ->select(
                    DB::raw('MIN(value_number) as min_val'),
                    DB::raw('MAX(value_number) as max_val'),
                    DB::raw('COUNT(DISTINCT product_id) as cnt')
                )
                ->first();

            $unit = null;
            $firstWithUnit = (clone $baseQuery)->whereNotNull('unit_id')->with('unit')->first();
            if ($firstWithUnit?->unit) {
                $unit = $firstWithUnit->unit->abbreviation;
            }

            return [
                'data_type' => 'Range',
                'values' => [],
                'min' => $stats->min_val !== null ? (float) $stats->min_val : null,
                'max' => $stats->max_val !== null ? (float) $stats->max_val : null,
                'count' => $stats->cnt ?? 0,
                'unit' => $unit,
            ];
        }

        // Text / String / Fallback: Einfache String-Werte
        $rows = (clone $baseQuery)
            ->whereNotNull('value_string')
            ->where('value_string', '!=', '')
            ->select('value_string', DB::raw('COUNT(DISTINCT product_id) as cnt'))
            ->groupBy('value_string')
            ->orderByDesc('cnt')
            ->limit(50)
            ->get();

        return [
            'data_type' => 'ValueList',
            'values' => $rows->map(fn ($r) => [
                'value' => $r->value_string,
                'label' => $r->value_string,
                'count' => $r->cnt,
            ])->toArray(),
        ];
    }
}
