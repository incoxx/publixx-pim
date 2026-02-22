<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\OutputHierarchyProductAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\ProductRelation;
use App\Models\ProductType;
use App\Models\Unit;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exportiert alle PIM-Daten in eine Excel-Datei im gleichen 14-Sheet-Format,
 * das der Import verwendet. Ermöglicht Round-Trip Import/Export.
 */
class ImportFormatExporter
{
    /**
     * Header-Definitionen pro Sheet – identisch zu TemplateGenerator::SHEET_HEADERS.
     */
    private const array SHEET_HEADERS = [
        '01_Produkttypen' => [
            'A' => 'Technischer Name*',
            'B' => 'Name (Deutsch)*',
            'C' => 'Name (Englisch)',
            'D' => 'Beschreibung',
            'E' => 'Hat Varianten (Ja/Nein)',
            'F' => 'Hat EAN (Ja/Nein)',
            'G' => 'Hat Preise (Ja/Nein)',
            'H' => 'Hat Medien (Ja/Nein)',
        ],
        '02_Attributgruppen' => [
            'A' => 'Technischer Name*',
            'B' => 'Name (Deutsch)*',
            'C' => 'Name (Englisch)',
            'D' => 'Beschreibung',
            'E' => 'Sortierung',
        ],
        '03_Einheiten' => [
            'A' => 'Gruppe Techn. Name*',
            'B' => 'Gruppe Name (Deutsch)*',
            'C' => 'Einheit Techn. Name*',
            'D' => 'Kürzel*',
            'E' => 'Umrechnungsfaktor',
            'F' => 'Basiseinheit (Ja/Nein)',
        ],
        '04_Wertelisten' => [
            'A' => 'Liste Techn. Name*',
            'B' => 'Liste Name (Deutsch)*',
            'C' => 'Eintrag Techn. Name',
            'D' => 'Anzeigename (Deutsch)',
            'E' => 'Anzeigename (Englisch)',
            'F' => 'Sortierung',
        ],
        '05_Attribute' => [
            'A' => 'Technischer Name*',
            'B' => 'Name (Deutsch)*',
            'C' => 'Name (Englisch)',
            'D' => 'Beschreibung',
            'E' => 'Datentyp*',
            'F' => 'Attributgruppe',
            'G' => 'Werteliste',
            'H' => 'Einheitengruppe',
            'I' => 'Standard-Einheit',
            'J' => 'Vermehrbar (Ja/Nein)',
            'K' => 'Max. Vermehrungen',
            'L' => 'Übersetzbar (Ja/Nein)',
            'M' => 'Pflicht (Optional/Pflicht)',
            'N' => 'Eindeutig (Ja/Nein)',
            'O' => 'Suchbar (Ja/Nein)',
            'P' => 'Vererbbar (Ja/Nein)',
            'Q' => 'Übergeordnetes Attribut',
            'R' => 'Quellsystem',
            'S' => 'Sichten (kommasepariert)',
        ],
        '06_Hierarchien' => [
            'A' => 'Hierarchie*',
            'B' => 'Typ* (master/output)',
            'C' => 'Ebene 1',
            'D' => 'Ebene 2',
            'E' => 'Ebene 3',
            'F' => 'Ebene 4',
            'G' => 'Ebene 5',
            'H' => 'Ebene 6',
        ],
        '07_Hierarchie_Attribute' => [
            'A' => 'Hierarchie*',
            'B' => 'Knotenpfad*',
            'C' => 'Attribut*',
            'D' => 'Sammlungsname',
            'E' => 'Sammlungs-Sortierung',
            'F' => 'Attribut-Sortierung',
            'G' => 'Nicht vererben (Ja/Nein)',
        ],
        '08_Produkte' => [
            'A' => 'SKU*',
            'B' => 'Produktname*',
            'C' => 'Produktname (EN)',
            'D' => 'Produkttyp*',
            'E' => 'EAN',
            'F' => 'Status (draft/active/inactive)',
        ],
        '09_Produktwerte' => [
            'A' => 'SKU*',
            'B' => 'Attribut*',
            'C' => 'Wert*',
            'D' => 'Einheit',
            'E' => 'Sprache (de/en/...)',
            'F' => 'Index',
        ],
        '10_Varianten' => [
            'A' => 'Eltern-SKU*',
            'B' => 'Varianten-SKU*',
            'C' => 'Variantenname*',
            'D' => 'Variantenname (EN)',
            'E' => 'EAN',
            'F' => 'Status',
        ],
        '11_Produkt_Hierarchien' => [
            'A' => 'SKU*',
            'B' => 'Hierarchie*',
            'C' => 'Knotenpfad*',
        ],
        '12_Produktbeziehungen' => [
            'A' => 'Quell-SKU*',
            'B' => 'Ziel-SKU*',
            'C' => 'Beziehungstyp*',
            'D' => 'Sortierung',
        ],
        '13_Preise' => [
            'A' => 'SKU*',
            'B' => 'Preisart*',
            'C' => 'Betrag*',
            'D' => 'Währung* (EUR/USD/...)',
            'E' => 'Gültig ab',
            'F' => 'Gültig bis',
            'G' => 'Land (ISO 2)',
            'H' => 'Staffel von',
            'I' => 'Staffel bis',
        ],
        '14_Medien' => [
            'A' => 'SKU*',
            'B' => 'Dateiname*',
            'C' => 'Medientyp (image/document/video)',
            'D' => 'Verwendung (teaser/gallery/document)',
            'E' => 'Titel (Deutsch)',
            'F' => 'Titel (Englisch)',
            'G' => 'Alt-Text (Deutsch)',
            'H' => 'Sortierung',
            'I' => 'Primär (Ja/Nein)',
        ],
    ];

