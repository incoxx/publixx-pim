# Analyse: 4 neue Attributtypen (Referenzen &amp; freie Selects)

## Auftrag

Vier neue `data_type`-Werte für Attribute:

| # | Typ (Vorschlag) | Beschreibung |
|---|---|---|
| 1 | `HierarchyNodeReference` | Querverweis auf einen Hierarchie-Knoten (Single), mit Hierarchie/Knoten-Picker |
| 2 | `ProductReference` | Querverweis auf ein anderes Produkt (Single), wie Produktbeziehungen — explizit relevant für Attribute im **Asset Management** (Medien-Attributwerte) |
| 3 | `SimpleSelect` | Einfache Selectbox, **ohne** vordefinierte Werteliste — Optionen werden direkt am Attribut gepflegt (z. B. `1`, `2`, `3`) |
| 4 | `SimpleMultiSelect` | Wie 3, Mehrfachauswahl |

Einbau in: Konfiguration › Attribute › Attribute, Produkteditor (inkl. Picker/Selectbox), `/preview`, Exporte.

Dies ist eine **Analyse mit Abhängigkeiten**, keine Implementierung. Grundlage ist eine Recherche des Ist-Zustands (drei parallele Codebase-Scans, Stand 2026-07-10) plus die bereits umgesetzte Vorlage für die 4 Link-Datentypen (`docs/features/plan-hyperlink-datatypes.md`, inzwischen im Code vorhanden).

---

## 1. Bestehende Architektur — Kernbefunde

### 1.1 Kein zentrales Enum, sondern EAV-Value-Spalten

`product_attribute_values` (und die Analog-Tabellen `hierarchy_node_attribute_values`, `media_attribute_values`, `product_relation_attribute_values`, `collection_attribute_values`, `output_hierarchy_product_attribute_values`) haben **feste Spalten**, keine typisierte JSON-Spalte:

```
value_string (TEXT), value_number (DECIMAL), value_date (DATE), value_flag (BOOLEAN),
value_selection_id (FK → value_list_entries)
```

`MultiSelection` behilft sich bereits, indem es ein JSON-Array **als String** in `value_string` ablegt (kein echtes JSON-Feld, kein FK-Array). Das ist das etablierte Muster, dem auch die 4 neuen Typen folgen sollten — **keine neue Spalte und keine Migration der Value-Tabellen nötig**.

> **Optimierungs-Nachtrag (Abschnitt 7):** Die „~25 Switch-Stellen" sind kein Block von 25 Änderungen. Sie zerfallen in drei Funktionsklassen, von denen nur eine echten Neucode braucht. Details und die 3 minimalinvasiven Hebel stehen in **Abschnitt 7**.

### 1.2 `data_type` ist an ≥4 Stellen als Enum dupliziert, an ≥25 Stellen als Switch/Match

- ENUM-Liste: `attributes`-Migration + 7 additive Migrationen (`ALTER TABLE ... MODIFY COLUMN`, nur MySQL-wirksam; SQLite hat einen eigenen CHECK-Constraint-Sync in `2026_06_12_000001_sync_data_type_enum_for_sqlite.php`)
- Dieselbe Werteliste außerdem in `SheetValidator::VALID_DATA_TYPES`, `StoreAttributeRequest`/`UpdateAttributeRequest` (`in:...`), `TemplateGenerator::ENUM_HINTS` (bereits jetzt veraltet/unvollständig — fehlt z. B. `Textarea`)
- **Es gibt keine `Attribute::DATA_TYPES`-Konstante im Model.** Das ist eine bestehende Wartungslücke, keine Neuerung durch diese Aufgabe — aber jede neue Typ-Einführung vergrößert das Risiko weiterer Inkonsistenzen.
- „Wert lesen/schreiben je `data_type`"-Switches sind **~25 Mal** im Backend dupliziert (Controller, Services, Connectors, Preview, Export) — siehe Tabelle in Abschnitt 3.

### 1.3 Zentrale, wiederverwendbare Bausteine, die bereits existieren

