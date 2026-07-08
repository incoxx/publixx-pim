<?php

declare(strict_types=1);

namespace App\Services\Collections;

use App\Models\Attribute;
use App\Models\Collection;
use App\Models\CollectionAttributeValue;
use App\Models\CollectionType;
use App\Models\Organization;
use App\Models\Product;
use App\Services\Collections\DTO\OfferContext;
use App\Services\Import\FuzzyMatcher;
use App\Services\Import\ReferenceResolver;
use Illuminate\Support\Facades\DB;

/**
 * Materialisiert eine OfferContext-DTO (aus einem Inbound-Adapter) zu collection +
 * collection_items. SKU-Matching nutzt App\Services\Import\ReferenceResolver /
 * App\Services\Import\FuzzyMatcher (Import-Praezedenzfall) -- NICHT
 * App\Services\Pql\FuzzyMatcher (das ist die PQL-Textsuche, nicht fuer SKU-Matching
 * gebaut). ReferenceResolver::resolveProduct() liefert bei fehlendem exaktem Match
 * nur den EINEN besten Fuzzy-Treffer; fuer die "mehrere gleich gute Treffer"-Anforderung
 * (edge case #3) wird bei Nicht-Exakt-Match zusaetzlich FuzzyMatcher::findAllMatches()
 * direkt aufgerufen, um die volle Kandidatenliste zu bekommen.
 *
 * Import-Status (matched/unconfirmed/unresolved) wird NICHT als eigene Spalte auf
 * collection_items gefuehrt (Nutzerentscheidung: Brief-Schema woertlich beibehalten),
 * sondern ueber zwei interne/versteckte collection_item-Attribute
 * (_import_match_status, _import_fuzzy_candidates) geschrieben -- siehe
 * DemoCollectionSeeder fuer deren Seeding.
 */
class CollectionFactory
{
    /** @var \Illuminate\Support\Collection<int, Product>|null */
    private ?\Illuminate\Support\Collection $productIndex = null;

    public function __construct(
        private readonly ReferenceResolver $referenceResolver,
        private readonly FuzzyMatcher $fuzzyMatcher,
    ) {}

    public function fromOfferContext(OfferContext $context, CollectionType $type): Collection
    {
        return DB::transaction(function () use ($context, $type) {
            // Explizite Blank-Pruefung statt ?: -- eine legitime Referenz "0" darf nicht als
            // leer gelten und durch den generierten Fallback-Namen ersetzt werden.
            $reference = $context->reference;
            $collection = Collection::create([
                'collection_type_id' => $type->id,
                'name' => ($reference !== null && $reference !== '') ? $reference : ($type->name_de . ' Import ' . now()->format('Y-m-d H:i')),
                'reference' => $context->reference,
                'currency' => $context->currency,
                'valid_until' => $context->validUntil,
                'source_channel' => $context->meta['source_channel'] ?? 'import',
                'status' => 'draft',
            ]);

            $this->resolveOrganization($collection, $context->organization);

            $position = 0;
            foreach ($context->items as $itemData) {
                $position += 10;
                $this->createItem($collection, $itemData, $position);
            }

            return $collection->fresh(['items', 'organization', 'collectionType']);
        });
    }

    /**
     * Edge case #9: RFQ bringt Empfaenger, keine persistierte Organisation ->
     * organization_snapshot inline fuellen, organization_id = NULL. Nie spekulativ
     * eine Organisation aus unbestaetigten RFQ-Daten anlegen.
     */
    private function resolveOrganization(Collection $collection, ?array $orgData): void
    {
        if ($orgData === null) {
            return;
        }

        $externalRef = $orgData['external_ref'] ?? null;
        $organization = $externalRef ? Organization::where('external_ref', $externalRef)->first() : null;

        if ($organization) {
            $collection->organization_id = $organization->id;
        } else {
            $collection->organization_snapshot = $orgData;
        }

        $collection->save();
    }

