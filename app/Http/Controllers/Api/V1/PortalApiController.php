<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Attribute;
use App\Models\PortalConfig;
use App\Models\ProductAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

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

        foreach ($steps as $step) {
            $attributeId = $step['attribute_id'] ?? null;
            if (!$attributeId) {
                continue;
            }

            $attribute = Attribute::find($attributeId);
            if (!$attribute) {
                continue;
            }

            $values = $this->aggregateAttributeValues($attribute, $step);

            $result[] = [
                'key' => $step['key'] ?? $attribute->technical_name,
                'attribute_id' => $attributeId,
                'label' => $step['label'] ?? $attribute->name_de ?? $attribute->technical_name,
                'widget' => $step['widget'] ?? 'filter-dropdown',
                'values' => $values,
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Aggregiert einzigartige Attributwerte mit Produktanzahl.
     */
    private function aggregateAttributeValues(Attribute $attribute, array $step): array
    {
        $query = ProductAttributeValue::where('attribute_id', $attribute->id)
            ->whereNotNull('value_string')
            ->where('value_string', '!=', '');

        // Pipe-separierte Werte (z.B. Laender: "AU|BR|CA|DE") aufspalten
        $rawValues = $query->pluck('value_string')->filter()->unique();

        $isPipeSeparated = $rawValues->contains(fn ($v) => str_contains($v, '|'));

        if ($isPipeSeparated) {
            $valueCounts = [];
            foreach ($rawValues as $raw) {
                foreach (explode('|', $raw) as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $valueCounts[$part] = ($valueCounts[$part] ?? 0) + 1;
                    }
                }
            }
            ksort($valueCounts);

            return collect($valueCounts)->map(fn ($count, $value) => [
                'value' => $value,
                'label' => $value,
                'count' => $count,
            ])->values()->all();
        }

        // Einfache Werte: gruppieren und zaehlen
        $valueCounts = ProductAttributeValue::where('attribute_id', $attribute->id)
            ->whereNotNull('value_string')
            ->where('value_string', '!=', '')
            ->selectRaw('value_string as value, COUNT(DISTINCT product_id) as count')
            ->groupBy('value_string')
            ->orderBy('value_string')
            ->get();

        return $valueCounts->map(fn ($row) => [
            'value' => $row->value,
            'label' => $row->value,
            'count' => $row->count,
        ])->all();
    }
}