| Baustein | Datei | Nutzen für die 4 neuen Typen |
|---|---|---|
| Hierarchie/Knoten-Picker | `pim-frontend/src/components/products/MasterHierarchyNodePickerDialog.vue` | Direkte Vorlage für `HierarchyNodeReference`. Aktuell fest an `product.master_hierarchy_node_id` gebunden, Props/Emits aber generisch genug — muss nur entkoppelt/umbenannt werden. |
| Generisches Auswahl-Modal | `pim-frontend/src/components/shared/EntityPickerDialog.vue` | Nimmt `fetcher(query, page)`, `labelFn`, `multiple` — geeignete Basis für `ProductReference` (Produkt-Fetcher übergeben), bereits in 4 Views im Einsatz. |
| Debounced Produktsuche | `ProductDetailView.vue:2301-2319` (`searchProducts`/`selectTargetProduct`, Relations-Tab) | Alternative/Vorbild für Inline-Picker statt Modal. |
| Zentrale Mapping-Engine | `app/Services/Export/MappingResolver.php` (611 Zeilen) | Für Export: neuer `type`-Fall analog zu `relation_array` (Zeile 254-290, liest Relationen → `{sku, name, image?}`). |
| Link-Typ-Pattern (`Hyperlink`/`ImageLink`/`PdfLink`/`VideoLink`) | `PimAttributeInput.vue:381-420`, `ProductPreviewService.php` (`link_data`), `JsonFormatExporter.php:1168-1200`, `CatalogProductDetailResource.php` | Exaktes Vorbild für „JSON-artiger Typ, strukturiert für Preview/Export decodiert" — dasselbe Muster passt für Referenz-Typen (`reference_data` statt `link_data`). |
| `select`/`multiselect`-Branches | `PimAttributeInput.vue:207-247` | Können für `SimpleSelect`/`SimpleMultiSelect` fast unverändert wiederverwendet werden — nur die `options`-Quelle ändert sich (vom Attribut selbst statt `ValueList`). |

### 1.4 Es gibt **keine** generische Attributwert-Darstellungskomponente

Preview (`CatalogProductView.vue`, `CatalogProductDetailResource.php`), Admin-Preview (`ProductPreviewService.php`), Bulk-Editor (`BulkEditorView.vue`) und alle Export-Resolver haben **je eigene, duplizierte** `data_type`-Switches. Es gibt kein zentrales `ExportProductHelpers`-Trait (trotz anderslautender Doku in `.claude/rules/export-format-conventions.md` — das Trait existiert nicht, stattdessen 4 unabhängige `resolveAttributeValue()`-Implementierungen in `BmecatFormatExporter`, `ImportFormatExporter`, `JsonFormatExporter`, `OfflineCatalogExportService`). Konsequenz: **jede neue Darstellungsart muss an mehreren Stellen einzeln nachgezogen werden** — das ist der Hauptkostentreiber dieser Aufgabe, nicht die Typ-Logik selbst.

---

## 2. Design-Entscheidung (Vorschlag)

### 2.1 Speicherung

| Typ | Spalte | Format |
|---|---|---|
| `HierarchyNodeReference` | `value_string` | UUID des `HierarchyNode` (kein FK-Constraint, da die Spalte typübergreifend genutzt wird — analog zu `value_selection_id`, aber ohne DB-FK, weil das Ziel je nach `data_type` Produkt oder Hierarchie-Knoten sein kann) |
| `ProductReference` | `value_string` | UUID des `Product` |
| `SimpleSelect` | `value_string` | Roher Text/Zahl wie eingegeben (z. B. `"2"`) |
| `SimpleMultiSelect` | `value_string` | JSON-Array als String, z. B. `["1","3"]` — identisches Muster wie `MultiSelection` |

