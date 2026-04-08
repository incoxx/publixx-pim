<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Attribute;
use App\Models\ExportProfile;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductMediaAssignment;
use App\Services\Export\Writers\CsvWriter;
use App\Services\Export\Writers\ExcelWriter;
use App\Services\Export\Writers\JsonWriter;
use App\Services\Export\Writers\XmlWriter;
use App\Services\Search\SearchProfileQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Orchestriert den Export basierend auf einem ExportProfile.
 *
 * 1. Lädt das verknüpfte SearchProfile → führt die Suche aus → Produkt-IDs
 * 2. Baut die Exportdaten zusammen
 * 3. Delegiert an den richtigen Kanal-Writer
 */
class ExportProfileService
{
    /**
     * Führt einen Export basierend auf dem Profil aus.
     */
    public function execute(ExportProfile $profile, ?string $fileName = null, bool $zip = false): StreamedResponse|JsonResponse
    {
        $products = $this->resolveProducts($profile);
        $data = $this->buildExportData($profile, $products);

        $resolvedFileName = $this->resolveFileName($profile, $fileName);

        $response = match ($profile->format) {
            'csv' => (new CsvWriter())->write($data, $resolvedFileName),
            'json' => (new JsonWriter())->write($data, $resolvedFileName),
            'xml' => (new XmlWriter())->write($data, $resolvedFileName),
            default => (new ExcelWriter())->write($data, $resolvedFileName),
        };

        if (!$zip) {
            return $response;
        }

        return $this->wrapInZip($response, $resolvedFileName, $profile->format);
    }

    /**
     * Gibt die JSON-Exportdaten direkt als API-Response zurück (kein Download).
     */
    public function stream(ExportProfile $profile): \Illuminate\Http\JsonResponse
    {
        $products = $this->resolveProducts($profile);
        $data = $this->buildExportData($profile, $products);

        return JsonWriter::asInlineResponse($data);
    }

    /**
     * Führt den Export chunk-basiert aus und schreibt direkt in eine Datei (für Async-Jobs).
     *
     * Produkte werden in 500er-Chunks verarbeitet — kein vollständiges $data-Array im RAM.
     * Gibt ['file_name' => ..., 'ext' => ..., 'output_path' => ...] zurück.
     */
    public function executeToFile(ExportProfile $profile, string $outputBasePath): array
    {
        $format           = $profile->format ?? 'excel';
        $resolvedFileName = $this->resolveFileName($profile, null);
        $languages        = $profile->languages ?? ['de'];

        $query = $this->buildProductQuery($profile);

        $eagerRelations = ['productType'];
        if ($profile->include_variants) {
            $eagerRelations[] = 'variants';
        }

        return match ($format) {
            'csv'  => $this->executeCsvToFile($profile, $query, $eagerRelations, $languages, $outputBasePath, $resolvedFileName),
            'json' => $this->executeJsonToFile($profile, $query, $eagerRelations, $languages, $outputBasePath, $resolvedFileName),
            'xml'  => $this->executeXmlToFile($profile, $query, $eagerRelations, $languages, $outputBasePath, $resolvedFileName),
            default => $this->executeExcelToFile($profile, $query, $eagerRelations, $languages, $outputBasePath, $resolvedFileName),
        };
    }

    // ─── Chunk-basierte Format-Methoden (nur für executeToFile) ────────────────

