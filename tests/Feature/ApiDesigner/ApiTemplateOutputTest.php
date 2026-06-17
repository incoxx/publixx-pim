<?php

declare(strict_types=1);

namespace Tests\Feature\ApiDesigner;

use App\Models\ApiTemplate;
use App\Models\Attribute;
use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-Tests für den konsumierenden Stream-Endpunkt
 * GET/POST /api/v1/api-streams/{slug} (JSON-Output gemäß Template-Felddefinition).
 */
class ApiTemplateOutputTest extends TestCase
{
    use RefreshDatabase;

    /** Erstellt ein Template mit den übergebenen Detail-Elementen (Gruppierung: keine). */
    private function makeTemplate(array $detailElements, array $overrides = []): ApiTemplate
    {
        return ApiTemplate::create(array_merge([
            'name' => 'Stream Test',
            'slug' => 'stream-test',
            'template_json' => [
                'version' => 1,
                'groups' => [[
                    'id' => 'g1',
                    'field' => 'none',
                    'label' => 'Alle',
                    'sortOrder' => 'asc',
                    'header' => ['elements' => []],
                    'footer' => ['elements' => []],
                    'groups' => [],
                    'detail' => ['elements' => $detailElements],
                ]],
            ],
            'language' => 'de',
            'is_active' => true,
            'auth_type' => 'none',
            'output_format' => 'json',
        ], $overrides));
    }

