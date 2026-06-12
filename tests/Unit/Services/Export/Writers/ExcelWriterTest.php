<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Export\Writers;

use App\Services\Export\Writers\ExcelWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Unit-Tests für den ExcelWriter (XLSX via PhpSpreadsheet).
 */
class ExcelWriterTest extends TestCase
{
    private ExcelWriter $writer;

    /** @var string[] Aufzuräumende Temp-Dateien */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new ExcelWriter();
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** Response-Inhalt in eine Temp-Datei unter /tmp schreiben. */
    private function captureToFile(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'xlsxtest_') . '.xlsx';
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        return $path;
    }

    public function test_ausgabe_ist_valide_xlsx_struktur(): void
    {
        $data = ['products' => [
            ['sku' => 'SKU-001', 'name' => 'Bohrer', 'status' => 'active', 'ean' => '4012345678901', 'product_type' => 'Werkzeug'],
        ]];

        $response = $this->writer->write($data, 'excel-export');
        $path = $this->captureToFile($response);

        // ZIP-Magic-Bytes (XLSX = ZIP-Container)
        $this->assertStringStartsWith("PK\x03\x04", (string) file_get_contents($path));

        // Pflicht-Einträge des Office-Open-XML-Formats
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $zip->close();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('excel-export.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_produktdaten_lassen_sich_mit_phpspreadsheet_zurucklesen(): void
    {
        $data = ['products' => [
            ['sku' => 'SKU-002', 'name' => 'Akkuschrauber', 'status' => 'active', 'ean' => '4012345678902', 'product_type' => 'Werkzeug'],
            ['sku' => 'SKU-003', 'name' => 'Hammer', 'status' => 'draft', 'ean' => '', 'product_type' => ''],
        ]];

        $path = $this->captureToFile($this->writer->write($data, 'export'));
        $spreadsheet = IOFactory::load($path);

        // Nur Produkte-Daten → Default-Sheet entfernt, ein Sheet übrig
        $this->assertSame(1, $spreadsheet->getSheetCount());

        $sheet = $spreadsheet->getSheetByName('Produkte');
        $this->assertNotNull($sheet);
        $this->assertSame('SKU', $sheet->getCell('A1')->getValue());
        $this->assertSame('Name', $sheet->getCell('B1')->getValue());
        $this->assertSame('SKU-002', $sheet->getCell('A2')->getValue());
        $this->assertSame('Akkuschrauber', $sheet->getCell('B2')->getValue());
        $this->assertSame('SKU-003', $sheet->getCell('A3')->getValue());
        $this->assertSame('draft', $sheet->getCell('C3')->getValue());
    }

    public function test_attribut_und_preis_sheets_werden_bei_bedarf_erstellt(): void
    {
        $data = ['products' => [
            [
                'sku' => 'SKU-004',
                'name' => 'Säge',
                'status' => 'active',
                'ean' => '',
                'product_type' => '',
                'attributes' => [
                    ['attribute' => 'laenge-mm', 'attribute_name' => 'Länge', 'value' => '500', 'language' => 'de'],
                ],
                'prices' => [
                    ['price_type' => 'list_price', 'amount' => 49.99, 'currency' => 'EUR', 'scale_from' => null, 'valid_from' => null, 'valid_to' => null],
                ],
            ],
        ]];

        $path = $this->captureToFile($this->writer->write($data, 'export'));
        $spreadsheet = IOFactory::load($path);

        $this->assertSame(3, $spreadsheet->getSheetCount());

        $attrSheet = $spreadsheet->getSheetByName('Attributwerte');
        $this->assertNotNull($attrSheet);
        $this->assertSame('SKU-004', $attrSheet->getCell('A2')->getValue());
        $this->assertSame('laenge-mm', $attrSheet->getCell('B2')->getValue());
        $this->assertSame('Länge', $attrSheet->getCell('C2')->getValue());
        // PhpSpreadsheet liest numerische Strings als Zahl zurück
        $this->assertEquals(500, $attrSheet->getCell('D2')->getValue());

        $priceSheet = $spreadsheet->getSheetByName('Preise');
        $this->assertNotNull($priceSheet);
        $this->assertSame('list_price', $priceSheet->getCell('B2')->getValue());
        $this->assertEqualsWithDelta(49.99, (float) $priceSheet->getCell('C2')->getValue(), 0.001);
        $this->assertSame('EUR', $priceSheet->getCell('D2')->getValue());
    }

    public function test_umlaute_und_sonderzeichen_bleiben_erhalten(): void
    {
        $data = ['products' => [
            ['sku' => 'Ü-005', 'name' => 'Größenmaß <&> "äöüß"', 'status' => 'active', 'ean' => '', 'product_type' => 'Zubehör'],
        ]];

        $path = $this->captureToFile($this->writer->write($data, 'export'));
        $sheet = IOFactory::load($path)->getSheetByName('Produkte');

        $this->assertSame('Ü-005', $sheet->getCell('A2')->getValue());
        $this->assertSame('Größenmaß <&> "äöüß"', $sheet->getCell('B2')->getValue());
        $this->assertSame('Zubehör', $sheet->getCell('E2')->getValue());
    }

    public function test_leere_daten_ergeben_nur_produkte_sheet_mit_header(): void
    {
        $path = $this->captureToFile($this->writer->write(['products' => []], 'leer'));
        $spreadsheet = IOFactory::load($path);

        $this->assertSame(1, $spreadsheet->getSheetCount());
        $sheet = $spreadsheet->getSheetByName('Produkte');
        $this->assertNotNull($sheet);
        $this->assertSame('SKU', $sheet->getCell('A1')->getValue());
        $this->assertNull($sheet->getCell('A2')->getValue());
    }
}
