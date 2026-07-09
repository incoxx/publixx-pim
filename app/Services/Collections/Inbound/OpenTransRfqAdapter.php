<?php

declare(strict_types=1);

namespace App\Services\Collections\Inbound;

use App\Services\Collections\DTO\OfferContext;

/**
 * openTRANS RFQ (Request for Quotation) -> OfferContext.
 *
 * ANNAHME (nicht verifiziert -- kein openTRANS-Code existiert sonst in diesem Repo,
 * siehe Plan §10 "Assumptions/risks"): Struktur folgt openTRANS 2.1, dem in
 * DACH-B2B am weitesten verbreiteten Stand. Parser ist bewusst defensiv (fehlende
 * Knoten -> null statt Exception), damit kleinere Abweichungen im tatsaechlichen
 * Handelspartner-Dialekt nicht sofort brechen. Vor Produktivbetrieb gegen echte
 * RFQ-Beispieldateien verifizieren.
 *
 * Erwartete Grobstruktur:
 * <RFQ>
 *   <RFQ_HEADER>
 *     <PARTIES><PARTY><PARTY_ROLE>buyer</PARTY_ROLE><PARTY_ID>...</PARTY_ID>
 *       <ADDRESS><NAME>...</NAME><COUNTRY>...</COUNTRY></ADDRESS></PARTY></PARTIES>
 *     <CONTROL_INFO><GENERATOR_INFO>...</GENERATOR_INFO></CONTROL_INFO>
 *   </RFQ_HEADER>
 *   <RFQ_ITEM_LIST>
 *     <RFQ_ITEM><LINE_ITEM_ID>1</LINE_ITEM_ID>
 *       <PRODUCT_ID><SUPPLIER_PID>...</SUPPLIER_PID><DESCRIPTION_SHORT>...</DESCRIPTION_SHORT></PRODUCT_ID>
 *       <QUANTITY>10</QUANTITY><ORDER_UNIT>C62</ORDER_UNIT>
 *       <REMARKS>...</REMARKS>
 *     </RFQ_ITEM>
 *   </RFQ_ITEM_LIST>
 * </RFQ>
 */
class OpenTransRfqAdapter implements InboundAdapterInterface
{
    public function parse(string $raw, array $options = []): OfferContext
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);

        if ($xml === false) {
            $errors = array_map(fn ($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            throw new \InvalidArgumentException('Ungueltiges openTRANS-XML: ' . implode('; ', $errors));
        }

        $buyerParty = $this->findParty($xml, 'buyer');

        $items = [];
        foreach ($xml->xpath('.//RFQ_ITEM') ?: [] as $rfqItem) {
            $productId = $rfqItem->PRODUCT_ID ?? null;
            $supplierPid = $this->text($productId?->SUPPLIER_PID ?? null);
            $description = $this->text($productId?->DESCRIPTION_SHORT ?? null);

            // ?? statt ?: -- text() liefert bei fehlenden/leeren Knoten bereits null (nie ''),
            // ein legitimer Wert "0" (Menge 0, SKU "0") darf daher nicht als "leer" gelten.
            $items[] = [
                'external_product_id' => $supplierPid,
                'sku_candidate' => $supplierPid ?? $description,
                'quantity' => (float) ($this->text($rfqItem->QUANTITY ?? null) ?? 1),
                'unit' => $this->text($rfqItem->ORDER_UNIT ?? null),
                'note' => $this->text($rfqItem->REMARKS ?? null) ?? $description,
            ];
        }

        return new OfferContext(
            organization: $buyerParty === null ? null : [
                'external_ref' => $this->text($buyerParty->PARTY_ID ?? null),
                'name' => $this->text($buyerParty->ADDRESS?->NAME ?? null),
                'language' => $this->text($buyerParty->ADDRESS?->COUNTRY ?? null) ? 'de' : null,
            ],
            currency: $this->text(($xml->xpath('.//CURRENCY') ?: [])[0] ?? null),
            items: $items,
            reference: $this->text(($xml->xpath('.//RFQ_HEADER/CONTROL_INFO/GENERATOR_INFO') ?: [])[0] ?? null),
        );
    }

    private function findParty(\SimpleXMLElement $xml, string $role): ?\SimpleXMLElement
    {
        foreach ($xml->xpath('.//PARTY') ?: [] as $party) {
            if (strtolower($this->text($party->PARTY_ROLE ?? null) ?? '') === $role) {
                return $party;
            }
        }

        return null;
    }

    private function text(\SimpleXMLElement|string|null $node): ?string
    {
        if ($node === null) {
            return null;
        }
        $value = trim((string) $node);

        return $value === '' ? null : $value;
    }
}
