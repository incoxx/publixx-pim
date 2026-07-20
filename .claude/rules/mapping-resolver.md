---
globs:
  - "app/Services/Export/MappingResolver.php"
  - "app/Services/Export/*ElementMap.php"
---

# MappingResolver — Zentrale Mapping-Engine

**Datei:** `app/Services/Export/MappingResolver.php` (456 Zeilen)

## Aufruf im FormatExporter

```php
// Im FormatExporter::export():
$query = $this->buildFilteredProductQuery([
    'productTypeIds' => $this->productTypeIds,
    'attributeIds'   => $this->attributeIds,
    'priceTypeIds'   => $this->priceTypeIds,
]);

$query->chunk(500, function ($products) {
    foreach ($products as $product) {
        // MappingResolver liefert flat key→value Map
        $mapped = $this->mappingResolver->resolve(
            $this->mappingRules,    // aus PublixxExportMapping oder ElementMap::defaultMappingRules()
            $product,
            $this->languages
        );
        // $mapped = ['outline_text' => 'Produktname', 'unit_price' => 189.99, ...]

        // Dann nur noch serialisieren (XMLWriter, json_encode, etc.)
        $this->writeProduct($mapped);
    }
});
```

## Verfügbare Quellfelder (Source-Namespaces)

| Prefix | Beispiel | Beschreibung |
|--------|----------|-------------|
| `attribute:` | `attribute:product-name-dict` | Attributwert nach technical_name |
| `prices:` | `prices:list_price` | Preis nach PriceType technical_name |
| `media:` | `media:teaser` | Medien-URL nach UsageType technical_name |
| `relations:` | `relations:accessories` | Produktrelationen nach RelationType technical_name |
| `collection:` | `collection:technische-daten` | Attribut-Gruppe nach Collection-Name |

## Verfügbare Mapping-Typen

| Type | Source → Output | Beispiel |
|------|----------------|---------|
| `text` | Attribut → String | `"Akkubohrer Professional"` |
| `unit_value` | Attribut → `{value, unit}` | `{"value": 1.8, "unit": "kg"}` |
| `composite` | Composite-Attribut → Objekt | `{"width": 10, "height": 20, "_formatted": "10 x 20"}` |
| `media_url` | Medien → einzelne URL | `"/storage/media/teaser.jpg"` |
| `media_array` | Medien → URL-Array | `["/img1.jpg", "/img2.jpg"]` |
| `price` | Preis → Dezimalwert | `189.99` |
| `variant_array` | Varianten → Array | `[{"sku": "V1", "name": "Rot"}]` |
| `relation_array` | Relationen → Array | `[{"sku": "Z1", "name": "Zubehör"}]` |
| `group` | Collection → Objekt | `{"gewicht": {"value": 1.8, "unit": "kg"}}` |

## Mapping-Regel-Struktur (JSON)

```json
{
  "rules": [
    {"source": "attribute:product-name-dict", "target": "productName", "type": "text"},
    {"source": "attribute:description-long",  "target": "description", "type": "text"},
    {"source": "prices:list_price",           "target": "unitPrice",   "type": "price"},
    {"source": "media:teaser",                "target": "imageUrl",    "type": "media_url"},
    {"source": "attribute:weight",            "target": "weight",      "type": "unit_value"}
  ]
}
```

Die `target`-Felder kommen aus der jeweiligen `{Format}ElementMap::TARGET_FIELDS`.

## ElementMap-Konvention für Zielschema

Jede `{Format}ElementMap` definiert das **Zielschema** des Formats. Die `TARGET_FIELDS` Konstante
listet alle gültigen Zielfelder, die in Mapping-Regeln als `target` verwendet werden können.

```php
// Beispiel: OnyxElementMap
public const TARGET_FIELDS = [
    'outline_text',   // Kurztext (Angebotsposition)
    'detail_text',    // Langtext (Leistungsbeschreibung)
    'quantity',       // Menge
    'quantity_unit',  // Mengeneinheit
    'unit_price',     // Einzelpreis
    'item_number',    // Ordnungszahl
    'item_type',      // Positionsart
];
```

Die `defaultMappingRules()` liefern eine **Standardvorlage**, die der Nutzer in der GUI als
Ausgangspunkt laden kann. Die `fieldDefaults()` definieren Fallback-Werte für ungemappte Felder.
