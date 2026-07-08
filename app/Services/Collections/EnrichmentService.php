<?php

declare(strict_types=1);

namespace App\Services\Collections;

use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Product;
use App\Services\Export\MappingResolver;

/**
 * Zieht den aktuellen gerenderten Produktzustand fuer eine Collection-Position ueber die
 * bestehende Export-/Mapping-Pipeline (MappingResolver) statt eine eigene Flatten-Logik zu
 * bauen. ExportProductHelpers/buildFilteredProductQuery existieren in diesem Repo NICHT
 * (verifiziert) -- MappingResolver::resolve() ist der reale, wiederverwendbare Einstiegspunkt.
 *
 * Positionen ohne product_id (Freitext) werden nicht angereichert (edge case #5).
 */
class EnrichmentService
{
    public function __construct(
        private readonly MappingResolver $mappingResolver,
        private readonly PriceResolver $priceResolver,
    ) {}

    /**
     * Angereicherter, abgeleiteter Zustand einer Position -- Grundlage fuer den Snapshot
     * (SnapshotService, Phase 3) und die Live-Vorschau.
     *
     * @return array{name: ?string, resolved_attributes: array, resolved_price: ?array, price_warning: bool}
     */
    public function enrichItem(CollectionItem $item, Collection $collection): array
    {
        if ($item->product_id === null) {
            // Freitextposition: nichts vom Produkt abzuleiten.
            return $item->snapshot ?? [];
        }

        $product = $item->relationLoaded('product') ? $item->product : Product::find($item->product_id);

        if ($product === null) {
            // Produkt wurde geloescht, aber ueber die FK (set null) ist product_id bereits
            // NULL -- dieser Zweig greift nur in einem Zwischenzustand innerhalb derselben
            // Transaktion und liefert dann den zuletzt bekannten Snapshot.
            return $item->snapshot ?? [];
        }

        $rules = $this->itemRules($collection);
        $options = $this->buildOptions($product);
        $language = $collection->language ?? 'de';

        $priceTypeTechnicalName = $this->extractPriceTypeTechnicalName($rules);
        // 'price'-Regeln werden ueber PriceResolver behandelt (MappingResolver::resolvePrice()
        // ist naiv) -- hier ausfiltern statt eine ungenutzte/leere Zielspalte mitzuschleppen.
        $attributeRules = array_values(array_filter($rules, fn (array $rule) => ($rule['type'] ?? null) !== 'price'));

        $resolved = $this->resolveWithLanguageFallback($attributeRules, $product, $language, $options);

        $resolvedPrice = null;

        if ($priceTypeTechnicalName !== null) {
            $price = $this->priceResolver->resolve(
                $product,
                $collection->organization,
                $priceTypeTechnicalName,
                (float) $item->quantity,
                $collection->currency
            );

            $resolvedPrice = $price !== null ? [
                'amount' => $price->amount,
                'currency' => $price->currency,
                'is_expired' => $price->isExpired,
            ] : null;
        }

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'resolved_attributes' => $resolved,
            'resolved_price' => $resolvedPrice,
            // edge case #6: kein Preis gefunden -- Ausgabe rendert trotzdem, nie blockierend.
            'price_warning' => $priceTypeTechnicalName !== null && $resolvedPrice === null,
        ];
    }

    /**
     * default_item_attribute_groups speichert Mapping-Regeln im selben Format wie
     * PublixxExportMapping.mapping_rules['rules'] ({source, target, type}).
     */
    private function itemRules(Collection $collection): array
    {
        return $collection->collectionType?->default_item_attribute_groups ?? [];
    }

    /**
     * language -> 'de' Fallback fuer Felder, die MappingResolver in der Zielsprache nicht
     * findet (MappingResolver selbst liefert bei fehlender Uebersetzung NULL, keine
     * Fallback-Kette). Volle TMS-Anbindung (laufende Uebersetzungsjobs pruefen) ist ein
     * spaeterer Ausbauschritt -- die Nie-leer-Garantie ist hier ueber den de-Fallback erfuellt.
     */
    private function resolveWithLanguageFallback(array $rules, Product $product, string $language, array $options): array
    {
        $resolved = $this->mappingResolver->resolve($rules, $product, [$language], $options);

        if ($language === 'de') {
            return $resolved;
        }

        $fallback = null;
        foreach ($resolved as $key => $value) {
            if ($value !== null) {
                continue;
            }
            $fallback ??= $this->mappingResolver->resolve($rules, $product, ['de'], $options);
            if (($fallback[$key] ?? null) !== null) {
                $resolved[$key] = $fallback[$key];
            }
        }

        return $resolved;
    }

    private function extractPriceTypeTechnicalName(array $rules): ?string
    {
        foreach ($rules as $rule) {
            if (($rule['type'] ?? null) === 'price' && str_starts_with($rule['source'] ?? '', 'prices:')) {
                return substr($rule['source'], strlen('prices:'));
            }
        }

        return null;
    }

    /**
     * @see \App\Services\Export\DatasetBuilder::enrichOptions() -- gleiches Options-Schema,
     * hier ohne PublixxExportMapping-Abhaengigkeit direkt zusammengestellt.
     */
    private function buildOptions(Product $product): array
    {
        return [
            'attributeValues' => $product->relationLoaded('attributeValues')
                ? $product->attributeValues
                : $product->attributeValues()->with(['attribute', 'valueListEntry'])->get(),
            'media' => $product->relationLoaded('mediaAssignments')
                ? $product->mediaAssignments
                : collect(),
            'prices' => collect(),
            'variants' => $product->relationLoaded('variants')
                ? $product->variants
                : collect(),
            'relations' => $product->relationLoaded('outgoingRelations')
                ? $product->outgoingRelations
                : $product->outgoingRelations()->with('targetProduct')->get(),
        ];
    }
}
