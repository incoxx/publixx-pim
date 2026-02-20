<?php

declare(strict_types=1);

namespace App\Services\Preview;

use App\Models\Product;
use App\Services\Inheritance\HierarchyInheritanceService;
use Illuminate\Support\Collection;

class ProductCompletenessService
{
    public function __construct(
        protected HierarchyInheritanceService $hierarchyService,
    ) {}

    /**
     * Calculate detailed completeness per section for a product.
     *
     * Returns overall percentage + per-section breakdown with missing fields.
     * Also includes an SVG gauge chart for visual representation.
     */
    public function calculateCompleteness(Product $product, string $lang): array
    {
        $product->load([
            'productType',
            'attributeValues.attribute',
            'prices',
            'relations',
            'media',
        ]);

        $sections = [];

        // 1. Stammdaten section
        $sections[] = $this->checkStammdaten($product, $lang);

        // 2. Attribute sections (grouped by collection_name)
        $attrSections = $this->checkAttributeSections($product, $lang);
        foreach ($attrSections as $section) {
            $sections[] = $section;
        }

        // 3. Prices (if product type supports prices)
        if (!$product->productType || $product->productType->has_prices !== false) {
            $sections[] = $this->checkPrices($product, $lang);
        }

        // 4. Media (if product type supports media)
        if (!$product->productType || $product->productType->has_media !== false) {
            $sections[] = $this->checkMedia($product, $lang);
        }

        // 5. Relations
        $sections[] = $this->checkRelations($product, $lang);

        // Calculate overall percentage
        $totalFields = 0;
        $filledFields = 0;
        foreach ($sections as $section) {
            $totalFields += $section['total'];
            $filledFields += $section['filled'];
        }

        $overallPercentage = $totalFields > 0
            ? (int) round(($filledFields / $totalFields) * 100)
            : 100;

        return [
            'overall_percentage' => $overallPercentage,
            'total_fields' => $totalFields,
            'filled_fields' => $filledFields,
            'sections' => $sections,
            'chart_svg' => $this->generateGaugeSvg($overallPercentage),
        ];
    }

    private function checkStammdaten(Product $product, string $lang): array
    {
        $fields = [
            ['field' => 'sku', 'label' => $lang === 'en' ? 'Article Number' : 'Artikelnummer', 'filled' => !empty($product->sku)],
            ['field' => 'name', 'label' => 'Name', 'filled' => !empty($product->name)],
            ['field' => 'status', 'label' => 'Status', 'filled' => !empty($product->status)],
        ];

        if ($product->productType?->has_ean !== false) {
            $fields[] = ['field' => 'ean', 'label' => 'EAN', 'filled' => !empty($product->ean)];
        }

        $filled = count(array_filter($fields, fn ($f) => $f['filled']));
        $total = count($fields);
        $missing = array_values(array_filter($fields, fn ($f) => !$f['filled']));

        return [
            'name' => $lang === 'en' ? 'Master Data' : 'Stammdaten',
            'icon' => 'database',
            'percentage' => $total > 0 ? (int) round(($filled / $total) * 100) : 100,
            'total' => $total,
            'filled' => $filled,
            'missing' => array_map(fn ($f) => [
                'field' => $f['field'],
                'label' => $f['label'],
                'is_mandatory' => true,
            ], $missing),
        ];
    }

    private function checkAttributeSections(Product $product, string $lang): array
    {
        $effectiveAttributes = $this->hierarchyService->getProductAttributes($product);

        if ($effectiveAttributes->isEmpty()) {
            return [];
        }

        // Index existing values by attribute_id
        $existingValueIds = $product->attributeValues
            ->filter(function ($val) {
                return $val->value_string !== null
                    || $val->value_number !== null
                    || $val->value_date !== null
                    || $val->value_flag !== null
                    || $val->value_selection_id !== null;
            })
            ->pluck('attribute_id')
            ->unique()
            ->toArray();

        // Group attributes by collection_name
        $grouped = $effectiveAttributes->groupBy(
            fn ($attr) => $attr->collection_name ?? ($lang === 'en' ? 'General' : 'Allgemein')
        );

        $sections = [];

        foreach ($grouped as $sectionName => $attributes) {
            $total = $attributes->count();
            $filled = 0;
            $missing = [];

            foreach ($attributes as $attr) {
                $hasFill = in_array($attr->attribute_id, $existingValueIds);

                if ($hasFill) {
                    $filled++;
                } else {
                    $label = $lang === 'en' && $attr->attribute_name_en
                        ? $attr->attribute_name_en
                        : $attr->attribute_name_de;

                    $missing[] = [
                        'attribute_id' => $attr->attribute_id,
                        'label' => $label,
                        'is_mandatory' => (bool) $attr->is_mandatory,
                    ];
                }
            }

            $sections[] = [
                'name' => $sectionName,
                'icon' => 'list',
                'percentage' => $total > 0 ? (int) round(($filled / $total) * 100) : 100,
                'total' => $total,
                'filled' => $filled,
                'missing' => $missing,
            ];
        }

        // Sort by collection_sort (use first attribute's collection_sort per group)
        usort($sections, function ($a, $b) use ($grouped) {
            $sortA = $grouped->get($a['name'])?->first()?->collection_sort ?? 0;
            $sortB = $grouped->get($b['name'])?->first()?->collection_sort ?? 0;
            return $sortA <=> $sortB;
        });

        return $sections;
    }

