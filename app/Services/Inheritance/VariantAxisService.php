<?php

declare(strict_types=1);

namespace App\Services\Inheritance;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariantAxis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verwaltet die Merkmalsachsen, die die Varianten eines Elternprodukts
 * unterscheiden (z.B. Anschluss, Ausführung, Farbe — beliebig, pro Produkt
 * frei konfigurierbar). Stellt sicher, dass keine zwei Geschwister-Varianten
 * dieselbe Kombination aus Achsen-Werten tragen.
 */
class VariantAxisService
{
    public function __construct(
        private readonly VariantInheritanceService $variantInheritanceService,
    ) {
    }

    /**
     * Konfigurierte Achsen eines Elternprodukts, in Anzeigereihenfolge.
     *
     * @return Collection<int, ProductVariantAxis>
     */
    public function getAxes(Product $parent): Collection
    {
        return $parent->variantAxes()->with('attribute.valueList.entries')->get();
    }

    /**
     * @return array<int, string> attribute_ids der Achsen, in Reihenfolge
     */
    public function getAxisAttributeIds(Product $parent): array
    {
        return $parent->variantAxes()->pluck('attribute_id')->all();
    }

    /**
     * Achsen eines Elternprodukts ersetzen. Nach dem Setzen werden für alle
     * bestehenden Varianten automatisch override-Regeln für die Achsen-Attribute
     * materialisiert (eine geerbte Achse könnte Varianten nie unterscheiden),
     * und die bestehenden Varianten werden auf Duplikate geprüft.
     *
     * @param array<int, string> $attributeIds in gewünschter Reihenfolge
     *
     * @throws ValidationException
     */
    public function setAxes(Product $parent, array $attributeIds): void
    {
        $attributeIds = array_values(array_unique($attributeIds));

        if (!empty($attributeIds)) {
            $eligibleCount = Attribute::whereIn('id', $attributeIds)
                ->where('is_variant_attribute', true)
                ->count();

            if ($eligibleCount !== count($attributeIds)) {
                throw ValidationException::withMessages([
                    'attribute_ids' => 'Nur Attribute mit is_variant_attribute=true können als Varianten-Achse verwendet werden.',
                ]);
            }
        }

        DB::transaction(function () use ($parent, $attributeIds) {
            ProductVariantAxis::where('product_id', $parent->id)->delete();

            foreach ($attributeIds as $position => $attributeId) {
                ProductVariantAxis::create([
                    'product_id' => $parent->id,
                    'attribute_id' => $attributeId,
                    'position' => $position,
                ]);
            }

            $variants = $parent->variants()->get();

            foreach ($variants as $variant) {
                $this->ensureOverrideRules($variant, $attributeIds);
            }

            $this->assertNoDuplicatesAmong($variants, $attributeIds);
        });
    }

    /**
     * Normalisierte Achsen-Kombination einer Variante (eigene Werte, Master-Kontext).
     *
     * @param array<int, string>|null $axisAttributeIds vorab geladen, um N+1 zu vermeiden
     *
     * @return array<string, string|null> attribute_id => normalisierter Skalar
     */
    public function combinationFor(Product $variant, ?array $axisAttributeIds = null): array
    {
        $axisAttributeIds ??= $variant->parent_product_id
            ? $this->getAxisAttributeIds($variant->parentProduct)
            : [];

        if (empty($axisAttributeIds)) {
            return [];
        }

        $valuesByAttribute = ProductAttributeValue::where('product_id', $variant->id)
            ->whereIn('attribute_id', $axisAttributeIds)
            ->whereNull('output_hierarchy_id')
            ->where(function ($q) {
                $q->whereNull('language')->orWhere('language', 'de');
            })
            ->get()
            ->groupBy('attribute_id');

        $attributes = Attribute::whereIn('id', $axisAttributeIds)->get()->keyBy('id');

        $combination = [];
        foreach ($axisAttributeIds as $attributeId) {
            $rows = $valuesByAttribute->get($attributeId, collect());
            // Bei übersetzbaren Achsen-Attributen 'de' bevorzugen, sonst die
            // sprachunabhängige Zeile (Regelfall für Varianten-Achsen).
            $pav = $rows->firstWhere('language', 'de') ?? $rows->firstWhere('language', null);
            $attribute = $attributes->get($attributeId);
            $combination[$attributeId] = $this->normalizeValue($pav, $attribute);
        }

        ksort($combination);

        return $combination;
    }