    /**
     * Erzeugt einen vollständigen Export aller PIM-Daten im Import-Template-Format.
     *
     * @param  string  $outputPath  Ziel-Dateipfad
     * @return string Pfad der erzeugten Datei
     */
    public function generate(string $outputPath): string
    {
        $spreadsheet = new Spreadsheet();

        // Default-Sheet entfernen
        $spreadsheet->removeSheetByIndex(0);

        $sheetIndex = 0;
        foreach (self::SHEET_HEADERS as $sheetName => $headers) {
            $worksheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($worksheet, $sheetIndex);
            $sheetIndex++;

            $this->writeHeaders($worksheet, $headers);
            $this->styleHeaders($worksheet, $headers);
            $this->autoSizeColumns($worksheet, $headers);
        }

        // Daten in die einzelnen Sheets schreiben
        $this->exportProdukttypen($spreadsheet->getSheetByName('01_Produkttypen'));
        $this->exportAttributgruppen($spreadsheet->getSheetByName('02_Attributgruppen'));
        $this->exportEinheiten($spreadsheet->getSheetByName('03_Einheiten'));
        $this->exportWertelisten($spreadsheet->getSheetByName('04_Wertelisten'));
        $this->exportAttribute($spreadsheet->getSheetByName('05_Attribute'));
        $this->exportHierarchien($spreadsheet->getSheetByName('06_Hierarchien'));
        $this->exportHierarchieAttribute($spreadsheet->getSheetByName('07_Hierarchie_Attribute'));
        $this->exportProdukte($spreadsheet->getSheetByName('08_Produkte'));
        $this->exportProduktwerte($spreadsheet->getSheetByName('09_Produktwerte'));
        $this->exportVarianten($spreadsheet->getSheetByName('10_Varianten'));
        $this->exportProduktHierarchien($spreadsheet->getSheetByName('11_Produkt_Hierarchien'));
        $this->exportProduktbeziehungen($spreadsheet->getSheetByName('12_Produktbeziehungen'));
        $this->exportPreise($spreadsheet->getSheetByName('13_Preise'));
        $this->exportMedien($spreadsheet->getSheetByName('14_Medien'));

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        return $outputPath;
    }

    // -------------------------------------------------------------------------
    // Sheet-Export-Methoden
    // -------------------------------------------------------------------------