    private function checkPrices(Product $product, string $lang): array
    {
        $count = $product->prices->count();

        return [
            'name' => $lang === 'en' ? 'Prices' : 'Preise',
            'icon' => 'currency',
            'percentage' => $count > 0 ? 100 : 0,
            'total' => 1,
            'filled' => min($count, 1),
            'missing' => $count === 0 ? [[
                'field' => 'price',
                'label' => $lang === 'en' ? 'At least one price required' : 'Mindestens ein Preis erforderlich',
                'is_mandatory' => true,
            ]] : [],
        ];
    }

    private function checkMedia(Product $product, string $lang): array
    {
        $count = $product->media->count();

        return [
            'name' => 'Media',
            'icon' => 'image',
            'percentage' => $count > 0 ? 100 : 0,
            'total' => 1,
            'filled' => min($count, 1),
            'missing' => $count === 0 ? [[
                'field' => 'media',
                'label' => $lang === 'en' ? 'At least one media file required' : 'Mindestens eine Mediendatei erforderlich',
                'is_mandatory' => false,
            ]] : [],
        ];
    }

    private function checkRelations(Product $product, string $lang): array
    {
        $count = $product->relations->count();

        // Check if product type has allowed_relation_types configured
        $expected = 0;
        if ($product->productType?->allowed_relation_types) {
            $expected = 1; // at least one relation expected
        }

        if ($expected === 0) {
            // No relations expected by product type
            return [
                'name' => $lang === 'en' ? 'Relations' : 'Beziehungen',
                'icon' => 'link',
                'percentage' => 100,
                'total' => 0,
                'filled' => 0,
                'missing' => [],
            ];
        }

        return [
            'name' => $lang === 'en' ? 'Relations' : 'Beziehungen',
            'icon' => 'link',
            'percentage' => $count > 0 ? 100 : 0,
            'total' => 1,
            'filled' => min($count, 1),
            'missing' => $count === 0 ? [[
                'field' => 'relation',
                'label' => $lang === 'en' ? 'At least one relation expected' : 'Mindestens eine Beziehung erwartet',
                'is_mandatory' => false,
            ]] : [],
        ];
    }

    /**
     * Generate an SVG gauge chart showing the completeness percentage.
     *
     * Returns inline SVG string that can be rendered directly or embedded in PDF.
     */
    private function generateGaugeSvg(int $percentage): string
    {
        $color = match (true) {
            $percentage >= 80 => '#22c55e', // green
            $percentage >= 50 => '#f59e0b', // amber
            default => '#ef4444',           // red
        };

        $radius = 45;
        $circumference = 2 * M_PI * $radius;
        $dashOffset = $circumference - ($circumference * $percentage / 100);

        return <<<SVG
<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" width="120" height="120">
  <circle cx="60" cy="60" r="{$radius}" fill="none" stroke="#e5e7eb" stroke-width="10"/>
  <circle cx="60" cy="60" r="{$radius}" fill="none" stroke="{$color}" stroke-width="10"
    stroke-dasharray="{$circumference}" stroke-dashoffset="{$dashOffset}"
    stroke-linecap="round" transform="rotate(-90 60 60)"/>
  <text x="60" y="60" text-anchor="middle" dominant-baseline="central"
    font-family="sans-serif" font-size="22" font-weight="bold" fill="{$color}">{$percentage}%</text>
</svg>
SVG;
    }
}
