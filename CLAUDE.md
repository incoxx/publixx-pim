# anyPIM — Agent-Konventionen für Export-Format-Entwicklung

## Architektur: Zentrale Mapping-Engine + Format-Writer

```
┌─────────────────────────────────────────────────────────────┐
│  PIM-Quelldaten                                             │
│  (Produkte, Attribute, Preise, Medien, Relationen)          │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  Mapping-Engine  (MappingResolver — existiert bereits)      │
│                                                             │
│  Quellfelder:              Zielfelder (per ElementMap):     │
│  ├─ attribute:tech_name    ├─ Format-spezifisch             │
│  ├─ prices:price_type      │  (aus {Format}ElementMap)      │
│  ├─ media:usage_type       │                                │
│  ├─ relations:rel_type     │                                │
│  └─ collection:coll_name   │                                │
│                                                             │
│  Mapping-Regeln (PublixxExportMapping):                     │
│  [{ source, target, type }]                                 │
│                                                             │
│  11 Typen: text, unit_value, composite, media_url,          │
│  media_array, price, variant_array, relation_array, group   │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  Gemapptes Dataset  (format-neutral, key→value)             │
└────────────────────────┬────────────────────────────────────┘
                         │
            ┌────────────┼────────────┬────────────┐
            ▼            ▼            ▼            ▼
      ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
      │ GAEB XML │ │ ONYX XML │ │ ETIM     │ │ FABDIS   │
      │ Writer   │ │ Writer   │ │ Writer   │ │ Writer   │
      └──────────┘ └──────────┘ └──────────┘ └──────────┘
         ▲ nur Serialisierung — keine eigene Mapping-Logik
```

**Kernprinzip:** Die Format-Exporter sind **dünne Writer**. Die gesamte Daten-Transformation
geschieht in der zentralen Mapping-Engine (`MappingResolver`). Jeder Format-Writer:

1. Lädt Produkte via `buildFilteredProductQuery()` (aus ExportProductHelpers)
2. Löst Mapping-Regeln via `MappingResolver::resolve()` auf → bekommt key→value Map
3. Serialisiert das gemappte Dataset ins Zielformat (XMLWriter, json_encode, etc.)

---

## Aktive Agenten & Merge-Reihenfolge

| # | Branch | Format | Merge |
|---|--------|--------|-------|
| 1 | `claude/gaeb-xml-export-XSnPV` | GAEB DA XML 3.3 | **Zuerst** |
| 2 | `claude/explore-onyx-format-XuGMq` | ONYX 3.0 (Buchhandel) | Danach |
| 3 | `claude/etim-classification-mapping-e4Y3i` | ETIM Klassifikation | Danach |
| 4 | `claude/fabdis-data-export-4c4Px` | FABDIS 2.0 | Zuletzt |

**Nach jedem Merge:** verbleibende Agenten rebasen auf `main`.

---

## Pflicht-Pattern: 5 Dateien pro Export-Format

Jeder Agent erstellt genau diese 5 Dateien (Beispiel mit `{Format}` = `Gaeb`, `Onyx`, `Etim`, `Fabdis`):

### 1. FormatExporter — `app/Services/Export/{Format}FormatExporter.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\PublixxExportMapping;

class {Format}FormatExporter
{
    use ExportProductHelpers;

    // Filter (immer diese 4 Properties)
    private ?string $hierarchyId = null;
    private array $productTypeIds = [];
    private array $attributeIds = [];
    private array $priceTypeIds = [];

    // Mapping
    private array $mappingRules = [];
    private array $languages = ['de'];

    private MappingResolver $mappingResolver;

    public function __construct(MappingResolver $mappingResolver)
    {
        $this->mappingResolver = $mappingResolver;
    }

    // --- Setter (identisch für alle Formate) ---

    public function setHierarchyId(?string $id): void { $this->hierarchyId = $id; }
    public function setProductTypeIds(array $ids): void { $this->productTypeIds = $ids; }
    public function setAttributeIds(array $ids): void { $this->attributeIds = $ids; }
    public function setPriceTypeIds(array $ids): void { $this->priceTypeIds = $ids; }
    public function setMappingRules(array $rules): void { $this->mappingRules = $rules; }
    public function setLanguages(array $languages): void { $this->languages = $languages; }

    public function setMappingId(string $id): void
    {
        $mapping = PublixxExportMapping::findOrFail($id);
        $rules = $mapping->mapping_rules['rules'] ?? $mapping->mapping_rules ?? [];
        $this->mappingRules = $rules;
        if (!empty($mapping->languages)) {
            $this->languages = $mapping->languages;
        }
    }