    /**
     * 01_Produkttypen: ProductType → technical_name, name_de, name_en, description,
     *   has_variants→Ja/Nein, has_ean→Ja/Nein, has_prices→Ja/Nein, has_media→Ja/Nein
     */
    private function exportProdukttypen(Worksheet $sheet): void
    {
        $row = 2;

        ProductType::query()->orderBy('technical_name')->chunk(500, function ($types) use ($sheet, &$row) {
            foreach ($types as $type) {
                $sheet->setCellValue("A{$row}", $type->technical_name);
                $sheet->setCellValue("B{$row}", $type->name_de);
                $sheet->setCellValue("C{$row}", $type->name_en);
                $sheet->setCellValue("D{$row}", $type->description);
                $sheet->setCellValue("E{$row}", $this->boolToJaNein($type->has_variants));
                $sheet->setCellValue("F{$row}", $this->boolToJaNein($type->has_ean));
                $sheet->setCellValue("G{$row}", $this->boolToJaNein($type->has_prices));
                $sheet->setCellValue("H{$row}", $this->boolToJaNein($type->has_media));
                $row++;
            }
        });
    }

    /**
     * 02_Attributgruppen: AttributeType → technical_name, name_de, name_en, description, sort_order
     */
    private function exportAttributgruppen(Worksheet $sheet): void
    {
        $row = 2;

        AttributeType::query()->orderBy('sort_order')->chunk(500, function ($groups) use ($sheet, &$row) {
            foreach ($groups as $group) {
                $sheet->setCellValue("A{$row}", $group->technical_name);
                $sheet->setCellValue("B{$row}", $group->name_de);
                $sheet->setCellValue("C{$row}", $group->name_en);
                $sheet->setCellValue("D{$row}", $group->description);
                $sheet->setCellValue("E{$row}", $group->sort_order);
                $row++;
            }
        });
    }

    /**
     * 03_Einheiten: Unit with unitGroup → group.technical_name, group.name_de,
     *   unit.technical_name, unit.abbreviation, conversion_factor, is_base_unit→Ja/Nein
     */
    private function exportEinheiten(Worksheet $sheet): void
    {
        $row = 2;

        Unit::query()
            ->with('unitGroup')
            ->orderBy('unit_group_id')
            ->orderBy('technical_name')
            ->chunk(500, function ($units) use ($sheet, &$row) {
                foreach ($units as $unit) {
                    $sheet->setCellValue("A{$row}", $unit->unitGroup?->technical_name);
                    $sheet->setCellValue("B{$row}", $unit->unitGroup?->name_de);
                    $sheet->setCellValue("C{$row}", $unit->technical_name);
                    $sheet->setCellValue("D{$row}", $unit->abbreviation);
                    $sheet->setCellValue("E{$row}", $unit->conversion_factor);
                    $sheet->setCellValue("F{$row}", $this->boolToJaNein($unit->is_base_unit));
                    $row++;
                }
            });
    }