**Keine Migration der Value-Tabellen nötig.** Referenzielle Integrität (gelöschtes Zielprodukt/Zielknoten) muss applikationsseitig behandelt werden (z. B. Anzeige „Referenz nicht gefunden", ähnlich wie fehlende `ValueListEntry` bei `Selection` heute schon gehandhabt wird) — es gibt keinen `ON DELETE`-Mechanismus wie bei `value_selection_id`.

### 2.2 Neue Konfigurationsspalte am Attribut

Nur **eine** neue Spalte auf `attributes` nötig, analog zu `delimiter`/`textarea_rows`:

```php
$table->json('simple_options')->nullable(); // ["1", "2", "3"] — für SimpleSelect/SimpleMultiSelect
```

`HierarchyNodeReference`/`ProductReference` brauchen **keine** Pflicht-Konfigurationsspalte für den MVP (freie Auswahl über alle Hierarchien/Produkte, analog zu Produktbeziehungen, die auch nicht auf einen Produkttyp beschränkt sind). Optional/später: `target_hierarchy_id` (FK, nullable) am Attribut, um `HierarchyNodeReference` auf eine bestimmte Hierarchie einzuschränken — offene Frage, siehe Abschnitt 5.

### 2.3 Konfigurations-UI (`AttributeFormPanel.vue`)

- 4 neue Optionen im `data_type`-Dropdown (Zeile 146-167)
- Neue `dataTypeGroups`-Einträge (Zeile 41-48): `reference: ['HierarchyNodeReference', 'ProductReference']`, ggf. `simpleSelection: ['SimpleSelect', 'SimpleMultiSelect']` — für verlustfreien Typwechsel
- Neues konditionales Feld für `SimpleSelect`/`SimpleMultiSelect`: Options-Editor (Tag-Liste, ein Wert pro Zeile oder Komma-getrennt) für `simple_options` — es existiert noch keine passende Komponente dafür, müsste neu gebaut werden (kleiner Umfang, ähnlich einem einfachen Tag-Input)
- `HierarchyNodeReference`/`ProductReference` brauchen im MVP keine Zusatzfelder im Formular

### 2.4 Produkteditor

- `mapDataTypeToInput()` (`ProductDetailView.vue:556-565`) um 4 Einträge erweitern
- `PimAttributeInput.vue`: 2 neue `v-if`-Zweige für die Referenz-Typen (neue Picker-Einbindung wie bei `richtext` per `defineAsyncComponent`), `select`/`multiselect`-Zweige für `SimpleSelect`/`SimpleMultiSelect` wiederverwenden (Options aus `attr.simple_options` statt `attr.value_list.entries`)
- `MasterHierarchyNodePickerDialog.vue` generalisieren (umbenennen/parametrisieren, damit sie nicht produktspezifisch heißt) und für `HierarchyNodeReference`-Attributwerte wiederverwenden
- Für `ProductReference`: entweder `EntityPickerDialog.vue` mit Produkt-Fetcher (Modal, empfohlen — konsistent mit anderen Referenz-Pickern im System) oder die Inline-Suche aus dem Relations-Tab kopieren

### 2.5 Export (`MappingResolver.php`)

Neuer `type`-Fall, z. B. `reference` (single) analog zu `resolveRelationArray()` (Zeile 254-290):
1. `findAttributeValue()` holt den `ProductAttributeValue`
2. `value_string` (UUID) auflösen: `Product::find()` bzw. `HierarchyNode::find()` je nach `attribute->data_type`
3. Rückgabe strukturiert: `{id, sku/technical_name, name, path?}` für JSON-Formate; für flache Formate (CSV/XML) reicht ein Skalar (z. B. SKU oder Knotenpfad) — ggf. zwei Typen `reference` (skalar) und `reference_object` (strukturiert), oder ein `options`-Flag wie bei bestehenden Typen

Zusätzlich müssen die **4 duplizierten** `resolveAttributeValue()`-Methoden (`BmecatFormatExporter.php:706-727`, `ImportFormatExporter.php:874-903`, `JsonFormatExporter.php:1168-1200`, `OfflineCatalogExportService.php:1586`) je einen neuen `if`-Zweig bekommen — exakt nach dem Muster, das dort schon für die Link-Typen existiert (JSON-Decodierung bzw. Auflösung des Referenzziels).

### 2.6 Preview

- `ProductPreviewService.php` (`resolveDisplayValue()`, Zeile 810-835) — neuer Match-Zweig, liefert zusätzlich `reference_data` (analog zu `link_data`, Zeile 190-201/219-221)
- `CatalogProductDetailResource.php` (`resolveAttributeDisplayValue()`, Zeile 414-426) — gleiches Muster für die öffentliche `/preview`-Ansicht
- `CatalogProductView.vue` (Frontend) — neuer `v-else-if`-Zweig fürs Rendering, analog zum bestehenden Link-Rendering-Block (Zeile 355-390) bzw. dem Relations-Rendering (`goToProduct()`-Muster, Zeile 291-296/411-416/545)

---

## 3. Abhängigkeits-Matrix

### 3.1 Pflicht für den beauftragten Umfang (Konfiguration, Produkteditor, Preview, Export, Asset Management)

| Bereich | Datei | Änderung |
|---|---|---|
| DB | neue Migration | `data_type`-ENUM um 4 Typen erweitern (analog zu den 7 bestehenden additiven Migrationen); `simple_options` JSON-Spalte auf `attributes` |
| Backend Validierung | `SheetValidator.php:18-21` | `VALID_DATA_TYPES` erweitern |
| Backend Validierung | `StoreAttributeRequest.php:26`, `UpdateAttributeRequest.php:30` | `in:...`-Liste erweitern |
| Backend Validierung | `TemplateGenerator.php:164-168` | `ENUM_HINTS` aktualisieren (ohnehin bereits veraltet — bei dieser Gelegenheit mitkorrigieren) |
| Attribut-Model | `app/Models/Attribute.php` | `simple_options` in `$fillable`/Casts; **Empfehlung**: jetzt endlich eine zentrale `DATA_TYPES`-Konstante einführen, um weitere Drift zu vermeiden |
| Wert-Schreiben (Produkt) | `ProductAttributeValueController.php:834-863` (`resolveValueColumns`) | neue `match`-Fälle |
| Wert-Schreiben (generisch) | `ProductAttributeWriter.php:177-200` | neue `match`-Fälle (wird von mehreren Kontexten genutzt) |
| Wert-Schreiben (Bulk) | `BulkEditorController.php:228`, `BulkUpdateController.php:935` | neue Fälle |
| Wert-Schreiben (Asset/Medien) | `MediaAttributeValueController.php:95` | **Pflicht laut Auftrag** — `ProductReference` muss in Medien-Attributwerten funktionieren |
| Wert-Schreiben (Varianten) | `ProductVariantController.php:256` | neue Fälle (falls Varianten diese Typen nutzen sollen — zu klären) |
| Export-Engine | `MappingResolver.php` | neuer `type`-Fall (Abschnitt 2.5) |
| Export-Rohwert | `BmecatFormatExporter.php:706-727`, `ImportFormatExporter.php:874-903`, `JsonFormatExporter.php:1168-1200`, `OfflineCatalogExportService.php:1586` | je ein neuer `if`-Zweig |
| Preview (Admin) | `ProductPreviewService.php:810-835` + Umgebung (`link_data`-Pattern) | neuer Match-Zweig + `reference_data` |
| Preview (öffentlich) | `CatalogProductDetailResource.php:414-426` | neuer Match-Zweig |
| Preview Frontend | `CatalogProductView.vue` (Zeile ~228-390) | neuer Rendering-Zweig |
| Konfiguration UI | `AttributeFormPanel.vue:41-48, 146-167, 176-282` | Dropdown-Optionen, `dataTypeGroups`, neuer Options-Editor für `simple_options` |
| Produkteditor UI | `PimAttributeInput.vue` | 2 neue Picker-Zweige, `select`/`multiselect` wiederverwenden |
| Produkteditor UI | `ProductDetailView.vue:556-565` (`mapDataTypeToInput`) | Mapping-Einträge |
| Picker-Komponente | `MasterHierarchyNodePickerDialog.vue` | generalisieren (nicht mehr fest an `master_hierarchy_node_id`) |
| Picker-Komponente | `EntityPickerDialog.vue` | Produkt-Fetcher konfigurieren (kein Umbau nötig, nur Verwendung) |

### 3.2 Optional / bewusst nicht im MVP (Empfehlung: zurückstellen, explizit mit Nutzer klären)

| Bereich | Datei | Warum zurückstellen |
|---|---|---|
| Bulk-Editor Zellrendering | `BulkEditorView.vue:396-465` | Eigene, unabhängig duplizierte `data_type`-Kaskade; deckt heute schon nicht alle Typen ab (`MultiSelection`, `Composite`, `RichText` fehlen). Nachrüsten ist sinnvoll, aber kein Blocker für „Konfiguration + Produkteditor + Preview + Export". |
| Import (Excel/CSV) | `ImportExecutor.php`, `FlatImportExecutor.php` (`mapValueToColumns`) | Bräuchte Referenz-Auflösung (Produkt per SKU, Hierarchie-Knoten per Pfad) beim Import — nicht Teil des Auftrags, aber am selben Muster wie `Selection`-Namensauflösung machbar. |
| PQL (Produktsuche/Filter) | `PqlSqlGenerator.php:564-570`, `PqlValidator.php:132-141` | `SimpleSelect`/`SimpleMultiSelect` fallen automatisch in den `default`-Zweig (`value_string`, Text-Vergleich funktioniert bereits ohne Änderung). `HierarchyNodeReference`/`ProductReference` filterbar zu machen (z. B. „alle Produkte mit Referenz auf X") ist ein Zusatzfeature, kein Rendering-Thema. |
| Meilisearch-Facetten | `SearchSchemaService.php:226-257`, `MeilisearchDocumentBuilder.php:209-235` | Nur relevant, falls die neuen Typen als Facette/Filter in der Volltextsuche erscheinen sollen. |
| Connector-Exporte | `ShopwareProductService.php:525`, `ShopifyProductService.php:465`, `ShopifyMetafieldService.php:183,228` | Nur falls die neuen Typen auch über die Shop-Konnektoren exportiert werden müssen. |
| Weitere `*_attribute_values`-Kontexte | `HierarchyNodeAttributeValueController.php`, `CollectionAttributeValueController.php`, `OutputHierarchyProductAssignmentController.php` | Nur relevant, falls Attribute dieser neuen Typen auch an Hierarchie-Knoten, Collections oder Output-Hierarchie-Zuordnungen (statt nur Produkten/Medien) zuweisbar sein sollen. Zu klären, ob das gewünscht ist. |
| PDF-Export der Preview | `PdfTemplateRenderer.php:340` | Falls PDF-Kataloge ebenfalls Referenzen darstellen sollen. |

---

## 4. Offene Fragen (vor Implementierungsstart zu klären)

1. **Selbstreferenz/Zyklen bei `ProductReference`:** Darf ein Produkt sich selbst referenzieren? Sollen Zyklen (A→B→A) verhindert werden, oder ist das wie bei Produktbeziehungen unkritisch (nur Anzeige, keine Business-Logik-Abhängigkeit)?
2. **Scoping von `HierarchyNodeReference`:** Soll die Auswahl auf eine bestimmte Hierarchie einschränkbar sein (analog `value_list_id` bei Selection), oder bleibt sie frei wie bei `master_hierarchy_node_id`? Beeinflusst, ob `target_hierarchy_id`-Spalte am Attribut nötig ist.
3. **Verhalten bei gelöschtem Ziel:** Da keine FK/`ON DELETE` existiert (bewusste Entscheidung, s. 2.1) — wie soll die Preview/der Export reagieren, wenn das referenzierte Produkt/der Knoten gelöscht wurde? (Vorschlag: Anzeige „Referenz ungültig", Wert bleibt in DB stehen bis manuell bereinigt — analog zum heutigen Verhalten bei verwaisten `value_selection_id`.)
4. **`SimpleSelect`-Optionen mehrsprachig?** Der Auftrag nennt nur Zahlen (`1, 2, 3`) als Beispiel — sind auch Textwerte vorgesehen, und müssen diese wie bei `Selection` pro Sprache unterschiedlich sein, oder reicht ein einzelner, sprachunabhängiger Wertesatz? (Vorschlag: sprachunabhängig, da explizit „keine vordefinierten Wertelisten" gewünscht ist — das würde den mehrsprachigen `ValueList`-Mechanismus unnötig duplizieren.)
5. **Umfang „Asset Management":** Reicht `MediaAttributeValueController.php` (Medien-Attributwerte), oder ist auch `CollectionAttributeValueController.php` (Collections/Sammlungen) gemeint?
6. **Bulk-Editor/Import/Suche:** Bestätigen, dass diese in Abschnitt 3.2 zunächst zurückgestellt werden können.

---

## 5. Aufwandseinschätzung &amp; Risiko

- **Kernumfang (Abschnitt 3.1):** vergleichbar mit der bereits umgesetzten Hyperlink/ImageLink/PdfLink/VideoLink-Erweiterung (4 Typen, ähnliche Anzahl Touchpoints), aber mit **höherer Komplexität pro Typ**, weil dort reine Werttypen (kein Fremdobjekt-Lookup) eingeführt wurden, während `HierarchyNodeReference`/`ProductReference` echte Cross-Entity-Auflösung (Model-Lookup, Picker mit Server-Suche, Broken-Reference-Handling) brauchen.
- **Größtes Risiko:** die ~25 duplizierten `data_type`-Switches im Backend sind fehleranfällig für „vergessene Stelle" — Testabdeckung (`AttributeTest.php`, ggf. neue Feature-Tests je Typ) ist wichtig, um Lücken früh zu finden.
- **技术Schulden-Chance:** Da ohnehin an der zentralen Enum-Liste gearbeitet wird, lohnt sich die Einführung einer zentralen `Attribute::DATA_TYPES`-Konstante (Punkt 3.1), um künftige Erweiterungen robuster zu machen — optionaler Zusatzaufwand, aber senkt Risiko für alle künftigen Typ-Erweiterungen.
- **Nicht neu, aber verstärkt sichtbar:** Es gibt keine zentrale Attributwert-Darstellungskomponente im Frontend — jede der 4 Ansichten (Produkteditor, Catalog-Preview, Admin-Preview, Bulk-Editor) hat eigene Rendering-Logik. Diese Aufgabe vergrößert diese Duplikation weiter, behebt sie aber nicht (wäre ein separates Refactoring, außerhalb des Auftragsumfangs).

---

## 6. Empfohlene Umsetzungsreihenfolge

1. Migration (`data_type`-Enum + `simple_options`-Spalte) + zentrale `Attribute::DATA_TYPES`-Konstante
2. Backend-Kern: `StoreAttributeRequest`/`UpdateAttributeRequest`/`SheetValidator`, `resolveValueColumns()` in `ProductAttributeValueController` + `ProductAttributeWriter` (deckt Produkt-Attributwerte ab)
3. Konfigurations-UI: `AttributeFormPanel.vue` (Dropdown, `simple_options`-Editor)
4. Produkteditor: `MasterHierarchyNodePickerDialog.vue` generalisieren, `PimAttributeInput.vue` + `mapDataTypeToInput()` erweitern
5. Asset Management: `MediaAttributeValueController.php` für `ProductReference` (laut Auftrag explizit gefordert)
6. Export: `MappingResolver.php` + die 4 duplizierten `resolveAttributeValue()`-Methoden
7. Preview: `ProductPreviewService.php`, `CatalogProductDetailResource.php`, `CatalogProductView.vue`
8. Tests (Backend Feature-Tests je Typ, Frontend Komponenten-Tests für neue Picker-Zweige)
9. Danach, falls gewünscht: Bulk-Editor, Import, PQL/Suche (Abschnitt 3.2) als Folge-Iteration

---

## 7. Minimalinvasive Reduktion der ~25 `data_type`-Switches

Kernbefund aus der Code-Verifikation: **Alle Write-Switches und die PQL-Spaltenwahl haben bereits einen `default → value_string`-Zweig** (verifiziert in `ProductAttributeWriter.php:198`, `ProductAttributeValueController.php:861`, `PqlSqlGenerator.php:569`). Da alle 4 neuen Typen string-backed sind (`value_string`), greift dieser `default` automatisch. Es gibt außerdem noch **kein** `app/Enums/` und **keine** zentrale Typ-Konstante — sauberer Ausgangspunkt.

### 7.1 Die „25" sind drei Buckets

| Bucket | Frage | Sites | Aufwand für die 4 neuen Typen |
|---|---|---|---|
| **A — Storage-Routing (Schreiben)** | „In welche `value_*`-Spalte?" | ~10 | **0 Edits.** `default → value_string` greift. |
| **B — Query-Spalte (PQL/Suche)** | „Auf welcher Spalte filtern?" | ~5 | **0 Edits.** PQL: `default → value_string`. Meilisearch/`SearchSchemaService`: opt-in `whereIn(['Number','Selection','Flag'])` → neue Typen sind nicht enthalten = nicht kaputt. |
| **C — Value-Presentation (Anzeige/Export)** | „Wie rendern?" | ~5 im Umfang | **Einziger Bucket mit echtem Neucode** — Referenz-Auflösung (UUID → Produkt/Knoten) ist genuin neues Verhalten. |

Ergebnis: Aus „25 Stellen anfassen" wird real **~5 echte Touch-Points + 1 neue Presenter-Klasse + 1 mechanische Enum-Dedup**. Die Speicherentscheidung `value_string` *ist* bereits die halbe Optimierung.

### 7.2 Die drei Hebel

**Hebel 1 — Eine Wahrheitsquelle für die Typ-Liste** (killt die Enum-Duplikation, rein mechanisch, ~0 Regressionsrisiko):
`Attribute::DATA_TYPES`-Konstante im Model einführen. `StoreAttributeRequest`, `UpdateAttributeRequest`, `SheetValidator`, `TemplateGenerator` referenzieren sie (`Rule::in(Attribute::DATA_TYPES)` bzw. `= Attribute::DATA_TYPES`) statt hartkodierter Strings. Der *nächste* neue Typ berührt diese 4 Dateien null Mal. Zusätzlich `Attribute::storageColumn(string): string` und die Gruppen-Konstanten `REFERENCE_TYPES`/`MULTI_VALUE_TYPES` als wiederverwendbare Klassifikation.

**Hebel 2 — Den `default`-Zweig bewusst ausnutzen** (Bucket A + B = 0 Edits): Weil alle 4 Typen in `value_string` liegen, sind Storage-Routing und Query-Spalte bereits korrekt. **Einzige Ausnahme:** `SimpleMultiSelect` kommt als Array rein und muss zu JSON kodiert werden (wie `MultiSelection`). Das wird an den **2 Write-Boundaries im Umfang** (`ProductAttributeValueController`, `MediaAttributeValueController`) plus `ProductAttributeWriter` behandelt — je eine Ein-Zeilen-Ergänzung am `MultiSelection`-Match-Arm, **nicht** in 10 Switches. Referenz-Typen + `SimpleSelect` brauchen null Write-Edits.

**Hebel 3 — Ein zentraler `AttributeValuePresenter`** (killt Bucket-C-Duplikation): Referenz-Auflösung + `SimpleMultiSelect`-Decode leben in **einer** Klasse (`app/Services/Attributes/AttributeValuePresenter.php`). Die berührten Sites (`ProductPreviewService`, `CatalogProductDetailResource`, die 4 Export-`resolveAttributeValue()`) delegieren dorthin — hinter einem dünnen Guard am Anfang jedes bestehenden Switches:
```php
// Am Anfang der bestehenden resolveDisplayValue()/resolveAttributeValue():
if (in_array($attr->data_type, Attribute::REFERENCE_TYPES, true) || $attr->data_type === 'SimpleMultiSelect') {
    return $this->attributeValuePresenter->displayValue($attrValue, $lang);
}
// ... bestehender Switch unverändert für alle Alttypen
```
`SimpleSelect` braucht dort **keinen** Guard — es fällt korrekt in den bestehenden `default → value_string`. **Null Regressionsrisiko** für Alttypen, neue Logik an genau einer Stelle. Spätere opportunistische Migration der Alttypen in den Presenter ist ein separates, optionales Refactoring.

### 7.3 Was bewusst NICHT angefasst wird (und warum es trotzdem funktioniert)

- Die ~10 Write-Switches außerhalb der 3 Boundaries (Connectors, weitere `*_attribute_values`-Controller): string-backed Typen laufen über deren `default`. `SimpleMultiSelect`-Writes finden dort im MVP nicht statt (out of scope) → kein „Array"-Problem.
- PQL/Meilisearch: unverändert lauffähig (s. Bucket B). Filterbarkeit nach Referenzen ist ein separates Zusatzfeature.
- Bulk-Editor: eigener, reduzierter Switch; deckt schon heute nicht alle Typen ab. Referenzen erscheinen dort bis zur Folge-Iteration als Roh-UUID (dokumentiert, kein Blocker).
- **Excel-/CSV-Wert-Import für Referenztypen:** Die Attribut-**Definition** (Typ anlegen) funktioniert über den Import; das Einlesen von Referenz-**Werten** löst jedoch keine SKU/Pfad → UUID-Auflösung auf (der Wert landet roh in `value_string` über den bestehenden `default`-Branch von `ImportExecutor::mapValueToColumns`). Bis zur Folge-Iteration sollten Referenz-Werte im Produkteditor über die Picker gepflegt werden, nicht per Excel-Import. Eine SKU/Pfad-Auflösung im Import-Flow ist der geplante nächste Schritt (analog zur bestehenden `Selection`-Namensauflösung).

---

## Referenzen

- Vorlage/Präzedenzfall: `docs/features/plan-hyperlink-datatypes.md` (bereits umgesetzt)
- Ist-Zustand-Doku (teils veraltet): `.claude/rules/export-format-conventions.md`, `.claude/rules/mapping-resolver.md` (nennt `MappingResolver.php` mit 456 Zeilen — tatsächlich 611 Zeilen)
- Soll-Workflow für neue Attributtypen: `.claude/skills/add-attribute-type/SKILL.md` (beschreibt ein Komponenten-Registry-Muster, das der reale Code nicht konsequent nutzt — reale Basis ist die zentrale `PimAttributeInput.vue`-Kaskade)