    /**
     * Excel-Export: PhpSpreadsheet ohne Zwischen-Array, Zeilen direkt in Sheets schreiben.
     */
    private function executeExcelToFile(
        ExportProfile $profile,
        Builder $query,
        array $eagerRelations,
        array $languages,
        string $outputBasePath,
        string $resolvedFileName,
    ): array {
        $spreadsheet = new Spreadsheet();

        // Attribut-Definitionen einmalig laden (unkritische Größe)
        $attributeDefs = $this->loadAttributeDefs($profile);

        // Sheets erstellen
        $prodSheet = $spreadsheet->createSheet();
        $prodSheet->setTitle('Produkte');
        foreach (['SKU', 'Name', 'Status', 'EAN', 'Produkttyp'] as $col => $header) {
            $prodSheet->setCellValue([$col + 1, 1], $header);
        }

        $attrSheet = null;
        if ($profile->include_attributes) {
            $attrSheet = $spreadsheet->createSheet();
            $attrSheet->setTitle('Attributwerte');
            foreach (['SKU', 'Attribut', 'Attributname', 'Wert', 'Sprache'] as $col => $h) {
                $attrSheet->setCellValue([$col + 1, 1], $h);
            }
        }

        $priceSheet = null;
        if ($profile->include_prices) {
            $priceSheet = $spreadsheet->createSheet();
            $priceSheet->setTitle('Preise');
            foreach (['SKU', 'Preistyp', 'Betrag', 'Währung', 'Ab Menge', 'Gültig von', 'Gültig bis'] as $col => $h) {
                $priceSheet->setCellValue([$col + 1, 1], $h);
            }
        }

        $relSheet = null;
        if ($profile->include_relations) {
            $relSheet = $spreadsheet->createSheet();
            $relSheet->setTitle('Beziehungen');
            foreach (['SKU', 'Ziel-SKU', 'Beziehungstyp', 'Position'] as $col => $h) {
                $relSheet->setCellValue([$col + 1, 1], $h);
            }
        }

        $mediaSheet = null;
        if ($profile->include_media) {
            $mediaSheet = $spreadsheet->createSheet();
            $mediaSheet->setTitle('Medien');
            foreach (['SKU', 'Dateiname', 'Verwendungstyp', 'Position'] as $col => $h) {
                $mediaSheet->setCellValue([$col + 1, 1], $h);
            }
        }

        // Default-Sheet entfernen
        $spreadsheet->removeSheetByIndex(0);

        $rows = ['prod' => 2, 'attr' => 2, 'price' => 2, 'rel' => 2, 'media' => 2];

        $query->with($eagerRelations)->chunk(500, function ($products) use (
            $profile, $languages, $attributeDefs,
            $prodSheet, $attrSheet, $priceSheet, $relSheet, $mediaSheet,
            &$rows,
        ) {
            $productIds = $products->pluck('id')->toArray();

            $attrValues = $this->loadChunkAttributeValues($productIds, $profile);
            $prices     = $this->loadChunkPrices($productIds, $profile);
            $relations  = $this->loadChunkRelations($productIds, $profile);
            $media      = $this->loadChunkMedia($productIds, $profile);

            foreach ($products as $product) {
                $prodSheet->setCellValue([1, $rows['prod']], $product->sku ?? '');
                $prodSheet->setCellValue([2, $rows['prod']], $product->name ?? '');
                $prodSheet->setCellValue([3, $rows['prod']], $product->status ?? '');
                $prodSheet->setCellValue([4, $rows['prod']], $product->ean ?? '');
                $prodSheet->setCellValue([5, $rows['prod']], $product->productType?->name_de ?? '');
                $rows['prod']++;

                if ($attrSheet !== null && $attrValues->has($product->id)) {
                    foreach ($attrValues[$product->id] as $av) {
                        if (!in_array($av->language ?: 'de', $languages)) {
                            continue;
                        }
                        $def = $attributeDefs[$av->attribute_id] ?? null;
                        $attrSheet->setCellValue([1, $rows['attr']], $product->sku);
                        $attrSheet->setCellValue([2, $rows['attr']], $def?->technical_name ?? $av->attribute_id);
                        $attrSheet->setCellValue([3, $rows['attr']], $def?->name_de ?? '');
                        $attrSheet->setCellValue([4, $rows['attr']], $av->value_string ?? $av->value_number ?? $av->value_date ?? $av->value_flag);
                        $attrSheet->setCellValue([5, $rows['attr']], $av->language);
                        $rows['attr']++;
                    }
                }

                if ($priceSheet !== null && $prices->has($product->id)) {
                    foreach ($prices[$product->id] as $price) {
                        $priceSheet->setCellValue([1, $rows['price']], $product->sku);
                        $priceSheet->setCellValue([2, $rows['price']], $price->price_type);
                        $priceSheet->setCellValue([3, $rows['price']], $price->amount);
                        $priceSheet->setCellValue([4, $rows['price']], $price->currency);
                        $priceSheet->setCellValue([5, $rows['price']], $price->scale_from);
                        $priceSheet->setCellValue([6, $rows['price']], $price->valid_from);
                        $priceSheet->setCellValue([7, $rows['price']], $price->valid_to);
                        $rows['price']++;
                    }
                }

                if ($relSheet !== null && $relations->has($product->id)) {
                    foreach ($relations[$product->id] as $rel) {
                        $relSheet->setCellValue([1, $rows['rel']], $product->sku);
                        $relSheet->setCellValue([2, $rows['rel']], $rel->targetProduct?->sku);
                        $relSheet->setCellValue([3, $rows['rel']], $rel->relationType?->name);
                        $relSheet->setCellValue([4, $rows['rel']], $rel->position);
                        $rows['rel']++;
                    }
                }

                if ($mediaSheet !== null && $media->has($product->id)) {
                    foreach ($media[$product->id] as $m) {
                        $mediaSheet->setCellValue([1, $rows['media']], $product->sku);
                        $mediaSheet->setCellValue([2, $rows['media']], $m->media?->file_name);
                        $mediaSheet->setCellValue([3, $rows['media']], $m->usage_type);
                        $mediaSheet->setCellValue([4, $rows['media']], $m->position);
                        $rows['media']++;
                    }
                }
            }

            unset($products, $attrValues, $prices, $relations, $media);
            gc_collect_cycles();
        });

        $outputPath = $outputBasePath . '.xlsx';
        (new Xlsx($spreadsheet))->save($outputPath);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return ['file_name' => $resolvedFileName, 'ext' => 'xlsx', 'output_path' => $outputPath];
    }