    public function test_output_enthaelt_attribut_preis_und_medienfelder(): void
    {
        $product = Product::factory()->active()->create(['sku' => 'SKU-100', 'name' => 'Akkubohrer']);

        // Attribut (String)
        $attribute = Attribute::factory()->create(['data_type' => 'String', 'name_de' => 'Farbe']);
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Rot',
        ]);

        // Preis
        $priceType = PriceType::factory()->create();
        ProductPrice::factory()->create([
            'product_id' => $product->id,
            'price_type_id' => $priceType->id,
            'amount' => 189.99,
            'currency' => 'EUR',
        ]);

        // Medium (url-Modus)
        $usageType = MediaUsageType::factory()->create();
        $media = Media::factory()->create(['file_path' => 'media/teaser.jpg']);
        ProductMediaAssignment::factory()->create([
            'product_id' => $product->id,
            'media_id' => $media->id,
            'usage_type_id' => $usageType->id,
            'is_primary' => true,
        ]);

        $this->makeTemplate([
            ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string'],
            ['type' => 'attribute', 'attributeId' => $attribute->id, 'jsonKey' => 'farbe'],
            ['type' => 'price', 'priceTypeId' => $priceType->id, 'jsonKey' => 'list_price'],
            ['type' => 'media', 'usageTypeId' => $usageType->id, 'jsonKey' => 'teaser', 'mediaMode' => 'url'],
        ]);

        $response = $this->getJson('/api/v1/api-streams/stream-test');

        $response->assertOk();
        $json = json_decode($response->streamedContent(), true);

        $this->assertSame(1, $json['total']);
        $this->assertSame(1, $json['count']);

        $productData = $json['groups'][0]['products'][0];
        $this->assertSame('SKU-100', $productData['sku']);
        $this->assertSame('Rot', $productData['farbe']);
        $this->assertSame(189.99, $productData['list_price']['amount']);
        $this->assertSame('EUR', $productData['list_price']['currency']);
        $this->assertStringContainsString('media/teaser.jpg', $productData['teaser']);
    }

    public function test_nur_aktive_produkte_werden_ausgegeben(): void
    {
        Product::factory()->active()->create(['sku' => 'AKTIV-1']);
        Product::factory()->create(['sku' => 'DRAFT-1', 'status' => 'draft']);

        $this->makeTemplate([
            ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string'],
        ]);

        $response = $this->getJson('/api/v1/api-streams/stream-test');
        $json = json_decode($response->streamedContent(), true);

        $this->assertSame(1, $json['total']);
        $this->assertSame('AKTIV-1', $json['groups'][0]['products'][0]['sku']);
    }

    public function test_fehlende_werte_liefern_null(): void
    {
        Product::factory()->active()->create(['sku' => 'LEER-1']);

        $attribute = Attribute::factory()->create(['data_type' => 'String']);
        $priceType = PriceType::factory()->create();

        $this->makeTemplate([
            ['type' => 'attribute', 'attributeId' => $attribute->id, 'jsonKey' => 'farbe'],
            ['type' => 'price', 'priceTypeId' => $priceType->id, 'jsonKey' => 'list_price'],
        ]);

        $response = $this->getJson('/api/v1/api-streams/stream-test');
        $json = json_decode($response->streamedContent(), true);

        $productData = $json['groups'][0]['products'][0];
        $this->assertNull($productData['farbe']);
        $this->assertNull($productData['list_price']['amount']);
    }

    public function test_sprachparameter_uebersteuert_template_sprache(): void
    {
        $product = Product::factory()->active()->create();

        // Flag-Attribut: 'Ja' (de) vs. 'Yes' (en)
        $attribute = Attribute::factory()->create(['data_type' => 'Flag']);
        ProductAttributeValue::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => null,
            'value_flag' => true,
        ]);

        $this->makeTemplate([
            ['type' => 'attribute', 'attributeId' => $attribute->id, 'jsonKey' => 'lagernd'],
        ]);

        // Template-Sprache de
        $jsonDe = json_decode($this->getJson('/api/v1/api-streams/stream-test')->streamedContent(), true);
        $this->assertSame('Ja', $jsonDe['groups'][0]['products'][0]['lagernd']);

        // ?lang=en übersteuert
        $jsonEn = json_decode($this->getJson('/api/v1/api-streams/stream-test?lang=en')->streamedContent(), true);
        $this->assertSame('Yes', $jsonEn['groups'][0]['products'][0]['lagernd']);
    }

    public function test_pagination_mit_limit_offset_und_navigations_urls(): void
    {
        foreach (['A-1', 'B-2', 'C-3'] as $sku) {
            Product::factory()->active()->create(['sku' => $sku]);
        }

        $this->makeTemplate([
            ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string'],
        ]);

        $response = $this->getJson('/api/v1/api-streams/stream-test?limit=2&offset=1&sort=sku&order=asc');
        $json = json_decode($response->streamedContent(), true);

        $this->assertSame(3, $json['total']);
        $this->assertSame(2, $json['count']);
        $this->assertSame(1, $json['offset']);
        $this->assertSame(2, $json['limit']);
        $this->assertArrayHasKey('next_url', $json);
        $this->assertArrayHasKey('prev_url', $json);

        // Sortierung + Offset: erstes ausgegebenes Produkt ist B-2
        $skus = array_column($json['groups'][0]['products'], 'sku');
        $this->assertSame(['B-2', 'C-3'], $skus);
    }

    public function test_sparse_fieldset_filtert_json_keys(): void
    {
        Product::factory()->active()->create(['sku' => 'SKU-1', 'name' => 'Produkt']);

        $this->makeTemplate([
            ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string'],
            ['type' => 'field', 'field' => 'name', 'jsonKey' => 'name', 'dataType' => 'string'],
            ['type' => 'field', 'field' => 'status', 'jsonKey' => 'status', 'dataType' => 'string'],
        ]);

        $response = $this->getJson('/api/v1/api-streams/stream-test?fields=sku,name');
        $json = json_decode($response->streamedContent(), true);

        $productData = $json['groups'][0]['products'][0];
        $this->assertSame(['sku', 'name'], array_keys($productData));
    }

    public function test_write_only_felder_erscheinen_nicht_im_output(): void
    {
        Product::factory()->active()->create(['sku' => 'SKU-1']);

        $this->makeTemplate([
            ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string'],
            ['type' => 'field', 'field' => 'name', 'jsonKey' => 'geheim', 'dataType' => 'string', 'access' => 'write'],
        ]);

        $response = $this->getJson('/api/v1/api-streams/stream-test');
        $json = json_decode($response->streamedContent(), true);

        $productData = $json['groups'][0]['products'][0];
        $this->assertArrayHasKey('sku', $productData);
        $this->assertArrayNotHasKey('geheim', $productData);
    }

    public function test_api_key_authentifizierung(): void
    {
        Product::factory()->active()->create();

        $plainKey = 'mein-geheimer-key';
        $this->makeTemplate(
            [['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string']],
            ['auth_type' => 'api_key', 'api_key' => hash('sha256', $plainKey)],
        );

        // Ohne Header → 401
        $this->getJson('/api/v1/api-streams/stream-test')->assertUnauthorized();

        // Falscher Key → 401
        $this->getJson('/api/v1/api-streams/stream-test', ['X-Api-Key' => 'falsch'])->assertUnauthorized();

        // Korrekter Key → 200
        $this->getJson('/api/v1/api-streams/stream-test', ['X-Api-Key' => $plainKey])->assertOk();
    }

    public function test_inaktives_template_und_unbekannter_slug_liefern_404(): void
    {
        $this->makeTemplate(
            [['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string']],
            ['is_active' => false],
        );

        $this->getJson('/api/v1/api-streams/stream-test')->assertNotFound();
        $this->getJson('/api/v1/api-streams/gibt-es-nicht')->assertNotFound();
    }

    public function test_zugriff_wird_protokolliert(): void
    {
        Product::factory()->active()->create();

        $template = $this->makeTemplate([
            ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string'],
        ]);

        $this->getJson('/api/v1/api-streams/stream-test')->assertOk();

        $this->assertDatabaseHas('api_template_access_logs', [
            'api_template_id' => $template->id,
        ]);
    }
}
