<?php

declare(strict_types=1);

namespace App\Services\PimSync;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PimSyncExportService
{
    /**
     * Produkte im anyPIM-Transfer-Format exportieren (paginiert).
     *
     * @param  array{
     *   hierarchy_id?: string,
     *   product_type_ids?: string[],
     *   skus?: string[],
     *   updated_since?: string,
     *   per_page?: int,
     *   page?: int,
     * } $filters
     */
    public function exportProducts(array $filters = []): array
    {
        $perPage = min($filters['per_page'] ?? 100, 500);
        $page = $filters['page'] ?? 1;

        $query = $this->buildQuery($filters);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $products = $paginator->getCollection()->map(
            fn (Product $product) => $this->serializeProduct($product)
        );

        return [
            'products' => $products->toArray(),
            'meta' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Einzelnes Produkt im Transfer-Format exportieren.
     */
    public function exportProductBySku(string $sku): ?array
    {
        $product = Product::with([
            'attributeValues.attribute',
            'attributeValues.unit',
            'attributeValues.valueListEntry',
            'prices.priceType',
            'prices.priceRegion',
            'mediaAssignments.media',
            'mediaAssignments.usageType',
            'productType',
            'manufacturer',
        ])->where('sku', $sku)->first();

        if (! $product) {
            return null;
        }

        return $this->serializeProduct($product);
    }

    /**
     * Checksums für Delta-Sync berechnen.
     *
     * @return array<string, array{checksum: string, updated_at: string}>
     */
    public function calculateChecksums(array $filters = []): array
    {
        $query = $this->buildQuery($filters);

        $checksums = [];

        $query->select(['id', 'sku', 'updated_at'])
            ->chunk(1000, function ($products) use (&$checksums) {
                foreach ($products as $product) {
                    $checksums[$product->sku] = [
                        'checksum'   => md5($product->sku . '|' . $product->updated_at->timestamp),
                        'updated_at' => $product->updated_at->toIso8601String(),
                    ];
                }
            });

        return $checksums;
    }

    /**
     * Attribut-Definitionen exportieren (für Schema-Sync).
     */
    public function exportAttributes(): array
    {
        return Attribute::orderBy('technical_name')
            ->get()
            ->map(fn (Attribute $attr) => [
                'technical_name' => $attr->technical_name,
                'name_de'        => $attr->name_de,
                'name_en'        => $attr->name_en,
                'data_type'      => $attr->data_type,
                'is_translatable' => $attr->is_translatable,
                'is_multipliable' => $attr->is_multipliable,
                'source_system'  => $attr->source_system,
                'unit_group'     => $attr->unitGroup?->name,
                'default_unit'   => $attr->defaultUnit?->symbol,
            ])
            ->toArray();
    }

    /**
     * Produkt in das standardisierte Transfer-Format serialisieren.
     */
    private function serializeProduct(Product $product): array
    {
        return [
            'sku'    => $product->sku,
            'ean'    => $product->ean,
            'name'   => $product->name,
            'status' => $product->status,
            'product_type' => $product->productType?->technical_name,
            'manufacturer' => $product->manufacturer?->name,
            'attributes'   => $this->serializeAttributes($product),
            'prices'       => $this->serializePrices($product),
            'media'        => $this->serializeMedia($product),
            '_checksum'    => md5($product->sku . '|' . $product->updated_at->timestamp),
            '_updated_at'  => $product->updated_at->toIso8601String(),
        ];
    }

    /**
     * Attributwerte serialisieren (gruppiert nach technical_name, multi-language).
     */
    private function serializeAttributes(Product $product): array
    {
        $attributes = [];

        foreach ($product->attributeValues as $pav) {
            $attr = $pav->attribute;
            if (! $attr) {
                continue;
            }

            $techName = $attr->technical_name;
            $value = $this->extractAttributeValue($pav);

            if ($attr->is_translatable && $pav->language) {
                $attributes[$techName][$pav->language] = $value;
            } else {
                // Nicht-übersetzbare Attribute: ggf. unit anhängen
                if ($pav->unit) {
                    $attributes[$techName] = [
                        'value' => $value,
                        'unit'  => $pav->unit->symbol ?? $pav->unit->abbreviation ?? null,
                    ];
                } else {
                    $attributes[$techName] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * Den konkreten Wert aus einem ProductAttributeValue extrahieren.
     */
    private function extractAttributeValue($pav): mixed
    {
        if ($pav->value_selection_id !== null) {
            return $pav->valueListEntry?->technical_name
                ?? $pav->valueListEntry?->value
                ?? $pav->value_selection_id;
        }
        if ($pav->value_flag !== null) {
            return $pav->value_flag;
        }
        if ($pav->value_number !== null) {
            return (float) $pav->value_number;
        }
        if ($pav->value_date !== null) {
            return $pav->value_date->toDateString();
        }

        return $pav->value_string;
    }

    /**
     * Preise serialisieren.
     */
    private function serializePrices(Product $product): array
    {
        return $product->prices->map(fn ($price) => [
            'type'       => $price->priceType?->technical_name,
            'amount'     => (float) $price->amount,
            'currency'   => $price->currency,
            'region'     => $price->priceRegion?->name,
            'valid_from' => $price->valid_from?->toDateString(),
            'valid_to'   => $price->valid_to?->toDateString(),
            'scale_from' => $price->scale_from,
            'scale_to'   => $price->scale_to,
        ])->toArray();
    }

    /**
     * Media-Zuordnungen serialisieren.
     */
    private function serializeMedia(Product $product): array
    {
        return $product->mediaAssignments->map(fn ($assignment) => [
            'file_name'    => $assignment->media?->file_name,
            'file_path'    => $assignment->media?->file_path,
            'mime_type'    => $assignment->media?->mime_type,
            'usage_type'   => $assignment->usageType?->technical_name,
            'is_primary'   => $assignment->is_primary,
            'sort_order'   => $assignment->sort_order,
            'title_de'     => $assignment->media?->title_de,
            'title_en'     => $assignment->media?->title_en,
            'alt_text_de'  => $assignment->media?->alt_text_de,
            'alt_text_en'  => $assignment->media?->alt_text_en,
        ])->toArray();
    }

    /**
     * Query mit Filtern aufbauen.
     */
    private function buildQuery(array $filters): Builder
    {
        $query = Product::with([
            'attributeValues.attribute',
            'attributeValues.unit',
            'attributeValues.valueListEntry',
            'prices.priceType',
            'prices.priceRegion',
            'mediaAssignments.media',
            'mediaAssignments.usageType',
            'productType',
            'manufacturer',
        ]);

        if (! empty($filters['hierarchy_id'])) {
            $query->where('master_hierarchy_node_id', $filters['hierarchy_id']);
        }

        if (! empty($filters['product_type_ids'])) {
            $query->whereIn('product_type_id', $filters['product_type_ids']);
        }

        if (! empty($filters['skus'])) {
            $query->whereIn('sku', $filters['skus']);
        }

        if (! empty($filters['updated_since'])) {
            $query->where('updated_at', '>=', $filters['updated_since']);
        }

        // Nur Hauptprodukte, keine Varianten (Varianten werden als Unterobjekte gesynct)
        $query->where('product_type_ref', 'product');

        return $query->orderBy('sku');
    }
}