    /**
     * CSV-Export: direkt in Temp-Dateien schreiben, kein Array im RAM.
     */
    private function executeCsvToFile(
        ExportProfile $profile,
        Builder $query,
        array $eagerRelations,
        array $languages,
        string $outputBasePath,
        string $resolvedFileName,
    ): array {
        $multiSheet = $profile->include_attributes
            || $profile->include_prices
            || $profile->include_relations
            || $profile->include_media;

        if (!$multiSheet) {
            // Einzelne CSV-Datei
            $outputPath = $outputBasePath . '.csv';
            $handle = fopen($outputPath, 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['SKU', 'Name', 'Status', 'EAN', 'Produkttyp'], ';');

            $query->with($eagerRelations)->chunk(500, function ($products) use ($handle) {
                foreach ($products as $p) {
                    fputcsv($handle, [
                        $p->sku ?? '',
                        $p->name ?? '',
                        $p->status ?? '',
                        $p->ean ?? '',
                        $p->productType?->name_de ?? '',
                    ], ';');
                }
                unset($products);
            });

            fclose($handle);
            return ['file_name' => $resolvedFileName, 'ext' => 'csv', 'output_path' => $outputPath];
        }

        // Multi-Sheet: Temp-Dateien → ZIP
        $attributeDefs = $this->loadAttributeDefs($profile);
        $tmpFiles = [];
        $handles  = [];

        $tmpFiles['products'] = tempnam(sys_get_temp_dir(), 'exp_prod_');
        $handles['products']  = fopen($tmpFiles['products'], 'w');
        fprintf($handles['products'], chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($handles['products'], ['SKU', 'Name', 'Status', 'EAN', 'Produkttyp'], ';');

        if ($profile->include_attributes) {
            $tmpFiles['attr'] = tempnam(sys_get_temp_dir(), 'exp_attr_');
            $handles['attr']  = fopen($tmpFiles['attr'], 'w');
            fprintf($handles['attr'], chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handles['attr'], ['SKU', 'Attribut', 'Attributname', 'Wert', 'Sprache'], ';');
        }

        if ($profile->include_prices) {
            $tmpFiles['prices'] = tempnam(sys_get_temp_dir(), 'exp_price_');
            $handles['prices']  = fopen($tmpFiles['prices'], 'w');
            fprintf($handles['prices'], chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handles['prices'], ['SKU', 'Preistyp', 'Betrag', 'Währung', 'Ab Menge', 'Gültig von', 'Gültig bis'], ';');
        }

        if ($profile->include_relations) {
            $tmpFiles['relations'] = tempnam(sys_get_temp_dir(), 'exp_rel_');
            $handles['relations']  = fopen($tmpFiles['relations'], 'w');
            fprintf($handles['relations'], chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handles['relations'], ['SKU', 'Ziel-SKU', 'Beziehungstyp', 'Position'], ';');
        }

        if ($profile->include_media) {
            $tmpFiles['media'] = tempnam(sys_get_temp_dir(), 'exp_media_');
            $handles['media']  = fopen($tmpFiles['media'], 'w');
            fprintf($handles['media'], chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handles['media'], ['SKU', 'Dateiname', 'Verwendungstyp', 'Position'], ';');
        }

        $query->with($eagerRelations)->chunk(500, function ($products) use (
            $profile, $languages, $attributeDefs, $handles,
        ) {
            $productIds = $products->pluck('id')->toArray();

            $attrValues = $this->loadChunkAttributeValues($productIds, $profile);
            $prices     = $this->loadChunkPrices($productIds, $profile);
            $relations  = $this->loadChunkRelations($productIds, $profile);
            $media      = $this->loadChunkMedia($productIds, $profile);

            foreach ($products as $p) {
                fputcsv($handles['products'], [
                    $p->sku ?? '', $p->name ?? '', $p->status ?? '',
                    $p->ean ?? '', $p->productType?->name_de ?? '',
                ], ';');

                if (isset($handles['attr']) && $attrValues->has($p->id)) {
                    foreach ($attrValues[$p->id] as $av) {
                        if (!in_array($av->language ?: 'de', $languages)) {
                            continue;
                        }
                        $def = $attributeDefs[$av->attribute_id] ?? null;
                        fputcsv($handles['attr'], [
                            $p->sku,
                            $def?->technical_name ?? $av->attribute_id,
                            $def?->name_de ?? '',
                            $av->value_string ?? $av->value_number ?? $av->value_date ?? $av->value_flag,
                            $av->language,
                        ], ';');
                    }
                }

                if (isset($handles['prices']) && $prices->has($p->id)) {
                    foreach ($prices[$p->id] as $price) {
                        fputcsv($handles['prices'], [
                            $p->sku, $price->price_type, $price->amount, $price->currency,
                            $price->scale_from, $price->valid_from, $price->valid_to,
                        ], ';');
                    }
                }

                if (isset($handles['relations']) && $relations->has($p->id)) {
                    foreach ($relations[$p->id] as $rel) {
                        fputcsv($handles['relations'], [
                            $p->sku, $rel->targetProduct?->sku,
                            $rel->relationType?->name, $rel->position,
                        ], ';');
                    }
                }

                if (isset($handles['media']) && $media->has($p->id)) {
                    foreach ($media[$p->id] as $m) {
                        fputcsv($handles['media'], [
                            $p->sku, $m->media?->file_name, $m->usage_type, $m->position,
                        ], ';');
                    }
                }
            }

            unset($products, $attrValues, $prices, $relations, $media);
            gc_collect_cycles();
        });

        foreach ($handles as $handle) {
            fclose($handle);
        }

        $outputPath = $outputBasePath . '.zip';
        $zip = new ZipArchive();
        $zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tmpFiles['products'], 'produkte.csv');
        if (isset($tmpFiles['attr']))      { $zip->addFile($tmpFiles['attr'],      'attributwerte.csv'); }
        if (isset($tmpFiles['prices']))    { $zip->addFile($tmpFiles['prices'],    'preise.csv'); }
        if (isset($tmpFiles['relations'])) { $zip->addFile($tmpFiles['relations'], 'beziehungen.csv'); }
        if (isset($tmpFiles['media']))     { $zip->addFile($tmpFiles['media'],     'medien.csv'); }
        $zip->close();

        foreach ($tmpFiles as $tmp) {
            @unlink($tmp);
        }

        return ['file_name' => $resolvedFileName, 'ext' => 'zip', 'output_path' => $outputPath];
    }

