<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Models\Attribute;
use App\Models\ExportProfile;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\PublixxExportMapping;
use App\Services\Export\DatasetBuilder;
use App\Services\Export\ExportProfileService;
use App\Services\Export\ExportService;
use App\Services\Export\MappingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * End-to-End-Tests für den profil-basierten Export (ExportProfileService)
 * und die Mapping-Export-Pipeline (ExportService).
 *
 * Hinweis: Das XML-Format wird hier nicht getestet, da die Writer-Klasse
 * App\Services\Export\Writers\XmlWriter aktuell nicht ladbar ist
 * (Namenskonflikt mit `use XMLWriter;`, siehe Bug-Report).
 */
class ExportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ExportProfileService $profileService;

    protected ExportService $exportService;

    /** @var string[] Aufzuräumende Temp-Dateien */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->profileService = new ExportProfileService();
        $this->exportService = new ExportService(new DatasetBuilder(new MappingResolver()), new MappingResolver());
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function captureContent(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    /** Produkt mit einem deutschen und einem englischen Attributwert anlegen. */
    private function createProductWithAttribute(): Product
    {
        $product = Product::factory()->active()->create(['sku' => 'PROF-001', 'name' => 'Akkubohrer']);
        $attribute = Attribute::factory()->create(['technical_name' => 'farbe', 'name_de' => 'Farbe']);

        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Rot',
            'language' => 'de',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Red',
            'language' => 'en',
        ]);

        return $product;
    }

    // ─── ExportProfileService: execute (Format-Weichen) ────────

    public function test_execute_json_profil_end_to_end(): void
    {
        $this->createProductWithAttribute();

        $profile = ExportProfile::factory()->create([
            'format' => 'json',
            'include_attributes' => true,
            'languages' => ['de'], // 'en'-Werte müssen herausgefiltert werden
        ]);

        $response = $this->profileService->execute($profile);
        $content = $this->captureContent($response);

        $this->assertStringContainsString('.json', (string) $response->headers->get('Content-Disposition'));

        $data = json_decode($content, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertCount(1, $data['products']);

        $product = $data['products'][0];
        $this->assertSame('PROF-001', $product['sku']);
        $this->assertSame('Akkubohrer', $product['name']);

        // Sprachfilter: nur der deutsche Wert
        $this->assertCount(1, $product['attributes']);
        $this->assertSame('farbe', $product['attributes'][0]['attribute']);
        $this->assertSame('Rot', $product['attributes'][0]['value']);
        $this->assertSame('de', $product['attributes'][0]['language']);
    }

    public function test_execute_csv_profil_liefert_einzeldatei(): void
    {
        Product::factory()->active()->create(['sku' => 'CSV-001', 'name' => 'Hammer']);

        $profile = ExportProfile::factory()->create([
            'format' => 'csv',
            'include_attributes' => false,
        ]);

        $response = $this->profileService->execute($profile);
        $content = $this->captureContent($response);

        $this->assertSame('text/csv; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('SKU;Name;Status;EAN;Produkttyp', $content);
        $this->assertStringContainsString('CSV-001;Hammer;active', $content);
    }

    public function test_execute_excel_profil_liefert_xlsx(): void
    {
        Product::factory()->active()->create(['sku' => 'XLSX-001']);

        $profile = ExportProfile::factory()->create([
            'format' => 'excel',
            'include_attributes' => false,
        ]);

        $response = $this->profileService->execute($profile);
        $content = $this->captureContent($response);

        // XLSX = ZIP-Container
        $this->assertStringStartsWith("PK\x03\x04", $content);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_dateiname_wird_aus_template_aufgeloest(): void
    {
        Product::factory()->active()->create();

        $profile = ExportProfile::factory()->create([
            'format' => 'json',
            'include_attributes' => false,
            'file_name_template' => 'mein-export-{date}',
        ]);

        $response = $this->profileService->execute($profile);

        $expected = 'mein-export-' . now()->format('Y-m-d') . '.json';
        $this->assertStringContainsString($expected, (string) $response->headers->get('Content-Disposition'));
    }

    // ─── ExportProfileService: executeToFile ────────────────────

    public function test_execute_to_file_json_schreibt_datei(): void
    {
        $this->createProductWithAttribute();

        $profile = ExportProfile::factory()->create([
            'format' => 'json',
            'include_attributes' => true,
            'languages' => ['de', 'en'],
        ]);

        $basePath = tempnam(sys_get_temp_dir(), 'expjson_');
        $this->tmpFiles[] = $basePath;

        $result = $this->profileService->executeToFile($profile, $basePath);
        $this->tmpFiles[] = $result['output_path'];

        $this->assertSame('json', $result['ext']);
        $this->assertSame($basePath . '.json', $result['output_path']);
        $this->assertFileExists($result['output_path']);

        $data = json_decode((string) file_get_contents($result['output_path']), true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertSame('PROF-001', $data['products'][0]['sku']);
        // Beide Sprachen enthalten
        $this->assertCount(2, $data['products'][0]['attributes']);
    }

    public function test_execute_to_file_xlsx_ist_mit_phpspreadsheet_lesbar(): void
    {
        Product::factory()->active()->create(['sku' => 'XLSX-FILE-001', 'name' => 'Größenmaß äöü']);

        $profile = ExportProfile::factory()->create([
            'format' => 'excel',
            'include_attributes' => false,
        ]);

        $basePath = tempnam(sys_get_temp_dir(), 'expxlsx_');
        $this->tmpFiles[] = $basePath;

        $result = $this->profileService->executeToFile($profile, $basePath);
        $this->tmpFiles[] = $result['output_path'];

        $this->assertSame('xlsx', $result['ext']);
        $this->assertFileExists($result['output_path']);

        $sheet = IOFactory::load($result['output_path'])->getSheetByName('Produkte');
        $this->assertNotNull($sheet);
        $this->assertSame('XLSX-FILE-001', $sheet->getCell('A2')->getValue());
        $this->assertSame('Größenmaß äöü', $sheet->getCell('B2')->getValue());
    }

    // ─── ExportProfileService: Scope / Count ────────────────────

    public function test_count_zaehlt_nur_produkte_ohne_varianten(): void
    {
        $parent = Product::factory()->active()->create();
        Product::factory()->active()->create();
        Product::factory()->variant()->create(['parent_product_id' => $parent->id]);

        // Ohne SearchProfile: Fallback auf alle Produkte mit product_type_ref = 'product'
        $profile = ExportProfile::factory()->create(['search_profile_id' => null]);

        $this->assertSame(2, $this->profileService->count($profile));
    }

    // ─── ExportService: Mapping-Pipeline ────────────────────────

    public function test_export_product_mit_mapping_liefert_dataset(): void
    {
        $product = $this->createProductWithAttribute();

        $mapping = PublixxExportMapping::factory()->create([
            'mapping_rules' => [
                'rules' => [
                    ['source' => 'attribute:farbe', 'target' => 'color', 'type' => 'text'],
                ],
            ],
            'languages' => ['de'],
            'flatten_mode' => 'flat',
            'include_media' => false,
            'include_prices' => false,
            'include_variants' => false,
            'include_relations' => false,
        ]);

        $dataset = $this->exportService->exportProduct($product, $mapping);

        $this->assertSame($product->id, $dataset['id']);
        $this->assertSame('PROF-001', $dataset['sku']);
        $this->assertSame('Rot', $dataset['color']);
    }

    public function test_export_product_legt_ergebnis_im_cache_ab(): void
    {
        $product = $this->createProductWithAttribute();

        $mapping = PublixxExportMapping::factory()->create([
            'mapping_rules' => ['rules' => []],
            'flatten_mode' => 'flat',
            'include_media' => false,
            'include_prices' => false,
            'include_variants' => false,
            'include_relations' => false,
        ]);

        $cacheKey = $this->exportService->cacheKey($mapping->id, $product->id);

        $first = $this->exportService->exportProduct($product, $mapping);

        $cached = Cache::tags(['export', "export:mapping:{$mapping->id}", "product:{$product->id}"])->get($cacheKey);
        $this->assertEquals($first, $cached);

        // Invalidierung entfernt den Eintrag wieder
        $this->exportService->invalidateProductCache($product->id);
        $this->assertNull(
            Cache::tags(['export', "export:mapping:{$mapping->id}", "product:{$product->id}"])->get($cacheKey)
        );
    }

    public function test_export_filtered_mit_status_filter(): void
    {
        Product::factory()->active()->create(['sku' => 'FILTER-AKTIV']);
        Product::factory()->create(['sku' => 'FILTER-DRAFT', 'status' => 'draft']);

        $mapping = PublixxExportMapping::factory()->create([
            'mapping_rules' => ['rules' => []],
            'flatten_mode' => 'flat',
            'include_media' => false,
            'include_prices' => false,
            'include_variants' => false,
            'include_relations' => false,
        ]);

        $result = $this->exportService->exportFiltered(['status' => 'active'], $mapping);

        $this->assertSame(1, $result['meta']['total']);
        $this->assertCount(1, $result['data']);
        $this->assertSame('FILTER-AKTIV', $result['data'][0]['sku']);
    }

    public function test_export_product_by_id_liefert_null_fuer_unbekannte_id(): void
    {
        $result = $this->exportService->exportProductById('00000000-0000-0000-0000-000000000000');

        $this->assertNull($result);
    }
}
