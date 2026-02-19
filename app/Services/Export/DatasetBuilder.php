<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Product;
use App\Models\PublixxExportMapping;
use Illuminate\Support\Arr;

/**
 * Builds a JSON dataset from a product + mapping configuration.
 *
 * Supports three output formats:
 * - flat:    All fields at top level, dots in keys preserved
 * - nested:  Dot-notation keys expanded to nested objects
 * - publixx: Nested + standard envelope fields (id, sku, ean, status, hierarchy)
 */
class DatasetBuilder
{
    public function __construct(
        protected MappingResolver $mappingResolver
    ) {}

    /**
     * Build a dataset for a single product.
     *
     * @param  Product              $product  The product with relations loaded
     * @param  PublixxExportMapping  $mapping  The export mapping configuration
     * @param  array                $options  Extra context: resolvedValues, attributeAssignments, etc.
     * @return array
     */
    public function build(Product $product, PublixxExportMapping $mapping, array $options = []): array
    {
        $languages = $mapping->languages ?? ['de'];
        $rules = $mapping->mapping_rules['rules'] ?? [];
        $format = $mapping->flatten_mode ?? 'nested';

        // Merge context from product relations into options
        $options = $this->enrichOptions($product, $mapping, $options);

        // Resolve all mapping rules → flat key=>value map
        $resolved = $this->mappingResolver->resolve($rules, $product, $languages, $options);

        // Convert to target format
        return match ($format) {
            'flat' => $this->buildFlat($product, $resolved, $languages),
            'nested' => $this->buildNested($product, $resolved, $languages),
            'publixx' => $this->buildPublixx($product, $resolved, $languages, $options),
            default => $this->buildNested($product, $resolved, $languages),
        };
    }

    /**
     * Build multiple datasets for a collection of products.
     *
     * @param  iterable             $products
     * @param  PublixxExportMapping  $mapping
     * @param  array                $options
     * @return array
     */
    public function buildMany(iterable $products, PublixxExportMapping $mapping, array $options = []): array
    {
        $datasets = [];

        foreach ($products as $product) {
            $datasets[] = $this->build($product, $mapping, $options);
        }

        return $datasets;
    }

    // ─── Format Builders ────────────────────────────────────────

    /**
     * Flat format: all keys at top level, dots preserved in key names.
     */
    protected function buildFlat(Product $product, array $resolved, array $languages): array
    {
        $dataset = [
            'id' => $product->id,
            'sku' => $product->sku,
        ];

        foreach ($resolved as $key => $value) {
            $dataset[$key] = $value;
        }

        return $dataset;
    }

    /**
     * Nested format: dot-notation keys expanded into nested objects.
     */
    protected function buildNested(Product $product, array $resolved, array $languages): array
    {
        $dataset = [
            'id' => $product->id,
            'sku' => $product->sku,
        ];

        foreach ($resolved as $key => $value) {
            $this->setNestedValue($dataset, $key, $value);
        }

        return $dataset;
    }

    /**
     * Publixx format: nested + standard envelope fields.
     */
    protected function buildPublixx(Product $product, array $resolved, array $languages, array $options): array
    {
        $dataset = [
            'id' => $product->id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'status' => $product->status,
        ];

        // Add product name in all requested languages
        $primaryLang = $languages[0] ?? 'de';
        $dataset['productName'] = $product->name;

        // Hierarchy path
        $dataset['hierarchy'] = $this->resolveHierarchyPath($product, $options);

        // Expand all resolved fields with dot-notation
        foreach ($resolved as $key => $value) {
            $this->setNestedValue($dataset, $key, $value);
        }

        // Add price currency if price fields exist
        $this->addPriceCurrency($dataset, $product, $options);

        return $dataset;
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Enrich options with product's loaded relations.
     */
    protected function enrichOptions(Product $product, PublixxExportMapping $mapping, array $options): array
    {
        if ($mapping->include_media && !isset($options['media'])) {
            $options['media'] = $product->relationLoaded('mediaAssignments')
                ? $product->mediaAssignments
                : collect();
        }

        if ($mapping->include_prices && !isset($options['prices'])) {
            $options['prices'] = $product->relationLoaded('prices')
                ? $product->prices
                : collect();
        }

        if ($mapping->include_variants && !isset($options['variants'])) {
            $options['variants'] = $product->relationLoaded('variants')
                ? $product->variants
                : collect();
        }

        if ($mapping->include_relations && !isset($options['relations'])) {
            $options['relations'] = $product->relationLoaded('outgoingRelations')
                ? $product->outgoingRelations
                : collect();
        }

        if (!isset($options['attributeValues'])) {
            $options['attributeValues'] = $product->relationLoaded('attributeValues')
                ? $product->attributeValues
                : collect();
        }

        return $options;
    }

    /**
     * Set a nested value using dot-notation key.
     * e.g. "preis.listenpreis" with value 189.99 → ['preis' => ['listenpreis' => 189.99]]
     */
    public function setNestedValue(array &$target, string $key, mixed $value): void
    {
        // Check if key contains a language suffix that should NOT be nested
        // e.g. "productName_en" stays flat, "preis.listenpreis" nests
        if (!str_contains($key, '.')) {
            $target[$key] = $value;
            return;
        }

        $parts = explode('.', $key);
        $current = &$target;

        for ($i = 0; $i < count($parts) - 1; $i++) {
            $part = $parts[$i];
            if (!isset($current[$part]) || !is_array($current[$part])) {
                $current[$part] = [];
            }
            $current = &$current[$part];
        }

        $current[end($parts)] = $value;
    }

    /**
     * Resolve the hierarchy path for a product.
     */
    protected function resolveHierarchyPath(Product $product, array $options): ?string
    {
        if (isset($options['hierarchyPath'])) {
            return $options['hierarchyPath'];
        }

        // Try to build from master hierarchy node
        $node = $product->masterHierarchyNode ?? null;
        if ($node === null) {
            return null;
        }

        // Use the materialized path to build a readable hierarchy
        // Path format: /node-1/node-2/ → "Node 1 > Node 2"
        $pathSegments = [];
        $current = $node;

        // Walk up the tree collecting names
        while ($current) {
            $pathSegments[] = $current->name_de;
            $current = $current->parent ?? null;
        }

        return implode(' > ', array_reverse($pathSegments));
    }

    /**
     * If price data exists, add currency info.
     */
    protected function addPriceCurrency(array &$dataset, Product $product, array $options): void
    {
        $prices = $options['prices'] ?? collect();
        if ($prices->isEmpty()) {
            return;
        }

        $firstPrice = $prices->first();
        $currency = $firstPrice->currency ?? 'EUR';

        // Walk through dataset and find price nodes to add currency
        if (isset($dataset['preis']) && is_array($dataset['preis'])) {
            $dataset['preis']['currency'] = $currency;
        }
    }
}