    private function createItem(Collection $collection, array $itemData, int $position): void
    {
        $skuCandidate = $itemData['sku_candidate'] ?? $itemData['external_product_id'] ?? null;
        $match = $skuCandidate
            ? $this->matchProduct($skuCandidate)
            : ['product_id' => null, 'status' => 'unresolved', 'candidates' => []];

        $item = $collection->items()->create([
            'product_id' => $match['product_id'],
            'quantity' => $itemData['quantity'] ?? 1,
            // Reihenfolge aus Import-Ankunft, 10er-Schritte -- Quellposition wird bewusst
            // ignoriert (edge case #10: deterministische Neuvergabe statt Kollisionsrisiko).
            'position' => $position,
        ]);

        // Edge case #1/#5: kein Produktbezug -> Snapshot sofort aus Freitext fuellen,
        // damit die Position nie kommentarlos verschwindet.
        if ($match['product_id'] === null) {
            // Explizite Blank-Pruefung statt ?: -- ein legitimer note-/SKU-Wert "0" darf
            // nicht als leer gelten und stillschweigend durch den Fallback-Text ersetzt werden.
            $note = $itemData['note'] ?? null;
            $name = ($note !== null && $note !== '')
                ? $note
                : (($skuCandidate !== null && $skuCandidate !== '') ? $skuCandidate : 'Unaufgeloeste Position');

            $item->snapshot = [
                'name' => $name,
                'note' => $note,
                'sku_candidate' => $skuCandidate,
                'unit' => $itemData['unit'] ?? null,
            ];
            $item->save();
        }

        $this->writeMatchStatus($item->id, $match['status'], $match['candidates']);
    }

    /**
     * @return array{product_id: ?string, status: string, candidates: array}
     */
    private function matchProduct(string $skuCandidate): array
    {
        $exact = $this->referenceResolver->resolveProduct($skuCandidate);
        if ($exact->found) {
            return ['product_id' => $exact->id, 'status' => 'matched', 'candidates' => []];
        }

        $products = $this->productIndex ??= Product::all(['id', 'sku', 'name']);
        $matches = $this->fuzzyMatcher->findAllMatches($skuCandidate, $products->pluck('sku')->all(), limit: 5);

        if (empty($matches)) {
            // Edge case #1: kein Treffer -> unresolved, Position bleibt (nie stumm verwerfen).
            return ['product_id' => null, 'status' => 'unresolved', 'candidates' => []];
        }

        // Edge case #2/#3: ein oder mehrere Fuzzy-Kandidaten -> immer 'unconfirmed',
        // product_id bleibt NULL bis ein Mensch bestaetigt (CollectionItemMatchController).
        $candidates = [];
        foreach ($matches as $fuzzyMatch) {
            $product = $products->firstWhere('sku', $fuzzyMatch->match);
            if ($product) {
                $candidates[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'similarity' => round($fuzzyMatch->similarity, 4),
                ];
            }
        }

        return ['product_id' => null, 'status' => 'unconfirmed', 'candidates' => $candidates];
    }

    private function writeMatchStatus(string $itemId, string $status, array $candidates): void
    {
        $statusAttr = Attribute::where('technical_name', '_import_match_status')->first();
        if (!$statusAttr) {
            // Seeder (DemoCollectionSeeder) noch nicht gelaufen -- Match-Status geht dann
            // nicht verloren (Produkt-Zuordnung selbst ist bereits korrekt gesetzt), nur
            // die Review-Queue-Markierung fehlt. Bewusst kein Hard-Fail hier.
            return;
        }

        CollectionAttributeValue::updateOrCreate(
            ['owner_type' => 'collection_item', 'owner_id' => $itemId, 'attribute_id' => $statusAttr->id, 'language' => null, 'multiplied_index' => 0],
            ['value_string' => $status]
        );

        $candidatesAttr = Attribute::where('technical_name', '_import_fuzzy_candidates')->first();
        if ($candidatesAttr && !empty($candidates)) {
            CollectionAttributeValue::updateOrCreate(
                ['owner_type' => 'collection_item', 'owner_id' => $itemId, 'attribute_id' => $candidatesAttr->id, 'language' => null, 'multiplied_index' => 0],
                ['value_string' => json_encode($candidates)]
            );
        }
    }
}
