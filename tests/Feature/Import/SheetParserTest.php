<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Services\Import\SheetParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SheetParserTest extends TestCase
{
    private SheetParser $parser;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SheetParser();
        $this->tempDir = storage_path('app/test-imports');
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Temp-Dateien aufräumen
        array_map('unlink', glob($this->tempDir . '/*.xlsx'));
        parent::tearDown();
    }

    public function test_parses_attribute_sheet(): void
    {
        $file = $this->createTestExcel([
            '05_Attribute' => [
                ['Technischer Name*', 'Name (Deutsch)*', 'Name (Englisch)', 'Beschreibung', 'Datentyp*'],
                ['product-weight', 'Gewicht', 'Weight', 'Das Gewicht', 'Number'],
                ['product-color', 'Farbe', 'Color', null, 'Selection'],
            ],
        ]);

        $result = $this->parser->parse($file);

        $this->assertContains('05_Attribute', $result->sheetsFound);
        $this->assertCount(2, $result->getSheetData('05_Attribute'));

        $firstRow = $result->getSheetData('05_Attribute')[2]; // Zeile 2
        $this->assertEquals('product-weight', $firstRow['technical_name']);
        $this->assertEquals('Gewicht', $firstRow['name_de']);
        $this->assertEquals('Weight', $firstRow['name_en']);
        $this->assertEquals('Number', $firstRow['data_type']);
        $this->assertEquals(2, $firstRow['_row']);
    }

    public function test_parses_product_sheet(): void
    {
        $file = $this->createTestExcel([
            '08_Produkte' => [
                ['SKU*', 'Produktname*', 'Produktname (EN)', 'Produkttyp*', 'EAN', 'Status'],
                ['SKU-001', 'Hydraulikpumpe', 'Hydraulic Pump', 'physical_product', '4012345678901', 'active'],
                ['SKU-002', 'Dichtungsring', 'Seal Ring', 'physical_product', null, 'draft'],
            ],
        ]);

        $result = $this->parser->parse($file);

        $this->assertContains('08_Produkte', $result->sheetsFound);
        $rows = $result->getSheetData('08_Produkte');
        $this->assertCount(2, $rows);

        $this->assertEquals('SKU-001', $rows[2]['sku']);
        $this->assertEquals('Hydraulikpumpe', $rows[2]['name']);
        $this->assertEquals('physical_product', $rows[2]['product_type']);
        $this->assertEquals('active', $rows[2]['status']);
    }

    public function test_parses_product_values_sheet(): void
    {
        $file = $this->createTestExcel([
            '09_Produktwerte' => [
                ['SKU*', 'Attribut*', 'Wert*', 'Einheit', 'Sprache', 'Index'],
                ['SKU-001', 'product-weight', '4.5', 'kg', null, null],
                ['SKU-001', 'Beschreibung', 'Eine Pumpe', null, 'de', null],
                ['SKU-001', 'Beschreibung', 'A pump', null, 'en', null],
            ],
        ]);

        $result = $this->parser->parse($file);

        $rows = $result->getSheetData('09_Produktwerte');
        $this->assertCount(3, $rows);
        $this->assertEquals('product-weight', $rows[2]['attribute']);
        $this->assertEquals('4.5', $rows[2]['value']);
        $this->assertEquals('kg', $rows[2]['unit']);
    }

    public function test_parses_multiple_sheets(): void
    {
        $file = $this->createTestExcel([
            '05_Attribute' => [
                ['Technischer Name*', 'Name (Deutsch)*', 'Name (Englisch)', 'Beschreibung', 'Datentyp*'],
                ['weight', 'Gewicht', 'Weight', null, 'Number'],
            ],
            '08_Produkte' => [
                ['SKU*', 'Produktname*', 'Produktname (EN)', 'Produkttyp*', 'EAN', 'Status'],
                ['SKU-001', 'Test', null, 'physical_product', null, 'draft'],
            ],
        ]);

        $result = $this->parser->parse($file);

        $this->assertCount(2, $result->sheetsFound);
        $this->assertTrue($result->hasSheet('05_Attribute'));
        $this->assertTrue($result->hasSheet('08_Produkte'));
        $this->assertEquals(2, $result->totalRows());
    }

    public function test_skips_empty_rows(): void
    {
        $file = $this->createTestExcel([
            '08_Produkte' => [
                ['SKU*', 'Produktname*', 'Produktname (EN)', 'Produkttyp*', 'EAN', 'Status'],
                ['SKU-001', 'Test', null, 'physical_product', null, null],
                [null, null, null, null, null, null], // Leere Zeile
                ['SKU-002', 'Test2', null, 'physical_product', null, null],
            ],
        ]);

        $result = $this->parser->parse($file);

        $rows = $result->getSheetData('08_Produkte');
        $this->assertCount(2, $rows);
        $this->assertArrayHasKey(2, $rows);
        $this->assertArrayHasKey(4, $rows); // Zeile 4 (nach Leerzeile)
    }

    public function test_ignores_unknown_sheets(): void
    {
        $file = $this->createTestExcel([
            'UnknownSheet' => [
                ['Col A', 'Col B'],
                ['val1', 'val2'],
            ],
        ]);

        $result = $this->parser->parse($file);

        $this->assertEmpty($result->sheetsFound);
    }

    public function test_total_rows_counts_across_sheets(): void
    {
        $file = $this->createTestExcel([
            '05_Attribute' => [
                ['Technischer Name*', 'Name (Deutsch)*', 'Name (Englisch)', 'Beschreibung', 'Datentyp*'],
                ['w1', 'N1', null, null, 'String'],
                ['w2', 'N2', null, null, 'Number'],
                ['w3', 'N3', null, null, 'Float'],
            ],
            '08_Produkte' => [
                ['SKU*', 'Produktname*', 'Produktname (EN)', 'Produkttyp*', 'EAN', 'Status'],
                ['SKU-1', 'P1', null, 'physical_product', null, null],
                ['SKU-2', 'P2', null, 'physical_product', null, null],
            ],
        ]);

        $result = $this->parser->parse($file);
        $this->assertEquals(5, $result->totalRows());
    }

    public function test_sheet_definitions_available(): void
    {
        $definitions = SheetParser::getSheetDefinitions();

        $this->assertCount(14, $definitions);
        $this->assertArrayHasKey('01_Produkttypen', $definitions);
        $this->assertArrayHasKey('14_Medien', $definitions);
    }

    // ──────────────────────────────────────────────
    //  Helper
    // ──────────────────────────────────────────────

    /**
     * Erzeugt eine Test-Excel-Datei.
     *
     * @param array<string, array<array>> $sheets  Sheet-Name → Zeilen (inkl. Header)
     * @return string Dateipfad
     */
    private function createTestExcel(array $sheets): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $index = 0;
        foreach ($sheets as $name => $rows) {
            $worksheet = $spreadsheet->createSheet($index);
            $worksheet->setTitle($name);

            foreach ($rows as $rowIdx => $rowData) {
                $col = 'A';
                foreach ($rowData as $cellValue) {
                    $worksheet->setCellValue($col . ($rowIdx + 1), $cellValue);
                    $col++;
                }
            }
            $index++;
        }

        $path = $this->tempDir . '/test_' . uniqid() . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }
}
