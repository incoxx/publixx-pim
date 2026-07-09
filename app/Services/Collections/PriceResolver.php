<?php

declare(strict_types=1);

namespace App\Services\Collections;

use App\Models\Organization;
use App\Models\PriceRegion;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Builder;

/**
 * Preisaufloesung fuer Collection-Positionen.
 *
 * Es gibt in diesem Repo keinen kanonischen Preis-Resolver (MappingResolver::resolvePrice()
 * ist naiv; ShopwareProductService/ShopifyProductService referenzieren die bereits entfernte
 * product_prices.country-Spalte). Diese Implementierung nutzt price_regions.code (nicht
 * technical_name -- das Feld existiert nicht) + scale_from/to + valid_from/to.
 *
 * price_regions hat KEIN is_default-Flag. Ohne aufloesbare Region werden ausschliesslich
 * regionsunabhaengige Preise (price_region_id IS NULL) beruecksichtigt -- ein Preis aus
 * einer zufaelligen anderen Region waere falsch, nicht "gut genug".
 */
class PriceResolver
{
    public function resolve(
        Product $product,
        ?Organization $organization,
        string $priceTypeTechnicalName,
        float $quantity = 1.0,
        ?string $currency = null,
    ): ?ResolvedPrice {
        $priceType = PriceType::where('technical_name', $priceTypeTechnicalName)->first();

        if ($priceType === null) {
            return null;
        }

        $regionId = $this->resolveRegionId($organization);
        $resolvedCurrency = $currency ?? $organization?->currency;

        // Exakte Region zuerst, sonst regionsunabhaengiger Preis (price_region_id IS NULL).
        if ($regionId !== null) {
            $price = $this->pickBestPrice(
                $this->baseQuery($product, $priceType->id, $resolvedCurrency)->where('price_region_id', $regionId),
                $quantity
            );
            if ($price !== null) {
                return $this->toResolvedPrice($price, $priceType->id);
            }
        }

        $price = $this->pickBestPrice(
            $this->baseQuery($product, $priceType->id, $resolvedCurrency)->whereNull('price_region_id'),
            $quantity
        );

        return $price !== null ? $this->toResolvedPrice($price, $priceType->id) : null;
    }

    private function resolveRegionId(?Organization $organization): ?string
    {
        if ($organization === null || empty($organization->price_list_ref)) {
            return null;
        }

        return PriceRegion::where('code', $organization->price_list_ref)->value('id');
    }

    private function baseQuery(Product $product, string $priceTypeId, ?string $currency): Builder
    {
        $query = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('price_type_id', $priceTypeId);

        if ($currency !== null) {
            $query->where('currency', $currency);
        }

        return $query;
    }

    /**
     * Bestpassenden Preis fuer eine Menge waehlen: zuerst nach Staffel (scale_from <= qty,
     * beste = hoechster passender scale_from), dann nach Gueltigkeit (aktuell gueltig vor
     * juengst abgelaufen).
     */
    private function pickBestPrice(Builder $query, float $quantity): ?ProductPrice
    {
        $candidates = (clone $query)
            ->where(function (Builder $q) use ($quantity) {
                $q->whereNull('scale_from')->orWhere('scale_from', '<=', $quantity);
            })
            ->where(function (Builder $q) use ($quantity) {
                $q->whereNull('scale_to')->orWhere('scale_to', '>=', $quantity);
            })
            ->orderByDesc('scale_from')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $current = $candidates->first(function (ProductPrice $p) {
            $validFromOk = $p->valid_from === null || $p->valid_from->lessThanOrEqualTo(now());
            $validToOk = $p->valid_to === null || $p->valid_to->greaterThanOrEqualTo(now());

            return $validFromOk && $validToOk;
        });

        if ($current !== null) {
            return $current;
        }

        // Kein aktuell gueltiger Preis -- juengsten abgelaufenen nehmen (edge case #7).
        return $candidates
            ->filter(fn (ProductPrice $p) => $p->valid_to !== null && $p->valid_to->isPast())
            ->sortByDesc(fn (ProductPrice $p) => $p->valid_to)
            ->first();
    }

    private function toResolvedPrice(ProductPrice $price, string $priceTypeId): ResolvedPrice
    {
        $isExpired = $price->valid_to !== null && $price->valid_to->isPast();

        return new ResolvedPrice(
            amount: (float) $price->amount,
            currency: $price->currency ?? 'EUR',
            priceTypeId: $priceTypeId,
            isExpired: $isExpired,
        );
    }
}
