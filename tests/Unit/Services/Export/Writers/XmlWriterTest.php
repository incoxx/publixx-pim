<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Export\Writers;

use App\Services\Export\Writers\XmlWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Unit-Tests für den XmlWriter.
 *
 * Regression: Die Klasse war wegen `use XMLWriter;` (case-insensitive
 * Kollision mit dem Klassennamen) nicht ladbar — jeder XML-Export
 * crashte mit einem Fatal Error beim Autoload.
 */
class XmlWriterTest extends TestCase
{
    private XmlWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new XmlWriter();
    }

    private function captureContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_klasse_ist_ladbar_und_instanziierbar(): void
    {
        $this->assertInstanceOf(XmlWriter::class, $this->writer);
    }

    public function test_grundausgabe_ist_valides_xml_mit_download_headern(): void
    {
        $data = ['products' => [['sku' => 'SKU-001', 'name' => 'Bohrer', 'status' => 'active']]];

        $response = $this->writer->write($data, 'xml-export');
        $content = $this->captureContent($response);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content), 'Output muss valides XML sein');

        $this->assertSame('export', $dom->documentElement->nodeName);
        $this->assertSame('SKU-001', $dom->getElementsByTagName('sku')->item(0)?->textContent);
        $this->assertSame('Bohrer', $dom->getElementsByTagName('name')->item(0)?->textContent);

        $this->assertStringContainsString('xml-export.xml', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_attribute_preise_und_relationen_im_output(): void
    {
        $data = ['products' => [[
            'sku' => 'SKU-002',
            'name' => 'Akkuschrauber',
            'attributes' => [
                ['attribute' => 'gewicht-kg', 'attribute_name' => 'Gewicht', 'value' => '1.8', 'language' => 'de'],
            ],
            'prices' => [
                ['price_type' => 'list_price', 'amount' => 189.99, 'currency' => 'EUR'],
            ],
            'relations' => [
                ['target_sku' => 'ZUB-001', 'relation_type' => 'accessories', 'position' => 1],
            ],
        ]]];

        $content = $this->captureContent($this->writer->write($data, 'export'));

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content));

        $this->assertSame('gewicht-kg', $dom->getElementsByTagName('technical_name')->item(0)?->textContent);
        $this->assertSame('list_price', $dom->getElementsByTagName('type')->item(0)?->textContent);
        $this->assertSame('189.99', $dom->getElementsByTagName('amount')->item(0)?->textContent);
        $this->assertSame('ZUB-001', $dom->getElementsByTagName('target_sku')->item(0)?->textContent);
    }

    public function test_sonderzeichen_werden_escaped_round_trip(): void
    {
        $data = ['products' => [['sku' => 'S<&>"1', 'name' => 'Bohrer & Meißel <5mm> "Profi"']]];

        $content = $this->captureContent($this->writer->write($data, 'export'));

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content), 'Sonderzeichen müssen escaped werden');
        $this->assertSame('Bohrer & Meißel <5mm> "Profi"', $dom->getElementsByTagName('name')->item(0)?->textContent);
    }

    public function test_leere_daten_ergeben_leeres_export_element(): void
    {
        $content = $this->captureContent($this->writer->write(['products' => []], 'leer'));

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($content));
        $this->assertSame(0, $dom->getElementsByTagName('product')->length);
    }
}