    // --- Hauptmethode ---

    public function export(): string
    {
        // Format-spezifische Implementierung
        // XMLWriter für XML-Formate, json_encode für JSON, etc.
    }
}
```

### 2. ElementMap — `app/Services/Export/{Format}ElementMap.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Export;

class {Format}ElementMap
{
    /**
     * Verfügbare Zielfelder für dieses Format.
     */
    public const TARGET_FIELDS = [
        // Format-spezifische Felder auflisten
    ];

    /**
     * Standard-Mapping-Regeln als Vorlage.
     */
    public static function defaultMappingRules(): array
    {
        return [
            // ['source' => 'product:name', 'target' => 'zielfeld', 'type' => 'text'],
            // ['source' => 'attribute:xxx', 'target' => 'zielfeld', 'type' => 'text'],
            // ['source' => 'prices:net_list', 'target' => 'zielfeld', 'type' => 'price'],
        ];
    }

    /**
     * Defaults für Felder ohne Mapping-Wert.
     */
    public static function fieldDefaults(): array
    {
        return [];
    }
}
```

### 3. Controller — `app/Http/Controllers/Api/V1/{Format}ExportController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Export\{Format}FormatExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class {Format}ExportController extends Controller
{
    public function export(Request $request, {Format}FormatExporter $exporter): StreamedResponse
    {
        $request->validate([
            'mapping_id' => 'sometimes|nullable|string',
            'hierarchy_id' => 'sometimes|nullable|string',
            'product_type_ids' => 'sometimes|array',
            'product_type_ids.*' => 'string',
            'attribute_ids' => 'sometimes|array',
            'attribute_ids.*' => 'string',
            'price_type_ids' => 'sometimes|array',
            'price_type_ids.*' => 'string',
        ]);

        if ($request->filled('mapping_id')) {
            $exporter->setMappingId($request->input('mapping_id'));
        }
        if ($request->filled('hierarchy_id')) {
            $exporter->setHierarchyId($request->input('hierarchy_id'));
        }
        if ($request->has('product_type_ids')) {
            $exporter->setProductTypeIds($request->input('product_type_ids', []));
        }
        if ($request->has('attribute_ids')) {
            $exporter->setAttributeIds($request->input('attribute_ids', []));
        }
        if ($request->has('price_type_ids')) {
            $exporter->setPriceTypeIds($request->input('price_type_ids', []));
        }

        $output = $exporter->export();
        $fileName = '{format}-export-' . date('Y-m-d') . '.{ext}';

        return new StreamedResponse(function () use ($output) {
            echo $output;
        }, 200, [
            'Content-Type' => 'application/{content-type}; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Content-Length' => strlen($output),
        ]);
    }
}
```

### 4. Artisan Command — `app/Console/Commands/{Format}Export.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Export\{Format}FormatExporter;
use Illuminate\Console\Command;

class {Format}Export extends Command
{
    protected $signature = 'pim:{format}-export
        {--mapping= : Mapping-ID (PublixxExportMapping UUID)}
        {--hierarchy= : Hierarchie-ID}
        {--product-types= : Produkttyp-IDs (kommagetrennt)}
        {--attributes= : Attribut-IDs (kommagetrennt)}
        {--price-types= : Preistyp-IDs (kommagetrennt)}
        {-o|output= : Ausgabedatei (Standard: stdout)}';

    protected $description = 'PIM-Daten als {Format} exportieren';