    /**
     * 04_Wertelisten: ValueList + ValueListEntry → list.technical_name, list.name_de,
     *   entry.technical_name, entry.display_value_de, entry.display_value_en, entry.sort_order
     */
    private function exportWertelisten(Worksheet $sheet): void
    {
        $row = 2;

        ValueList::query()
            ->with(['entries' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('technical_name')
            ->chunk(500, function ($lists) use ($sheet, &$row) {
                foreach ($lists as $list) {
                    if ($list->entries->isEmpty()) {
                        // Leere Werteliste trotzdem exportieren (nur Kopfdaten)
                        $sheet->setCellValue("A{$row}", $list->technical_name);
                        $sheet->setCellValue("B{$row}", $list->name_de);
                        $row++;
                    } else {
                        foreach ($list->entries as $entry) {
                            $sheet->setCellValue("A{$row}", $list->technical_name);
                            $sheet->setCellValue("B{$row}", $list->name_de);
                            $sheet->setCellValue("C{$row}", $entry->technical_name);
                            $sheet->setCellValue("D{$row}", $entry->display_value_de);
                            $sheet->setCellValue("E{$row}", $entry->display_value_en);
                            $sheet->setCellValue("F{$row}", $entry->sort_order);
                            $row++;
                        }
                    }
                }
            });
    }

    /**
     * 05_Attribute: Attribute with relations → technical_name, name_de, name_en, description_de,
     *   data_type, attributeType->technical_name, valueList->technical_name, unitGroup->technical_name,
     *   defaultUnit->technical_name, is_multipliable→Ja/Nein, max_multiplied, is_translatable→Ja/Nein,
     *   is_mandatory→Pflicht/Optional, is_unique→Ja/Nein, is_searchable→Ja/Nein,
     *   is_inheritable→Ja/Nein, parentAttribute->technical_name, source_system,
     *   views (comma-separated)
     */
    private function exportAttribute(Worksheet $sheet): void
    {
        $row = 2;

        Attribute::query()
            ->with([
                'attributeType',
                'valueList',
                'unitGroup',
                'defaultUnit',
                'parentAttribute',
                'attributeViews',
            ])
            ->orderBy('technical_name')
            ->chunk(500, function ($attributes) use ($sheet, &$row) {
                foreach ($attributes as $attribute) {
                    $views = $attribute->attributeViews
                        ->pluck('technical_name')
                        ->implode(',');

                    $sheet->setCellValue("A{$row}", $attribute->technical_name);
                    $sheet->setCellValue("B{$row}", $attribute->name_de);
                    $sheet->setCellValue("C{$row}", $attribute->name_en);
                    $sheet->setCellValue("D{$row}", $attribute->description_de);
                    $sheet->setCellValue("E{$row}", $attribute->data_type);
                    $sheet->setCellValue("F{$row}", $attribute->attributeType?->technical_name);
                    $sheet->setCellValue("G{$row}", $attribute->valueList?->technical_name);
                    $sheet->setCellValue("H{$row}", $attribute->unitGroup?->technical_name);
                    $sheet->setCellValue("I{$row}", $attribute->defaultUnit?->technical_name);
                    $sheet->setCellValue("J{$row}", $this->boolToJaNein($attribute->is_multipliable));
                    $sheet->setCellValue("K{$row}", $attribute->max_multiplied);
                    $sheet->setCellValue("L{$row}", $this->boolToJaNein($attribute->is_translatable));
                    $sheet->setCellValue("M{$row}", $attribute->is_mandatory ? 'Pflicht' : 'Optional');
                    $sheet->setCellValue("N{$row}", $this->boolToJaNein($attribute->is_unique));
                    $sheet->setCellValue("O{$row}", $this->boolToJaNein($attribute->is_searchable));
                    $sheet->setCellValue("P{$row}", $this->boolToJaNein($attribute->is_inheritable));
                    $sheet->setCellValue("Q{$row}", $attribute->parentAttribute?->technical_name);
                    $sheet->setCellValue("R{$row}", $attribute->source_system);
                    $sheet->setCellValue("S{$row}", $views);
                    $row++;
                }
            });
    }

    /**
     * 06_Hierarchien: Hierarchy + HierarchyNode → Für jeden Knoten:
     *   hierarchy.technical_name, hierarchy.hierarchy_type, level_1..level_6 (aus Knotennamen)
     */
    private function exportHierarchien(Worksheet $sheet): void
    {
        $row = 2;
        $levelColumns = ['C', 'D', 'E', 'F', 'G', 'H'];

        Hierarchy::query()
            ->with(['nodes' => fn ($q) => $q->orderBy('depth')->orderBy('sort_order')])
            ->orderBy('technical_name')
            ->chunk(500, function ($hierarchies) use ($sheet, &$row, $levelColumns) {
                foreach ($hierarchies as $hierarchy) {
                    $allNodes = $hierarchy->nodes;

                    foreach ($allNodes as $node) {
                        // Root-Knoten (depth 0) überspringen – werden nicht als Zeile exportiert
                        if ($node->depth === 0) {
                            continue;
                        }

                        $sheet->setCellValue("A{$row}", $hierarchy->technical_name);
                        $sheet->setCellValue("B{$row}", $hierarchy->hierarchy_type);

                        // Ebenen-Namen über die Parent-Kette aufbauen (statt aus dem Pfad)
                        $levelNames = $this->buildLevelNames($node, $allNodes);
                        foreach ($levelNames as $index => $name) {
                            if ($index < count($levelColumns)) {
                                $col = $levelColumns[$index];
                                $sheet->setCellValue("{$col}{$row}", $name);
                            }
                        }

                        $row++;
                    }
                }
            });
    }

    /**
     * 07_Hierarchie_Attribute: HierarchyNodeAttributeAssignment →
     *   hierarchy.technical_name, node.readablePath, attribute.technical_name,
     *   collection_name, collection_sort, attribute_sort, dont_inherit→Ja/Nein
     */
    private function exportHierarchieAttribute(Worksheet $sheet): void
    {
        $row = 2;

        // Alle Knoten vorladen für buildReadablePath
        $allNodesByHierarchy = $this->loadAllNodesByHierarchy();

        HierarchyNodeAttributeAssignment::query()
            ->with([
                'hierarchyNode.hierarchy',
                'attribute',
            ])
            ->chunk(500, function ($assignments) use ($sheet, &$row, $allNodesByHierarchy) {
                foreach ($assignments as $assignment) {
                    $node = $assignment->hierarchyNode;
                    if (!$node) {
                        continue;
                    }

                    $hierarchyId = $node->hierarchy_id;
                    $allNodes = $allNodesByHierarchy[$hierarchyId] ?? collect();
                    $readablePath = $this->buildReadablePath($node, $allNodes);

                    $sheet->setCellValue("A{$row}", $node->hierarchy?->technical_name);
                    $sheet->setCellValue("B{$row}", $readablePath);
                    $sheet->setCellValue("C{$row}", $assignment->attribute?->technical_name);
                    $sheet->setCellValue("D{$row}", $assignment->collection_name);
                    $sheet->setCellValue("E{$row}", $assignment->collection_sort);
                    $sheet->setCellValue("F{$row}", $assignment->attribute_sort);
                    $sheet->setCellValue("G{$row}", $this->boolToJaNein($assignment->dont_inherit));
                    $row++;
                }
            });
    }

    /**
     * 08_Produkte: Product where product_type_ref='product' →
     *   sku, name, name_en, productType.technical_name, ean, status
     */
    private function exportProdukte(Worksheet $sheet): void
    {
        $row = 2;

        Product::query()
            ->where('product_type_ref', 'product')
            ->with('productType')
            ->orderBy('sku')
            ->chunk(500, function ($products) use ($sheet, &$row) {
                foreach ($products as $product) {
                    $sheet->setCellValue("A{$row}", $product->sku);
                    $sheet->setCellValue("B{$row}", $product->name);
                    $sheet->setCellValue("C{$row}", $this->getProductNameEn($product));
                    $sheet->setCellValue("D{$row}", $product->productType?->technical_name);
                    $sheet->setCellValue("E{$row}", $product->ean);
                    $sheet->setCellValue("F{$row}", $product->status);
                    $row++;
                }
            });
    }

    /**
     * 09_Produktwerte: ProductAttributeValue →
     *   product.sku, attribute.technical_name, value, unit.abbreviation, language, multiplied_index
     */
    private function exportProduktwerte(Worksheet $sheet): void
    {
        $row = 2;

        ProductAttributeValue::query()
            ->with([
                'product',
                'attribute',
                'unit',
                'valueListEntry',
            ])
            ->chunk(500, function ($values) use ($sheet, &$row) {
                foreach ($values as $pav) {
                    if (!$pav->product || !$pav->attribute) {
                        continue;
                    }

                    $value = $this->resolveAttributeValue($pav);

                    $sheet->setCellValue("A{$row}", $pav->product->sku);
                    $sheet->setCellValue("B{$row}", $pav->attribute->technical_name);
                    $sheet->setCellValue("C{$row}", $value);
                    $sheet->setCellValue("D{$row}", $pav->unit?->abbreviation);
                    $sheet->setCellValue("E{$row}", $pav->language);
                    $sheet->setCellValue("F{$row}", $pav->multiplied_index);
                    $row++;
                }
            });
    }

    /**
     * 10_Varianten: Product where product_type_ref='variant' →
     *   parent.sku, variant.sku, variant.name, name_en, ean, status
     */
    private function exportVarianten(Worksheet $sheet): void
    {
        $row = 2;

        Product::query()
            ->where('product_type_ref', 'variant')
            ->with('parentProduct')
            ->orderBy('sku')
            ->chunk(500, function ($variants) use ($sheet, &$row) {
                foreach ($variants as $variant) {
                    $sheet->setCellValue("A{$row}", $variant->parentProduct?->sku);
                    $sheet->setCellValue("B{$row}", $variant->sku);
                    $sheet->setCellValue("C{$row}", $variant->name);
                    $sheet->setCellValue("D{$row}", $this->getProductNameEn($variant));
                    $sheet->setCellValue("E{$row}", $variant->ean);
                    $sheet->setCellValue("F{$row}", $variant->status);
                    $row++;
                }
            });
    }

    /**
     * 11_Produkt_Hierarchien:
     *   - Product.master_hierarchy_node_id → product.sku, hierarchy.technical_name, readablePath
     *   - OutputHierarchyProductAssignment → product.sku, hierarchy.technical_name, readablePath
     */
    private function exportProduktHierarchien(Worksheet $sheet): void
    {
        $row = 2;

        // Alle Knoten vorladen für buildReadablePath
        $allNodesByHierarchy = $this->loadAllNodesByHierarchy();

        // Master-Hierarchie-Zuordnungen (über master_hierarchy_node_id am Produkt)
        Product::query()
            ->whereNotNull('master_hierarchy_node_id')
            ->with('masterHierarchyNode.hierarchy')
            ->orderBy('sku')
            ->chunk(500, function ($products) use ($sheet, &$row, $allNodesByHierarchy) {
                foreach ($products as $product) {
                    $node = $product->masterHierarchyNode;
                    if (!$node) {
                        continue;
                    }

                    $hierarchyId = $node->hierarchy_id;
                    $allNodes = $allNodesByHierarchy[$hierarchyId] ?? collect();
                    $readablePath = $this->buildReadablePath($node, $allNodes);

                    $sheet->setCellValue("A{$row}", $product->sku);
                    $sheet->setCellValue("B{$row}", $node->hierarchy?->technical_name);
                    $sheet->setCellValue("C{$row}", $readablePath);
                    $row++;
                }
            });

        // Output-Hierarchie-Zuordnungen
        OutputHierarchyProductAssignment::query()
            ->with([
                'product',
                'hierarchyNode.hierarchy',
            ])
            ->chunk(500, function ($assignments) use ($sheet, &$row, $allNodesByHierarchy) {
                foreach ($assignments as $assignment) {
                    if (!$assignment->product || !$assignment->hierarchyNode) {
                        continue;
                    }

                    $node = $assignment->hierarchyNode;
                    $hierarchyId = $node->hierarchy_id;
                    $allNodes = $allNodesByHierarchy[$hierarchyId] ?? collect();
                    $readablePath = $this->buildReadablePath($node, $allNodes);

                    $sheet->setCellValue("A{$row}", $assignment->product->sku);
                    $sheet->setCellValue("B{$row}", $node->hierarchy?->technical_name);
                    $sheet->setCellValue("C{$row}", $readablePath);
                    $row++;
                }
            });
    }

    /**
     * 12_Produktbeziehungen: ProductRelation →
     *   source.sku, target.sku, relationType.technical_name, sort_order
     */
    private function exportProduktbeziehungen(Worksheet $sheet): void
    {
        $row = 2;

        ProductRelation::query()
            ->with([
                'sourceProduct',
                'targetProduct',
                'relationType',
            ])
            ->chunk(500, function ($relations) use ($sheet, &$row) {
                foreach ($relations as $relation) {
                    if (!$relation->sourceProduct || !$relation->targetProduct) {
                        continue;
                    }

                    $sheet->setCellValue("A{$row}", $relation->sourceProduct->sku);
                    $sheet->setCellValue("B{$row}", $relation->targetProduct->sku);
                    $sheet->setCellValue("C{$row}", $relation->relationType?->technical_name);
                    $sheet->setCellValue("D{$row}", $relation->sort_order);
                    $row++;
                }
            });
    }

    /**
     * 13_Preise: ProductPrice →
     *   product.sku, priceType.technical_name, amount, currency,
     *   valid_from, valid_to, country, scale_from, scale_to
     */
    private function exportPreise(Worksheet $sheet): void
    {
        $row = 2;

        ProductPrice::query()
            ->with([
                'product',
                'priceType',
            ])
            ->chunk(500, function ($prices) use ($sheet, &$row) {
                foreach ($prices as $price) {
                    if (!$price->product) {
                        continue;
                    }

                    $sheet->setCellValue("A{$row}", $price->product->sku);
                    $sheet->setCellValue("B{$row}", $price->priceType?->technical_name);
                    $sheet->setCellValue("C{$row}", $price->amount);
                    $sheet->setCellValue("D{$row}", $price->currency);
                    $sheet->setCellValue("E{$row}", $price->valid_from?->format('Y-m-d'));
                    $sheet->setCellValue("F{$row}", $price->valid_to?->format('Y-m-d'));
                    $sheet->setCellValue("G{$row}", $price->country);
                    $sheet->setCellValue("H{$row}", $price->scale_from);
                    $sheet->setCellValue("I{$row}", $price->scale_to);
                    $row++;
                }
            });
    }

    /**
     * 14_Medien: ProductMediaAssignment + Media →
     *   product.sku, media.file_name, media.media_type, assignment.usage_type,
     *   media.title_de, media.title_en, media.alt_text_de, assignment.sort_order,
     *   assignment.is_primary→Ja/Nein
     */
    private function exportMedien(Worksheet $sheet): void
    {
        $row = 2;

        ProductMediaAssignment::query()
            ->with([
                'product',
                'media',
                'usageType',
            ])
            ->chunk(500, function ($assignments) use ($sheet, &$row) {
                foreach ($assignments as $assignment) {
                    if (!$assignment->product || !$assignment->media) {
                        continue;
                    }

                    $media = $assignment->media;

                    $sheet->setCellValue("A{$row}", $assignment->product->sku);
                    $sheet->setCellValue("B{$row}", $media->file_name);
                    $sheet->setCellValue("C{$row}", $media->media_type);
                    $sheet->setCellValue("D{$row}", $assignment->usageType?->technical_name);
                    $sheet->setCellValue("E{$row}", $media->title_de);
                    $sheet->setCellValue("F{$row}", $media->title_en);
                    $sheet->setCellValue("G{$row}", $media->alt_text_de);
                    $sheet->setCellValue("H{$row}", $assignment->sort_order);
                    $sheet->setCellValue("I{$row}", $this->boolToJaNein($assignment->is_primary));
                    $row++;
                }
            });
    }

    // -------------------------------------------------------------------------
    // Header-Formatierung (identisch zu TemplateGenerator)
    // -------------------------------------------------------------------------

    /**
     * Schreibt Header in Zeile 1.
     */
    private function writeHeaders(Worksheet $worksheet, array $headers): void
    {
        foreach ($headers as $column => $headerText) {
            $worksheet->setCellValue($column . '1', $headerText);
        }
    }

    /**
     * Formatiert die Header-Zeile mit blauem Hintergrund und weißer Schrift.
     * Pflichtfelder (mit *) erhalten einen gelben Hintergrund.
     */
    private function styleHeaders(Worksheet $worksheet, array $headers): void
    {
        $lastColumn = array_key_last($headers);
        $range = 'A1:' . $lastColumn . '1';

        $worksheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2B5797'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF999999'],
                ],
            ],
        ]);

        $worksheet->getRowDimension(1)->setRowHeight(30);

        // Pflichtfelder (mit *) in Gelb markieren
        foreach ($headers as $column => $headerText) {
            if (str_contains($headerText, '*')) {
                $worksheet->getStyle($column . '1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD4A017');
            }
        }
    }

    /**
     * Passt die Spaltenbreiten an.
     */
    private function autoSizeColumns(Worksheet $worksheet, array $headers): void
    {
        foreach (array_keys($headers) as $column) {
            $worksheet->getColumnDimension($column)->setWidth(22);
        }

        // Erste Spalte etwas breiter
        $worksheet->getColumnDimension('A')->setWidth(28);
    }

    // -------------------------------------------------------------------------
    // Hilfsmethoden
    // -------------------------------------------------------------------------

    /**
     * Konvertiert einen booleschen Wert in "Ja" oder "Nein".
     */
    private function boolToJaNein(?bool $value): string
    {
        if ($value === null) {
            return 'Nein';
        }

        return $value ? 'Ja' : 'Nein';
    }

    /**
     * Ermittelt den konkreten Wert eines ProductAttributeValue
     * aus den verschiedenen Wertspalten.
     * Bei Selection-Attributen wird der technical_name des ValueListEntry verwendet.
     */
    private function resolveAttributeValue(ProductAttributeValue $pav): ?string
    {
        // Selection: technical_name des ValueListEntry verwenden
        if ($pav->value_selection_id !== null) {
            if ($pav->value_string !== null && $pav->value_string !== '') {
                return $pav->value_string;
            }
            // Fallback: ValueListEntry direkt nachschlagen
            $entry = $pav->valueListEntry ?? ValueListEntry::find($pav->value_selection_id);
            return $entry?->technical_name;
        }

        if ($pav->value_string !== null && $pav->value_string !== '') {
            return $pav->value_string;
        }

        if ($pav->value_number !== null) {
            return (string) $pav->value_number;
        }

        if ($pav->value_date !== null) {
            return $pav->value_date->format('Y-m-d');
        }

        if ($pav->value_flag !== null) {
            return $pav->value_flag ? 'Ja' : 'Nein';
        }

        return null;
    }

    /**
     * Versucht den englischen Produktnamen aus den Attributwerten zu ermitteln.
     * Produkte speichern name_en nicht direkt – der englische Name kann als
     * Attributwert (language=en) mit dem passenden Namensattribut vorliegen.
     * Falls nicht verfügbar, wird null zurückgegeben.
     */
    private function getProductNameEn(Product $product): ?string
    {
        return ProductAttributeValue::query()
            ->where('product_id', $product->id)
            ->where('language', 'en')
            ->whereHas('attribute', fn ($q) => $q->where('technical_name', 'name'))
            ->value('value_string');
    }

    // -------------------------------------------------------------------------
    // Hierarchie-Pfad-Hilfsmethoden
    // -------------------------------------------------------------------------

    /**
     * Lädt alle HierarchyNodes gruppiert nach hierarchy_id.
     *
     * @return array<string, Collection> hierarchy_id → Collection<HierarchyNode>
     */
    private function loadAllNodesByHierarchy(): array
    {
        return HierarchyNode::all()
            ->groupBy('hierarchy_id')
            ->all();
    }

    /**
     * Baut einen lesbaren Pfad aus den name_de-Werten der Knotenvorfahren auf.
     * Ergebnis: "/Elektronik/Audio/Kopfhörer/" statt "/{uuid}/{uuid}/{uuid}/"
     *
     * @param HierarchyNode $node     Der Knoten
     * @param Collection    $allNodes Alle Knoten dieser Hierarchie
     */
    private function buildReadablePath(HierarchyNode $node, Collection $allNodes): string
    {
        $segments = [];
        $current = $node;

        while ($current && $current->depth > 0) {
            array_unshift($segments, $current->name_de);
            $parentId = $current->parent_node_id;
            $current = $parentId ? $allNodes->firstWhere('id', $parentId) : null;
        }

        if (empty($segments)) {
            return '/';
        }

        return '/' . implode('/', $segments) . '/';
    }

    /**
     * Baut die Ebenen-Namen (level_1, level_2, ...) eines Knotens über die Parent-Kette auf.
     *
     * @param HierarchyNode $node     Der Knoten
     * @param Collection    $allNodes Alle Knoten dieser Hierarchie
     * @return string[] Array der Ebenen-Namen (Index 0 = level_1)
     */
    private function buildLevelNames(HierarchyNode $node, Collection $allNodes): array
    {
        $names = [];
        $current = $node;

        while ($current && $current->depth > 0) {
            array_unshift($names, $current->name_de);
            $parentId = $current->parent_node_id;
            $current = $parentId ? $allNodes->firstWhere('id', $parentId) : null;
        }

        return $names;
    }
}
