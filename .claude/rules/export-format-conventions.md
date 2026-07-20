---
globs:
  - "app/Services/Export/**"
  - "app/Http/Controllers/Api/V1/*ExportController.php"
  - "app/Console/Commands/*Export.php"
  - "routes/api.php"
---

# Export-Format-Konventionen

## Pflicht-Pattern: 5 Dateien pro Export-Format

Jeder Agent erstellt genau diese 5 Dateien (Beispiel mit `{Format}` = `Onyx`, `Etim`, `Fabdis`):

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
