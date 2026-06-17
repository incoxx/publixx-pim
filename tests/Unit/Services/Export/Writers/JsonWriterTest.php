<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Export\Writers;

use App\Services\Export\Writers\JsonWriter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Unit-Tests für den JsonWriter (Download und Inline-Response).
 */
class JsonWriterTest extends TestCase
{
    private JsonWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new JsonWriter();
    }

    private function captureContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_grundausgabe_ist_valides_json_mit_download_headern(): void
    {
        $data = ['products' => [['sku' => 'SKU-001', 'name' => 'Bohrer']]];

        $response = $this->writer->write($data, 'json-export');
        $content = $this->captureContent($response);

        $decoded = json_decode($content, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertSame('SKU-001', $decoded['products'][0]['sku']);

        $this->assertSame('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('json-export.json', (string) $response->headers->get('Content-Disposition'));
        // Pretty-Print: Zeilenumbrüche im Output
        $this->assertStringContainsString("\n", $content);
    }

    public function test_umlaute_und_slashes_werden_nicht_escaped(): void
    {
        $data = ['products' => [['sku' => 'Ü-001', 'name' => 'Größenmaß', 'url' => 'https://example.org/a/b']]];

        $content = $this->captureContent($this->writer->write($data, 'export'));

        // JSON_UNESCAPED_UNICODE: Umlaute literal, nicht ü
        $this->assertStringContainsString('Größenmaß', $content);
        $this->assertStringNotContainsString('\u00', $content);
        // JSON_UNESCAPED_SLASHES: kein \/
        $this->assertStringContainsString('https://example.org/a/b', $content);
    }

    public function test_inline_response_ohne_content_disposition(): void
    {
        $response = JsonWriter::asInlineResponse(['products' => [['sku' => 'SKU-002']]]);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('Content-Disposition'));

        $decoded = json_decode((string) $response->getContent(), true);
        $this->assertSame('SKU-002', $decoded['products'][0]['sku']);
    }

    public function test_leere_daten_ergeben_leeres_json_objekt_mit_array(): void
    {
        $content = $this->captureContent($this->writer->write(['products' => []], 'leer'));

        $decoded = json_decode($content, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertSame([], $decoded['products']);
    }
}