    public function handle({Format}FormatExporter $exporter): int
    {
        if ($this->option('mapping')) {
            $exporter->setMappingId($this->option('mapping'));
            $this->info("Mapping: {$this->option('mapping')}");
        }
        if ($this->option('hierarchy')) {
            $exporter->setHierarchyId($this->option('hierarchy'));
            $this->info("Hierarchie: {$this->option('hierarchy')}");
        }
        if ($this->option('product-types')) {
            $ids = array_filter(explode(',', $this->option('product-types')));
            $exporter->setProductTypeIds($ids);
            $this->info('Produkttypen: ' . count($ids));
        }
        if ($this->option('attributes')) {
            $ids = array_filter(explode(',', $this->option('attributes')));
            $exporter->setAttributeIds($ids);
            $this->info('Attribute: ' . count($ids));
        }
        if ($this->option('price-types')) {
            $ids = array_filter(explode(',', $this->option('price-types')));
            $exporter->setPriceTypeIds($ids);
            $this->info('Preistypen: ' . count($ids));
        }

        $this->newLine();
        $this->info('{Format} Export wird ausgeführt...');

        try {
            $output = $exporter->export();
        } catch (\Throwable $e) {
            $this->error('Export fehlgeschlagen: ' . $e->getMessage());
            return self::FAILURE;
        }

        $outputFile = $this->option('output');
        if ($outputFile) {
            file_put_contents($outputFile, $output);
            $this->info("Export geschrieben: {$outputFile}");
            $this->info('Größe: ' . number_format(strlen($output)) . ' Bytes');
        } else {
            $this->line($output);
        }

        return self::SUCCESS;
    }
}
```

### 5. Route — in `routes/api.php`

Nach dem BMEcat-Block (Zeile ~629) hinzufügen:

```php
// Enterprise: {Format} Export
Route::middleware('module:{format}')->group(function () {
    Route::post('{format}-export', [{Format}ExportController::class, 'export']);
});
```

---

## Pflicht-Regeln

1. **`use ExportProductHelpers;`** — Trait verwenden, nicht den Code kopieren
2. **`MappingResolver`** — für Feld-Mapping nutzen (existiert in `app/Services/Export/MappingResolver.php`)
3. **`StreamedResponse`** — für Downloads (nie den gesamten Output in Memory buffern)
4. **`XMLWriter`** — für XML-Formate (memory-effizient, Streaming)
5. **Filtering** — hierarchy, product_types, attributes, price_types immer unterstützen
6. **Chunking** — `$query->chunk(500, fn ...)` bei >1000 Produkten
7. **i18n** — Languages-Parameter berücksichtigen
8. **Keine gemeinsamen Dateien ändern** — außer `routes/api.php` (eigenen Block hinzufügen)

## Gemeinsam genutzte Dateien (NUR LESEN)

| Datei | Zweck |
|-------|-------|
| `app/Services/Export/ExportProductHelpers.php` | Trait: `resolveAttributeValue()`, `getAttributeValue()`, `loadHierarchyById()`, `buildFilteredProductQuery()` |
| `app/Services/Export/MappingResolver.php` | Löst Mapping-Regeln auf: source→target mit type (text, price, unit_value, composite, media_url, etc.) |
| `app/Services/Export/DatasetBuilder.php` | Erstellt JSON-Datasets (flat/nested/publixx) |
| `app/Services/Export/Writers/*` | Output-Writer: XmlWriter, CsvWriter, JsonWriter, ExcelWriter |
| `app/Models/PublixxExportMapping.php` | Model: name, mapping_rules (JSON), languages, flatten_mode |

## Referenz-Implementierung

**GAEB-Export** (`claude/gaeb-xml-export-XSnPV`) dient als Vorlage:
- `app/Services/Export/GaebFormatExporter.php` — 496 Zeilen, XMLWriter, Hierarchie→BoQ-Mapping
- `app/Services/Export/GaebElementMap.php` — 7 Zielfelder, Default-Regeln
- `app/Http/Controllers/Api/V1/GaebExportController.php` — 54 Zeilen
- `app/Console/Commands/GaebExport.php` — 80 Zeilen
- Route: `POST /api/v1/gaeb-export` mit `module:gaeb` Middleware

## MappingResolver — Zentrale Mapping-Engine (existiert bereits)

**Datei:** `app/Services/Export/MappingResolver.php` (456 Zeilen)

### Aufruf im FormatExporter

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

### Verfügbare Quellfelder (Source-Namespaces)

| Prefix | Beispiel | Beschreibung |
|--------|----------|-------------|
| `attribute:` | `attribute:product-name-dict` | Attributwert nach technical_name |
| `prices:` | `prices:list_price` | Preis nach PriceType technical_name |
| `media:` | `media:teaser` | Medien-URL nach UsageType technical_name |
| `relations:` | `relations:accessories` | Produktrelationen nach RelationType technical_name |
| `collection:` | `collection:technische-daten` | Attribut-Gruppe nach Collection-Name |

### Verfügbare Mapping-Typen

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

### Mapping-Regel-Struktur (JSON)

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

---

## ElementMap-Konvention für Zielschema

Jede `{Format}ElementMap` definiert das **Zielschema** des Formats. Die `TARGET_FIELDS` Konstante
listet alle gültigen Zielfelder, die in Mapping-Regeln als `target` verwendet werden können.

```php
// Beispiel: GaebElementMap
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

---

## Technologie-Stack

- **Backend:** PHP 8.4, Laravel 11
- **Frontend:** Vue 3, Vite, Tailwind CSS 4, DaisyUI 5
- **Datenbank:** MySQL 8+
- **Tests:** PHPUnit (Backend), Vitest (Frontend)
- **Code-Style:** `declare(strict_types=1)`, deutsche Kommentare/Beschreibungen