    /**
     * JSON-Export: Produkt-Objekte direkt in Datei schreiben, kein json_encode auf Gesamt-Array.
     */
    private function executeJsonToFile(
        ExportProfile $profile,
        Builder $query,
        array $eagerRelations,
        array $languages,
        string $outputBasePath,
        string $resolvedFileName,
    ): array {
        $outputPath    = $outputBasePath . '.json';
        $handle        = fopen($outputPath, 'w');
        $attributeDefs = $this->loadAttributeDefs($profile);
        $firstProduct  = true;

        fwrite($handle, '{"products":[' . "\n");

        $query->with($eagerRelations)->chunk(500, function ($products) use (
            $profile, $languages, $attributeDefs, $handle, &$firstProduct,
        ) {
            $productIds = $products->pluck('id')->toArray();

            $attrValues = $this->loadChunkAttributeValues($productIds, $profile);
            $prices     = $this->loadChunkPrices($productIds, $profile);
            $relations  = $this->loadChunkRelations($productIds, $profile);
            $media      = $this->loadChunkMedia($productIds, $profile);

            foreach ($products as $p) {
                $productData = [
                    'sku'          => $p->sku,
                    'name'         => $p->name,
                    'status'       => $p->status,
                    'ean'          => $p->ean,
                    'product_type' => $p->productType?->name_de,
                ];

                if ($profile->include_attributes && $attrValues->has($p->id)) {
                    $productData['attributes'] = [];
                    foreach ($attrValues[$p->id] as $av) {
                        if (!in_array($av->language ?: 'de', $languages)) {
                            continue;
                        }
                        $def = $attributeDefs[$av->attribute_id] ?? null;
                        $productData['attributes'][] = [
                            'attribute'      => $def?->technical_name ?? $av->attribute_id,
                            'attribute_name' => $def?->name_de ?? '',
                            'value'          => $av->value_string ?? $av->value_number ?? $av->value_date ?? $av->value_flag,
                            'language'       => $av->language,
                        ];
                    }
                }

                if ($profile->include_prices && $prices->has($p->id)) {
                    $productData['prices'] = $prices[$p->id]->map(fn ($pr) => [
                        'price_type' => $pr->price_type,
                        'amount'     => $pr->amount,
                        'currency'   => $pr->currency,
                        'scale_from' => $pr->scale_from,
                        'valid_from' => $pr->valid_from,
                        'valid_to'   => $pr->valid_to,
                    ])->values()->toArray();
                }

                if ($profile->include_relations && $relations->has($p->id)) {
                    $productData['relations'] = $relations[$p->id]->map(fn ($r) => [
                        'target_sku'    => $r->targetProduct?->sku,
                        'relation_type' => $r->relationType?->name,
                        'position'      => $r->position,
                    ])->values()->toArray();
                }

                if ($profile->include_media && $media->has($p->id)) {
                    $productData['media'] = $media[$p->id]->map(fn ($m) => [
                        'file_name'  => $m->media?->file_name,
                        'usage_type' => $m->usage_type,
                        'position'   => $m->position,
                    ])->values()->toArray();
                }

                if ($profile->include_variants && $p->relationLoaded('variants')) {
                    $productData['variants'] = $p->variants->map(fn ($v) => [
                        'sku'    => $v->sku,
                        'name'   => $v->name,
                        'ean'    => $v->ean,
                        'status' => $v->status,
                    ])->values()->toArray();
                }

                if (!$firstProduct) {
                    fwrite($handle, ",\n");
                }
                fwrite($handle, json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $firstProduct = false;
            }

            unset($products, $attrValues, $prices, $relations, $media);
            gc_collect_cycles();
        });

        fwrite($handle, "\n]}");
        fclose($handle);

        return ['file_name' => $resolvedFileName, 'ext' => 'json', 'output_path' => $outputPath];
    }

    /**
     * XML-Export: XMLWriter direkt in Datei, kein Puffer.
     */
    private function executeXmlToFile(
        ExportProfile $profile,
        Builder $query,
        array $eagerRelations,
        array $languages,
        string $outputBasePath,
        string $resolvedFileName,
    ): array {
        $outputPath    = $outputBasePath . '.xml';
        $attributeDefs = $this->loadAttributeDefs($profile);

        $xml = new \XMLWriter();
        $xml->openURI($outputPath);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startElement('export');
        $xml->writeAttribute('generated', now()->toIso8601String());

        $query->with($eagerRelations)->chunk(500, function ($products) use (
            $profile, $languages, $attributeDefs, $xml,
        ) {
            $productIds = $products->pluck('id')->toArray();

            $attrValues = $this->loadChunkAttributeValues($productIds, $profile);
            $prices     = $this->loadChunkPrices($productIds, $profile);
            $relations  = $this->loadChunkRelations($productIds, $profile);
            $media      = $this->loadChunkMedia($productIds, $profile);

            foreach ($products as $p) {
                $xml->startElement('product');
                $xml->writeElement('sku', $p->sku ?? '');
                $xml->writeElement('name', $p->name ?? '');
                $xml->writeElement('status', $p->status ?? '');
                $xml->writeElement('ean', $p->ean ?? '');
                $xml->writeElement('product_type', $p->productType?->name_de ?? '');

                if ($profile->include_attributes && $attrValues->has($p->id)) {
                    $xml->startElement('attributes');
                    foreach ($attrValues[$p->id] as $av) {
                        if (!in_array($av->language ?: 'de', $languages)) {
                            continue;
                        }
                        $def = $attributeDefs[$av->attribute_id] ?? null;
                        $xml->startElement('attribute');
                        $xml->writeElement('technical_name', $def?->technical_name ?? $av->attribute_id);
                        $xml->writeElement('name', $def?->name_de ?? '');
                        $xml->writeElement('value', (string) ($av->value_string ?? $av->value_number ?? $av->value_date ?? (int) ($av->value_flag ?? 0)));
                        $xml->writeElement('language', $av->language ?? '');
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                if ($profile->include_prices && $prices->has($p->id)) {
                    $xml->startElement('prices');
                    foreach ($prices[$p->id] as $price) {
                        $xml->startElement('price');
                        $xml->writeElement('type', $price->price_type ?? '');
                        $xml->writeElement('amount', (string) ($price->amount ?? ''));
                        $xml->writeElement('currency', $price->currency ?? '');
                        $xml->writeElement('scale_from', (string) ($price->scale_from ?? ''));
                        $xml->writeElement('valid_from', (string) ($price->valid_from ?? ''));
                        $xml->writeElement('valid_to', (string) ($price->valid_to ?? ''));
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                if ($profile->include_relations && $relations->has($p->id)) {
                    $xml->startElement('relations');
                    foreach ($relations[$p->id] as $rel) {
                        $xml->startElement('relation');
                        $xml->writeElement('target_sku', $rel->targetProduct?->sku ?? '');
                        $xml->writeElement('type', $rel->relationType?->name ?? '');
                        $xml->writeElement('position', (string) ($rel->position ?? ''));
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                if ($profile->include_media && $media->has($p->id)) {
                    $xml->startElement('media');
                    foreach ($media[$p->id] as $m) {
                        $xml->startElement('item');
                        $xml->writeElement('file_name', $m->media?->file_name ?? '');
                        $xml->writeElement('usage_type', $m->usage_type ?? '');
                        $xml->writeElement('position', (string) ($m->position ?? ''));
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                if ($profile->include_variants && $p->relationLoaded('variants')) {
                    $xml->startElement('variants');
                    foreach ($p->variants as $v) {
                        $xml->startElement('variant');
                        $xml->writeElement('sku', $v->sku ?? '');
                        $xml->writeElement('name', $v->name ?? '');
                        $xml->writeElement('ean', $v->ean ?? '');
                        $xml->writeElement('status', $v->status ?? '');
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                $xml->endElement(); // product
            }

            $xml->flush();
            unset($products, $attrValues, $prices, $relations, $media);
            gc_collect_cycles();
        });

        $xml->endElement(); // export
        $xml->endDocument();
        $xml->flush();

        return ['file_name' => $resolvedFileName, 'ext' => 'xml', 'output_path' => $outputPath];
    }

    // ─── Chunk-Lade-Hilfsmethoden ──────────────────────────────────────────────

    /** Lädt Attribut-Definitionen einmalig (klein, vor dem Chunking). */
    private function loadAttributeDefs(ExportProfile $profile): \Illuminate\Support\Collection
    {
        if (!$profile->include_attributes) {
            return collect();
        }
        $q = Attribute::query();
        if (!empty($profile->attribute_ids)) {
            $q->whereIn('id', $profile->attribute_ids);
        }
        return $q->get()->keyBy('id');
    }

    /** Lädt Attributwerte für einen Produkt-Chunk. */
    private function loadChunkAttributeValues(array $productIds, ExportProfile $profile): \Illuminate\Support\Collection
    {
        if (!$profile->include_attributes || empty($productIds)) {
            return collect();
        }
        $q = ProductAttributeValue::whereIn('product_id', $productIds);
        if (!empty($profile->attribute_ids)) {
            $q->whereIn('attribute_id', $profile->attribute_ids);
        }
        return $q->get()->groupBy('product_id');
    }

    /** Lädt Preise für einen Produkt-Chunk. */
    private function loadChunkPrices(array $productIds, ExportProfile $profile): \Illuminate\Support\Collection
    {
        if (!$profile->include_prices || empty($productIds)) {
            return collect();
        }
        return ProductPrice::whereIn('product_id', $productIds)->get()->groupBy('product_id');
    }

    /** Lädt Beziehungen für einen Produkt-Chunk. */
    private function loadChunkRelations(array $productIds, ExportProfile $profile): \Illuminate\Support\Collection
    {
        if (!$profile->include_relations || empty($productIds)) {
            return collect();
        }
        return ProductRelation::whereIn('source_product_id', $productIds)
            ->with(['targetProduct', 'relationType'])
            ->get()
            ->groupBy('source_product_id');
    }

    /** Lädt Medien für einen Produkt-Chunk. */
    private function loadChunkMedia(array $productIds, ExportProfile $profile): \Illuminate\Support\Collection
    {
        if (!$profile->include_media || empty($productIds)) {
            return collect();
        }
        return ProductMediaAssignment::whereIn('product_id', $productIds)
            ->with('media')
            ->get()
            ->groupBy('product_id');
    }

    /**
     * Gibt die Anzahl der Produkte zurück, die der Export treffen würde.
     */
    public function count(ExportProfile $profile): int
    {
        return $this->buildProductQuery($profile)->count();
    }

    private function buildProductQuery(ExportProfile $profile): Builder
    {
        $searchProfile = $profile->searchProfile;
        if (!$searchProfile) {
            return Product::query()->where('product_type_ref', 'product');
        }

        return app(SearchProfileQueryBuilder::class)->forProducts($searchProfile);
    }

    private function resolveProducts(ExportProfile $profile): \Illuminate\Support\Collection
    {
        $query = $this->buildProductQuery($profile);

        $relations = ['productType'];

        if ($profile->include_variants) {
            $relations[] = 'variants';
        }

        return $query->with($relations)->get();
    }

    /**
     * Baut die Exportdaten basierend auf den Profil-Einstellungen.
     */
    private function buildExportData(ExportProfile $profile, \Illuminate\Support\Collection $products): array
    {
        $data = ['products' => []];
        $productIds = $products->pluck('id')->toArray();
        $languages = $profile->languages ?? ['de'];

        // Attribute laden
        $attributeValues = [];
        if ($profile->include_attributes) {
            $attrQuery = ProductAttributeValue::whereIn('product_id', $productIds);
            if (!empty($profile->attribute_ids)) {
                $attrQuery->whereIn('attribute_id', $profile->attribute_ids);
            }
            $attributeValues = $attrQuery->get()->groupBy('product_id');
        }

        // Preise laden
        $prices = [];
        if ($profile->include_prices) {
            $prices = ProductPrice::whereIn('product_id', $productIds)
                ->get()
                ->groupBy('product_id');
        }

        // Beziehungen laden
        $relations = [];
        if ($profile->include_relations) {
            $relations = ProductRelation::whereIn('source_product_id', $productIds)
                ->get()
                ->groupBy('source_product_id');
        }

        // Medien laden
        $media = [];
        if ($profile->include_media) {
            $media = ProductMediaAssignment::whereIn('product_id', $productIds)
                ->with('media')
                ->get()
                ->groupBy('product_id');
        }

        // Attribute-Definitionen laden
        $attributes = [];
        if ($profile->include_attributes) {
            $attrQuery = Attribute::query();
            if (!empty($profile->attribute_ids)) {
                $attrQuery->whereIn('id', $profile->attribute_ids);
            }
            $attributes = $attrQuery->get()->keyBy('id');
        }

        foreach ($products as $product) {
            $productData = [
                'sku' => $product->sku,
                'name' => $product->name,
                'status' => $product->status,
                'ean' => $product->ean,
                'product_type' => $product->productType?->name_de,
            ];

            if ($profile->include_attributes && isset($attributeValues[$product->id])) {
                $productData['attributes'] = [];
                foreach ($attributeValues[$product->id] as $attrValue) {
                    if (!in_array($attrValue->language ?: 'de', $languages)) {
                        continue;
                    }
                    $attrDef = $attributes[$attrValue->attribute_id] ?? null;
                    $productData['attributes'][] = [
                        'attribute' => $attrDef?->technical_name ?? $attrValue->attribute_id,
                        'attribute_name' => $attrDef?->name_de ?? '',
                        'value' => $attrValue->value_string ?? $attrValue->value_number ?? $attrValue->value_date ?? $attrValue->value_flag,
                        'language' => $attrValue->language,
                    ];
                }
            }

            if ($profile->include_prices && isset($prices[$product->id])) {
                $productData['prices'] = $prices[$product->id]->map(fn($p) => [
                    'price_type' => $p->price_type,
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'scale_from' => $p->scale_from,
                    'valid_from' => $p->valid_from,
                    'valid_to' => $p->valid_to,
                ])->toArray();
            }

            if ($profile->include_relations && isset($relations[$product->id])) {
                $productData['relations'] = $relations[$product->id]->map(fn($r) => [
                    'target_sku' => $r->targetProduct?->sku,
                    'relation_type' => $r->relationType?->name,
                    'position' => $r->position,
                ])->toArray();
            }

            if ($profile->include_media && isset($media[$product->id])) {
                $productData['media'] = $media[$product->id]->map(fn($m) => [
                    'file_name' => $m->media?->file_name,
                    'usage_type' => $m->usage_type,
                    'position' => $m->position,
                ])->toArray();
            }

            if ($profile->include_variants && $product->relationLoaded('variants')) {
                $productData['variants'] = $product->variants->map(fn($v) => [
                    'sku' => $v->sku,
                    'name' => $v->name,
                    'ean' => $v->ean,
                    'status' => $v->status,
                ])->toArray();
            }

            $data['products'][] = $productData;
        }

        return $data;
    }

    private function resolveFileName(ExportProfile $profile, ?string $fileName): string
    {
        if ($fileName) {
            return $fileName;
        }

        $template = $profile->file_name_template;
        if (!$template) {
            $template = 'export-{date}';
        }

        $name = str_replace(
            ['{date}', '{profile}', '{format}'],
            [now()->format('Y-m-d'), $profile->name, $profile->format],
            $template,
        );

        return $name;
    }

    /**
     * Wraps a streamed export response into a ZIP archive.
     */
    private function wrapInZip(StreamedResponse $originalResponse, string $fileName, string $format): StreamedResponse
    {
        $ext = match ($format) {
            'csv' => 'csv',
            'json' => 'json',
            'xml' => 'xml',
            default => 'xlsx',
        };
        $innerName = "{$fileName}.{$ext}";
        $zipName = "{$fileName}.zip";

        // Capture the original response body
        ob_start();
        $originalResponse->sendContent();
        $content = ob_get_clean();

        $tempFile = tempnam(sys_get_temp_dir(), 'pim_zip_');

        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::OVERWRITE);
        $zip->addFromString($innerName, $content);
        $zip->close();

        $zipContent = file_get_contents($tempFile);
        unlink($tempFile);

        return new StreamedResponse(function () use ($zipContent) {
            echo $zipContent;
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$zipName}\"",
            'Content-Length' => strlen($zipContent),
            'Cache-Control' => 'no-store',
        ]);
    }
}