    /**
     * Findet eine Geschwister-Variante mit identischer Achsen-Kombination.
     */
    public function findDuplicateSibling(Product $parent, array $combination, ?string $excludeVariantId = null): ?Product
    {
        if ($this->combinationIsEmpty($combination)) {
            return null;
        }

        $axisAttributeIds = array_keys($combination);

        $siblings = $parent->variants()
            ->when($excludeVariantId, fn ($q) => $q->where('id', '!=', $excludeVariantId))
            ->get();

        foreach ($siblings as $sibling) {
            $siblingCombination = $this->combinationFor($sibling, $axisAttributeIds);
            if (!$this->combinationIsEmpty($siblingCombination) && $siblingCombination === $combination) {
                return $sibling;
            }
        }

        return null;
    }

    /**
     * Wirft eine ValidationException, wenn eine Geschwister-Variante dieselbe
     * Achsen-Kombination trägt. No-op, wenn kein Elternprodukt, keine Achsen
     * konfiguriert sind oder die Kombination noch vollständig leer ist.
     *
     * @throws ValidationException
     */
    public function assertUniqueCombination(Product $variant): void
    {
        if (!$variant->parent_product_id) {
            return;
        }

        // Serialisiert konkurrierende Anfragen für dasselbe Elternprodukt: ohne
        // diese Sperre könnten zwei gleichzeitige Requests einander unsichtbar
        // bleiben und beide dieselbe Kombination erfolgreich anlegen. Wirkt nur
        // innerhalb einer offenen Transaktion (immer der Fall an allen
        // Aufrufstellen: store()/generate()/bulkUpdate()).
        Product::where('id', $variant->parent_product_id)->lockForUpdate()->first();

        $combination = $this->combinationFor($variant);
        $duplicate = $this->findDuplicateSibling($variant->parentProduct, $combination, $variant->id);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'variant' => "Diese Merkmalskombination ist bereits bei Variante \"{$duplicate->sku}\" vergeben.",
            ]);
        }
    }

    /**
     * Setzt für jede Achse des Elternprodukts inheritance_mode=override auf der
     * Variante — eine geerbte Achse hätte für alle Geschwister denselben Wert.
     *
     * @param array<int, string>|null $axisAttributeIds vorab geladen, um N+1 zu vermeiden
     */
    public function ensureOverrideRules(Product $variant, ?array $axisAttributeIds = null): void
    {
        if (!$variant->parent_product_id) {
            return;
        }

        $axisAttributeIds ??= $this->getAxisAttributeIds($variant->parentProduct);

        if (empty($axisAttributeIds)) {
            return;
        }

        $this->variantInheritanceService->setRules(
            $variant,
            array_fill_keys($axisAttributeIds, 'override')
        );
    }

    /**
     * Prüft eine Menge von Varianten paarweise auf identische Achsen-Kombinationen.
     *
     * @param Collection<int, Product> $variants
     * @param array<int, string> $axisAttributeIds
     *
     * @throws ValidationException
     */
    private function assertNoDuplicatesAmong(Collection $variants, array $axisAttributeIds): void
    {
        if (empty($axisAttributeIds)) {
            return;
        }

        $seen = [];
        foreach ($variants as $variant) {
            $combination = $this->combinationFor($variant, $axisAttributeIds);
            if ($this->combinationIsEmpty($combination)) {
                continue;
            }

            $key = json_encode($combination);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'attribute_ids' => "Varianten \"{$seen[$key]}\" und \"{$variant->sku}\" hätten mit dieser Achsen-Konfiguration dieselbe Merkmalskombination.",
                ]);
            }
            $seen[$key] = $variant->sku;
        }
    }

    private function combinationIsEmpty(array $combination): bool
    {
        return empty(array_filter($combination, fn ($value) => $value !== null));
    }

    private function normalizeValue(?ProductAttributeValue $pav, ?Attribute $attribute): ?string
    {
        if (!$pav || !$attribute) {
            return null;
        }

        if (in_array($attribute->data_type, ['Selection', 'Dictionary'], true) && $pav->value_selection_id !== null) {
            return (string) $pav->value_selection_id;
        }

        $column = Attribute::storageColumn($attribute->data_type);
        $value = $pav->{$column};

        if ($value === null) {
            return null;
        }

        return is_string($value) ? mb_strtolower(trim($value)) : (string) $value;
    }
}
