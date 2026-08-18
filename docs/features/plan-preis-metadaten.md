# Plan — Preis-Metadaten

> Frei definierbare Zusatzfelder an Preiszeilen, analog zu den
> **Attribut-Metadaten** (`docs/architecture/25-attribut-metadaten.md`).
> Auslöser: COVER liefert je Preiszeile Rabatt- und Steuerangaben, für die
> `product_prices` heute keine Spalten hat.

---

## 1. Problem

`product_prices` hat ein bewusst schlankes, festes Schema:

```
product_id, price_type_id, amount, currency, valid_from, valid_to,
country, price_region_id, scale_from, scale_to
```

`SF_PRODUCTPRICE` bringt darüber hinaus mit:

| COVER-Spalte | Art |
|---|---|
| `DISCOUNTPERCENT` | Zahl |
| `DISCOUNTGROUP` | Auswahl/Text |
| `BATCHQUANTITY` | Zahl |
| `TAXRATECODE1` / `TAXRATECODE2` | Auswahl |
| `TAXRATEPERCENT1` / `TAXRATEPERCENT2` | Zahl |
| `TAXABLEAMOUNT1` / `TAXABLEAMOUNT2` | Zahl |
| `TAXAMOUNT1` / `TAXAMOUNT2` | Zahl |

Für jede dieser Spalten eine eigene Kernspalte anzulegen, würde das Preisschema um
kundenspezifische Felder aufblähen — der nächste Kunde bringt andere. Umgekehrt sind
es keine Produktattribute, denn sie gelten **je Preiszeile**, nicht je Produkt.

---

## 2. Lösung

Dasselbe Muster wie bei den Attribut-Metadaten: eine Definitionstabelle für die
Felder, eine Wertetabelle für die Belegung.

### 2.1 `price_metadata_definitions`

| Spalte | Typ | Bedeutung |
|---|---|---|
| `id` | `char(36)` | UUID |
| `technical_name` | `varchar(100)` unique | Schlüssel der Metadaten-Map — nach Anlage unveränderlich |
| `name_de` / `name_en` | `varchar(255)` | Feldlabel im Preis-Panel |
| `description` | `text` | Hinweistext |
| `value_type` | `varchar(30)` | siehe unten |
| `options` | `json` | Auswahloptionen, Format `Label::Wert` |
| `price_type_id` | `char(36)` FK → `price_types`, nullable | Gültigkeitsbereich: `NULL` = für alle Preisarten |
| `is_required` | `boolean` | Pflichtfeld beim Speichern |
| `sort_order` | `integer` | Reihenfolge im Formular |

`value_type` bewusst **kein DB-`enum`** — einzige Wahrheitsquelle ist
`PriceMetadataDefinition::VALUE_TYPES`, identisch zu den Attribut-Metadaten:

```
text · textarea · number · date · boolean · select · multiselect · url · email
```

`price_type_id` ist die einzige Erweiterung gegenüber dem Attribut-Vorbild: Ein
Steuersatzfeld soll nicht an einer Frachtkostenzeile hängen.

### 2.2 `price_metadata_values`

| Spalte | Typ | Bedeutung |
|---|---|---|
| `id` | `char(36)` | UUID |
| `product_price_id` | `char(36)` FK → `product_prices`, `ON DELETE CASCADE` | |
| `definition_id` | `char(36)` FK → `price_metadata_definitions`, `ON DELETE CASCADE` | |
| `value` | `text` | skalare Werte |
| `value_json` | `json` | nur `multiselect` |

`unique(product_price_id, definition_id)`. Ein geleerter Wert löscht die Zeile —
keine Leerzeilen, wie bei den Attribut-Metadaten.

---

## 3. API

### Definitionen

```
GET    /api/v1/price-metadata-definitions
POST   /api/v1/price-metadata-definitions
GET    /api/v1/price-metadata-definitions/{id}
PUT    /api/v1/price-metadata-definitions/{id}
DELETE /api/v1/price-metadata-definitions/{id}[?force=true]
GET    /api/v1/price-metadata-definitions/{id}/dependencies
```

Löschen einer belegten Definition liefert **409** samt Anzahl betroffener
Preiszeilen; `?force=true` löscht Definition und Werte, die Preise bleiben.

### Werte an der Preiszeile

Wie bei Attributen reisen die Werte als flache Map `technical_name => Wert` im
**Preis-Payload** mit, kein eigener Endpoint:

