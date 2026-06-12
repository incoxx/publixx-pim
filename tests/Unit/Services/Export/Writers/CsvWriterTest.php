<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Export\Writers;

use App\Services\Export\Writers\CsvWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Unit-Tests für den CsvWriter (Einzeldatei und ZIP-Variante).
 */
class CsvWriterTest extends TestCase
{
    private CsvWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new CsvWriter();
    }

    /** Streamed-Response-Inhalt als String einsammeln. */
    private function captureContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_einfache_produktliste_wird_als_csv_geschrieben(): void
    {
        $data = ['products' => [
            ['sku' => 'SKU-001', 'name' => 'Bohrer', 'status' => 'active', 'ean' => '4012345678901', 'product_type' => 'Werkzeug'],
        ]];

        $response = $this->writer->write($data, 'test-export');
        $content = $this->captureContent($response);

        // UTF-8 BOM am Anfang
        $this->assertStringStartsWith(chr(0xEF) . chr(0xBB) . chr(0xBF), $content);
        // Header und Datenzeile mit Semikolon-Trenner
        $this->assertStringContainsString('SKU;Name;Status;EAN;Produkttyp', $content);
        $this->assertStringContainsString('SKU-001;Bohrer;active;4012345678901;Werkzeug', $content);

        $this->assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('test-export.csv', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_sonderzeichen_werden_per_csv_quoting_escaped(): void
    {
        $data = ['products' => [
            ['sku' => 'SKU;002', 'name' => 'Bohrer "Profi"' . "\n" . 'Zeile 2', 'status' => 'active', 'ean' => '', 'product_type' => ''],
        ]];

        $content = $this->captureContent($this->writer->write($data, 'export'));

        // Semikolon im Feld → Feld muss gequotet sein
        $this->assertStringContainsString('"SKU;002"', $content);
        // Anführungszeichen werden verdoppelt
        $this->assertStringContainsString('""Profi""', $content);

        // Zurücklesen: str_getcsv liefert die Originalwerte
        $lines = explode("\n", substr($content, 3)); // BOM abschneiden
        $row = str_getcsv($lines[1] . "\n" . $lines[2], ';', '"', '\\');
        $this->assertSame('SKU;002', $row[0]);
        $this->assertSame('Bohrer "Profi"' . "\n" . 'Zeile 2', $row[1]);
    }

    public function test_umlaute_bleiben_erhalten(): void
    {
        $data = ['products' => [
            ['sku' => 'SKU-003', 'name' => 'Übergrößen-Schraubendreher äöüß', 'status' => 'active', 'ean' => '', 'product_type' => 'Zubehör'],
        ]];

        $content = $this->captureContent($this->writer->write($data, 'export'));

        $this->assertStringContainsString('Übergrößen-Schraubendreher äöüß', $content);
        $this->assertStringContainsString('Zubehör', $content);
    }

    public function test_formel_injection_wird_neutralisiert(): void
    {
        // OWASP CSV Injection: führende =, +, @ dürfen in Excel/Calc
        // nicht als Formel ausgeführt werden → Apostroph-Prefix
        $data = ['products' => [
            ['sku' => '=cmd|/c calc', 'name' => '+SUM(A1:A9)', 'status' => '@active', 'ean' => '-2+3'],
            ['sku' => 'SKU-OK', 'name' => 'Normal', 'status' => 'active', 'ean' => '-5'],
        ]];

        $content = $this->captureContent($this->writer->write($data, 'export'));

        $this->assertStringContainsString("'=cmd|/c calc", $content);
        $this->assertStringContainsString("'+SUM(A1:A9)", $content);
        $this->assertStringContainsString("'@active", $content);
        $this->assertStringContainsString("'-2+3", $content);
        // Echte Zahlen bleiben unverändert (kein Apostroph vor -5)
        $this->assertStringContainsString(';-5', $content);
        $this->assertStringNotContainsString("'-5", $content);
        $this->assertStringNotContainsString("'SKU-OK", $content);
    }

    public function test_leere_produktliste_liefert_nur_header(): void
    {
        $content = $this->captureContent($this->writer->write(['products' => []], 'leer'));

        $lines = array_filter(explode("\n", trim(substr($content, 3))));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('SKU;Name;Status;EAN;Produkttyp', $lines[0]);
    }

    public function test_produkte_mit_attributen_und_preisen_werden_als_zip_geschrieben(): void
    {
        $data = ['products' => [
            [
                'sku' => 'SKU-004',
                'name' => 'Akkuschrauber',
                'status' => 'active',
                'ean' => '4012345678902',
                'product_type' => 'Werkzeug',
                'attributes' => [
                    ['attribute' => 'gewicht', 'attribute_name' => 'Gewicht', 'value' => '1.8', 'language' => 'de'],
                ],
                'prices' => [
                    ['price_type' => 'list_price', 'amount' => 189.99, 'currency' => 'EUR', 'scale_from' => null, 'valid_from' => null, 'valid_to' => null],
                ],
            ],
        ]];

        $response = $this->writer->write($data, 'multi-export');
        $content = $this->captureContent($response);

        // ZIP-Magic-Bytes
        $this->assertStringStartsWith("PK\x03\x04", $content);
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('multi-export.zip', (string) $response->headers->get('Content-Disposition'));

        // ZIP entpacken und Einträge prüfen
        $tmp = tempnam(sys_get_temp_dir(), 'csvtest_') . '.zip';
        file_put_contents($tmp, $content);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertNotFalse($zip->locateName('produkte.csv'));
        $this->assertNotFalse($zip->locateName('attributwerte.csv'));
        $this->assertNotFalse($zip->locateName('preise.csv'));
        // Keine Beziehungen/Medien in den Daten → keine Dateien dafür
        $this->assertFalse($zip->locateName('beziehungen.csv'));
        $this->assertFalse($zip->locateName('medien.csv'));

        $attrCsv = $zip->getFromName('attributwerte.csv');
        $this->assertStringContainsString('SKU-004;gewicht;Gewicht;1.8;de', $attrCsv);

        $priceCsv = $zip->getFromName('preise.csv');
        $this->assertStringContainsString('SKU-004;list_price;189.99;EUR', $priceCsv);

        $zip->close();
        @unlink($tmp);
    }
}
