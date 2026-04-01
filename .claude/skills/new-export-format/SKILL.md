---
name: new-export-format
description: Neues Export-Format anlegen (5-Dateien-Pattern mit MappingResolver)
disable-model-invocation: true
---

# Neues Export-Format anlegen

Erstelle ein neues Export-Format für anyPIM. Ersetze `{Format}` (PascalCase) und `{format}` (kebab-case) in allen Dateien.

## Schritt 1: Anforderungen klären

Frage den Nutzer:
1. **Format-Name** (z.B. "Gaeb", "Onyx", "Etim", "Fabdis")
2. **Ausgabeformat** (XML → XMLWriter, JSON → json_encode, CSV → CsvWriter)
3. **Zielfelder** (TARGET_FIELDS) — welche Felder soll das Format unterstützen?
4. **Gibt es eine Spezifikation?** (URL oder Datei zum Format-Standard)

## Schritt 2: 5 Dateien erstellen

### Datei 1: `app/Services/Export/{Format}FormatExporter.php`

Pflicht-Elemente:
- `use ExportProductHelpers;` (Trait, NICHT kopieren)
- `MappingResolver` per Constructor Injection
- 4 Filter-Properties: `hierarchyId`, `productTypeIds`, `attributeIds`, `priceTypeIds`
- Identische Setter wie in der Referenz (setHierarchyId, setProductTypeIds, etc.)
- `setMappingId()` lädt PublixxExportMapping
- `export()` mit `buildFilteredProductQuery()` + `chunk(500, ...)`
- MappingResolver::resolve() für jedes Produkt aufrufen
- Ergebnis serialisieren (XMLWriter/json_encode/etc.)

### Datei 2: `app/Services/Export/{Format}ElementMap.php`

- `TARGET_FIELDS` Konstante mit allen Zielfeldern
- `defaultMappingRules()` mit sinnvollen Quell→Ziel Mappings
- `fieldDefaults()` mit Fallback-Werten

### Datei 3: `app/Http/Controllers/Api/V1/{Format}ExportController.php`

- Request-Validation: mapping_id, hierarchy_id, product_type_ids, attribute_ids, price_type_ids
- `StreamedResponse` für Download
- Content-Type + Content-Disposition Header

### Datei 4: `app/Console/Commands/{Format}Export.php`

- Signature: `pim:{format}-export`
- Options: --mapping, --hierarchy, --product-types, --attributes, --price-types, -o|output
- Output an Datei oder stdout

### Datei 5: Route in `routes/api.php`

Nach dem BMEcat-Block einfügen:
```php
Route::middleware('module:{format}')->group(function () {
    Route::post('{format}-export', [{Format}ExportController::class, 'export']);
});
```

## Schritt 3: Checkliste

Prüfe jede Datei gegen diese Regeln:

- [ ] `declare(strict_types=1)` in allen PHP-Dateien
- [ ] `use ExportProductHelpers;` Trait (nicht kopiert)
- [ ] `MappingResolver` für Feld-Mapping
- [ ] `StreamedResponse` für Downloads
- [ ] XMLWriter für XML-Formate (memory-effizient)
- [ ] Chunking: `$query->chunk(500, fn ...)`
- [ ] i18n: `$this->languages` Parameter berücksichtigt
- [ ] Keine gemeinsamen Dateien geändert (außer routes/api.php)
- [ ] Filter: hierarchy, product_types, attributes, price_types alle unterstützt

## Referenz

Lies `.claude/rules/export-format-conventions.md` für die vollständigen Code-Templates.
GAEB-Export dient als Vorlage (Branch `claude/gaeb-xml-export-XSnPV`).