```jsonc
// POST/PUT /api/v1/products/{product}/prices/{price}
{
  "amount": 49.00,
  "currency": "EUR",
  "valid_from": "2026-01-01",
  "metadata": {
    "discount_percent": 25.0,
    "discount_group": "A",
    "tax_rate_code_1": "V1",
    "tax_rate_percent_1": 7.0
  }
}
```

Begründung identisch zum Attribut-Panel: ein Speichern-Button, beim Anlegen
existiert noch keine Preis-ID, Validierungsfehler gehören in dasselbe 422.

---

## 4. COVER-Mapping

| COVER | Ziel |
|---|---|
| `PRICEAMOUNT` | `product_prices.amount` |
| `CURRENCYCODE` | `product_prices.currency` |
| `COUNTRYCODE` | `product_prices.country` / `price_region_id` |
| `PRICEEFFECTIVEFROM` / `-UNTIL` | `valid_from` / `valid_to` |
| `MINIMUMORDERQUANTITY` | `scale_from` |
| `PRICETYPECODE` + `PRICESTATUS` | `price_types.technical_name` — **nicht** Metadaten, siehe 4.1 |
| `DISCOUNTPERCENT` | Metadatum `discount_percent` (`number`) |
| `DISCOUNTGROUP` | Metadatum `discount_group` (`select`) |
| `BATCHQUANTITY` | Metadatum `batch_quantity` (`number`) |
| `TAXRATECODE1/2` | Metadaten `tax_rate_code_1/2` (`select`) |
| `TAXRATEPERCENT1/2` | Metadaten `tax_rate_percent_1/2` (`number`) |
| `TAXABLEAMOUNT1/2` | Metadaten `tax_taxable_amount_1/2` (`number`) |
| `TAXAMOUNT1/2` | Metadaten `tax_amount_1/2` (`number`) |

### 4.1 Warum `PRICESTATUS` und Land nicht in die Metadaten gehören

`product_prices` trägt seit `2026_03_13_000002` den Unique-Index

```
(product_id, price_type_id, currency, valid_from, scale_from)
```

**Weder `country`/`price_region_id` noch ein Status sind darin enthalten.** Der
COVER-Schlüssel ist dagegen
`PRICETYPECODE + PRICESTATUS + CURRENCYCODE + PRICEEFFECTIVEFROM + MINIMUMORDERQUANTITY`.

Daraus folgt zwingend: Land und Status müssen in den `price_type` einfließen, sonst
kollidieren z. B. `LP-Deutschland` und `LP-Österreich` (beide EUR, gleiches
Gültigkeitsdatum) im Unique-Index. Die Preisarten werden deshalb 1:1 aus COVER
übernommen — `lp_de_02`, `lp_at_02`, `lp_ch_02`, `ca_de_01` usw.

Alternative, falls die Preisartenliste dadurch zu lang wird: Unique-Index um
`price_region_id` und eine neue Statusspalte erweitern. Das ist die sauberere, aber
teurere Variante und berührt bestehende Kunden — deshalb nicht Teil dieses Plans.

### 4.2 Abgrenzung: beschreibend, nicht preisbildend

Preis-Metadaten werden **nicht** in Preisberechnungen ausgewertet. Soll
`DISCOUNTPERCENT` künftig einen Nettopreis errechnen, gehört es als Kernspalte in
`product_prices` — nicht als Metadatum. Für die COVER-Migration ist die
Durchreiche-Semantik ausreichend: die Werte werden geführt, angezeigt und
exportiert.

---

## 5. Umsetzungsschritte

| # | Schritt |
|---|---|
| 1 | Migrationen `price_metadata_definitions`, `price_metadata_values` |
| 2 | Modelle `PriceMetadataDefinition` (inkl. `VALUE_TYPES`), `PriceMetadataValue`; Relation `metadataValues()` an `ProductPrice` |
| 3 | Controller + Requests + Resource für die Definitionen, inkl. `dependencies` und `force`-Löschung |
| 4 | `metadata`-Map in Preis-Store/Update-Request validieren und schreiben, in `ProductPriceResource` ausgeben |
| 5 | Frontend: Metadatenblock im Preis-Panel, gerendert über die vorhandenen `PimAttributeInput`-Typen |
| 6 | Berücksichtigung im Export (`MappingResolver`: Quell-Namespace `price_meta:`) |
| 7 | Tests: CRUD, Unique-Constraint, Leerwert löscht Zeile, `price_type_id`-Gültigkeitsbereich, 409 bei belegter Definition |

Schritt 6 ist nötig, damit die COVER-Werte auch wieder aus dem System herauskommen —
`MappingResolver` kennt heute `prices:` als Quell-Namespace, aber keine Preis-Metadaten.
