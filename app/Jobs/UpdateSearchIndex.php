<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Support\KoelnerPhonetik;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UpdateSearchIndex – Aktualisiert den denormalisierten products_search_index.
 *
 * Der Search-Index ist die zentrale Tabelle für PQL-Queries und
 * Produktlisten. Er vereint Daten aus products, product_attribute_values,
 * product_media_assignments und product_prices in einer flachen Struktur.
 *
 * Wird dispatcht von:
 * - ProductObserver (created/updated)
 * - AttributeValueObserver (saved/deleted)
 * - HierarchyNodeObserver (moved → subtree reindex)
 * - UpdateSearchIndexListener (Event-basiert)
 *
 * Latenz-Budget: Produktliste < 100ms, PQL einfach < 50ms
 * → Deshalb denormalisiert statt EAV-JOINs zur Laufzeit.
 */
class UpdateSearchIndex implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Anzahl Versuche bei Fehler.
     */
    public int $tries = 3;

    /**
     * Backoff zwischen Versuchen (Sekunden).
     */
    public array $backoff = [5, 15, 60];

    /**
     * Unique-Lock für 60 Sekunden → kein doppeltes Indexing
     * für dasselbe Produkt.
     */
    public int $uniqueFor = 60;

    public function __construct(
        public readonly string $productId,
    ) {
        $this->onQueue('indexing');
    }

    /**
     * Unique-ID für ShouldBeUnique.
     */
    public function uniqueId(): string
    {
        return 'update-search-index:' . $this->productId;
    }

    /**
     * Middleware: WithoutOverlapping verhindert parallele Ausführung
     * für dasselbe Produkt.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->productId))
                ->releaseAfter(30)
                ->expireAfter(120),
        ];
    }

    /**
     * Job ausführen: Produkt laden und Search-Index aktualisieren.
     */
    public function handle(): void
    {
        $product = Product::with([
            'productType',
        ])->find($this->productId);

        // Produkt wurde gelöscht → aus Index entfernen
        if (!$product) {
            DB::table('products_search_index')
                ->where('product_id', $this->productId)
                ->delete();

            Log::info('UpdateSearchIndex: Product not found, removed from index', [
                'product_id' => $this->productId,
            ]);

            return;
        }

        $nameDe = $this->getAttributeValue($product->id, 'productName', 'de');
        $nameEn = $this->getAttributeValue($product->id, 'productName', 'en');
        $descriptionDe = $this->getAttributeValue($product->id, 'description', 'de');

        DB::table('products_search_index')->updateOrInsert(
            ['product_id' => $product->id],
            [
                'sku' => $product->sku,
                'ean' => $product->ean,
                'product_type' => $product->productType?->technical_name,
                'status' => $product->status,
                'name_de' => $nameDe ? mb_substr($nameDe, 0, 500) : null,
                'name_en' => $nameEn ? mb_substr($nameEn, 0, 500) : null,
                'description_de' => $descriptionDe,
                'hierarchy_path' => $this->getHierarchyPath($product),
                'primary_image' => $this->getPrimaryImage($product->id),
                'list_price' => $this->getListPrice($product->id),
                'attribute_completeness' => $this->calculateCompleteness($product),
                'phonetic_name_de' => $nameDe
                    ? mb_substr(KoelnerPhonetik::encode($nameDe), 0, 100)
                    : null,
                'updated_at' => now(),
            ],
        );

        Log::debug('UpdateSearchIndex: Index updated', [
            'product_id' => $product->id,
            'sku' => $product->sku,
        ]);
    }

    /**
     * Attributwert aus der EAV-Tabelle lesen.
     *
     * Fallback-Kette: angeforderte Sprache → 'en' → 'de' → NULL (sprachunabhängig)
     */
    private function getAttributeValue(string $productId, string $technicalName, string $language): ?string
    {
        $query = ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('product_attribute_values.product_id', $productId)
            ->where('attributes.technical_name', $technicalName)
            ->where('product_attribute_values.multiplied_index', 0)
            ->whereIn('product_attribute_values.language', [$language, 'en', 'de', null])
            ->select('product_attribute_values.value_string');

        if (DB::getDriverName() === 'sqlite') {
            $query->orderByRaw(
                "CASE product_attribute_values.language WHEN ? THEN 1 WHEN 'en' THEN 2 WHEN 'de' THEN 3 ELSE 4 END",
                [$language]
            );
        } else {
            $query->orderByRaw("FIELD(product_attribute_values.language, ?, 'en', 'de', NULL)", [$language]);
        }

        return $query->first()?->value_string;
    }

    /**
     * Hierarchy-Path des Produkts über den master_hierarchy_node ermitteln.
     */
    private function getHierarchyPath(Product $product): ?string
    {
        if (!$product->master_hierarchy_node_id) {
            return null;
        }

        return DB::table('hierarchy_nodes')
            ->where('id', $product->master_hierarchy_node_id)
            ->value('path');
    }

    /**
     * Primäres Bild des Produkts ermitteln.
     */
    private function getPrimaryImage(string $productId): ?string
    {
        return ProductMediaAssignment::query()
            ->join('media', 'media.id', '=', 'product_media_assignments.media_id')
            ->where('product_media_assignments.product_id', $productId)
            ->where('product_media_assignments.is_primary', true)
            ->value('media.file_path');
    }

    /**
     * Listenpreis ermitteln (erster aktiver Preis vom Typ 'list_price').
     */
    private function getListPrice(string $productId): ?float
    {
        $price = ProductPrice::query()
            ->join('price_types', 'price_types.id', '=', 'product_prices.price_type_id')
            ->where('product_prices.product_id', $productId)
            ->where('price_types.technical_name', 'list_price')
            ->where(function ($q) {
                $q->whereNull('product_prices.valid_to')
                    ->orWhere('product_prices.valid_to', '>=', now()->toDateString());
            })
            ->orderBy('product_prices.valid_from', 'desc')
            ->value('product_prices.amount');

        return $price ? (float) $price : null;
    }

    /**
     * Attribut-Vollständigkeit berechnen.
     *
     * Formel: (Anzahl ausgefüllter Pflichtattribute / Gesamtzahl Pflichtattribute) × 100
     */
    private function calculateCompleteness(Product $product): int
    {
        // Pflichtattribute für dieses Produkt ermitteln
        // (über Hierarchie-Zuordnung + is_mandatory)
        $mandatoryCount = DB::table('attributes')
            ->where('is_mandatory', true)
            ->where('status', 'active')
            ->count();

        if ($mandatoryCount === 0) {
            return 100;
        }

        // Vorhandene Werte für Pflichtattribute zählen
        $filledCount = ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('product_attribute_values.product_id', $product->id)
            ->where('attributes.is_mandatory', true)
            ->where('product_attribute_values.multiplied_index', 0)
            ->where(function ($q) {
                $q->whereNotNull('product_attribute_values.value_string')
                    ->orWhereNotNull('product_attribute_values.value_number')
                    ->orWhereNotNull('product_attribute_values.value_date')
                    ->orWhereNotNull('product_attribute_values.value_flag')
                    ->orWhereNotNull('product_attribute_values.value_selection_id');
            })
            ->distinct('product_attribute_values.attribute_id')
            ->count('product_attribute_values.attribute_id');

        return (int) min(100, round(($filledCount / $mandatoryCount) * 100));
    }
}
