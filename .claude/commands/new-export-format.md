# Neues Export-Format anlegen

Erstelle ein neues Export-Format für anyPIM. Folge exakt dem 5-Dateien-Pattern.

## Ablauf

1. Frage den Nutzer nach dem **Format-Namen** (z.B. "Gaeb", "Onyx", "Etim", "Fabdis")
2. Frage nach dem **Ausgabeformat** (XML, JSON, CSV)
3. Frage nach den **Zielfeldern** (TARGET_FIELDS) — welche Felder soll das Format unterstützen?

## Dateien erstellen

Erstelle genau diese 5 Dateien (ersetze `{Format}` und `{format}`):

1. **`app/Services/Export/{Format}FormatExporter.php`** — Hauptlogik
   - `use ExportProductHelpers;` Trait einbinden
   - `MappingResolver` per Constructor Injection
   - 4 Filter-Properties: hierarchyId, productTypeIds, attributeIds, priceTypeIds
   - `export()` Methode mit `buildFilteredProductQuery()` + `chunk(500, ...)`

2. **`app/Services/Export/{Format}ElementMap.php`** — Zielschema
   - `TARGET_FIELDS` Konstante mit den Zielfeldern
   - `defaultMappingRules()` mit sinnvollen Standard-Mappings
   - `fieldDefaults()` mit Fallback-Werten

3. **`app/Http/Controllers/Api/V1/{Format}ExportController.php`** — API-Endpoint
   - Request-Validation für mapping_id, hierarchy_id, product_type_ids, attribute_ids, price_type_ids
   - `StreamedResponse` für Download

4. **`app/Console/Commands/{Format}Export.php`** — CLI
   - Signature: `pim:{format}-export` mit allen Options
   - Output an Datei oder stdout

5. **Route in `routes/api.php`** — nach dem BMEcat-Block
   - `Route::middleware('module:{format}')->group(...)` 
   - `Route::post('{format}-export', [{Format}ExportController::class, 'export'])`

## Checkliste nach Erstellung

- [ ] `use ExportProductHelpers;` Trait verwendet (nicht kopiert)
- [ ] `MappingResolver` für Feld-Mapping genutzt
- [ ] `StreamedResponse` für Downloads
- [ ] XMLWriter für XML-Formate (memory-effizient)
- [ ] Filtering: hierarchy, product_types, attributes, price_types
- [ ] Chunking: `$query->chunk(500, fn ...)` 
- [ ] i18n: Languages-Parameter berücksichtigt
- [ ] `declare(strict_types=1)` in allen Dateien
- [ ] Keine gemeinsamen Dateien geändert (außer routes/api.php)

## Referenz

Siehe GAEB-Export als Vorlage (`claude/gaeb-xml-export-XSnPV`).
