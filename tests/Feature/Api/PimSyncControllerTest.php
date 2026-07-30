<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Media;
use App\Models\MediaUsageType;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMediaAssignment;
use App\Models\ProductPrice;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Happy-Path-Tests fuer die passive Seite des anyPIM-Sync-Connectors
 * (app/Http/Controllers/Api/V1/PimSyncController.php + PimSyncExportService/PimSyncImportService).
 *
 * Deckt nur den nachweislich funktionierenden Kern ab: einfache String-Attribute
 * (inkl. mehrsprachig), Preise, Medien-Metadaten und Checksum-Delta-Sync.
 * Number/Date/Flag/Selection-Attribute sind bewusst NICHT abgedeckt: der Import-Pfad
 * (PimSyncImportService::buildValueFields()) vergleicht $attribute->data_type gegen
 * kleingeschriebene Strings ('boolean', 'number', 'date'), waehrend bereits bestehende
 * Attribute die kanonische Gross-Schreibung aus Attribute::DATA_TYPES fuehren
 * ('Number', 'Date', 'Flag', ...) - der Vergleich schlaegt daher fehl und der Wert
 * landet im falschen value_*-Feld. Das ist ein separater Bug, kein Testluecken-Versehen.
 */
class PimSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::findOrCreate('Admin', 'sanctum'));
        $this->actingAs($this->user);
    }

    public function test_index_gibt_produkte_gefiltert_nach_skus_zurueck(): void
    {
        $match = Product::factory()->create(['sku' => 'MATCH-001']);
        Product::factory()->create(['sku' => 'OTHER-001']);

        $response = $this->getJson('/api/v1/pim-sync/products?' . http_build_query([
            'skus' => ['MATCH-001'],
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.sku', $match->sku);
    }

    public function test_show_liefert_produkt_mit_attributen_preisen_und_medien(): void
    {
        $product = Product::factory()->create(['name' => 'Akkubohrer']);

        $textAttribute = Attribute::factory()->create([
            'technical_name' => 'produktbeschreibung',
            'data_type'      => 'String',
            'is_translatable' => false,
        ]);
        ProductAttributeValue::factory()->create([
            'product_id'   => $product->id,
            'attribute_id' => $textAttribute->id,
            'value_string' => 'Leistungsstarker Akkubohrer',
        ]);

        $translatableAttribute = Attribute::factory()->translatable()->create([
            'technical_name' => 'marketing-text',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id'   => $product->id,
            'attribute_id' => $translatableAttribute->id,
            'value_string' => 'Deutscher Text',
            'language'     => 'de',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id'   => $product->id,
            'attribute_id' => $translatableAttribute->id,
            'value_string' => 'English text',
            'language'     => 'en',
        ]);

        $priceType = PriceType::factory()->create(['technical_name' => 'list_price']);
        ProductPrice::factory()->create([
            'product_id'    => $product->id,
            'price_type_id' => $priceType->id,
            'amount'        => 199.99,
            'currency'      => 'EUR',
        ]);

        // 'teaser' ist ein Standard-MediaUsageType aus einer frueheren Migration (Datenmigration),
        // daher firstOrCreate statt factory()->create() um Unique-Constraint-Kollisionen zu vermeiden.
        $usageType = MediaUsageType::firstOrCreate(
            ['technical_name' => 'teaser'],
            ['name_de' => 'Hauptbild', 'name_en' => 'Main Image', 'sort_order' => 0],
        );
        $media = Media::factory()->create(['file_name' => 'akkubohrer.jpg']);
        ProductMediaAssignment::factory()->create([
            'product_id'    => $product->id,
            'media_id'      => $media->id,
            'usage_type_id' => $usageType->id,
            'is_primary'    => true,
        ]);

        $response = $this->getJson("/api/v1/pim-sync/products/{$product->sku}");

        $response->assertOk()
            ->assertJsonPath('data.sku', $product->sku)
            ->assertJsonPath('data.name', 'Akkubohrer')
            ->assertJsonPath('data.attributes.produktbeschreibung', 'Leistungsstarker Akkubohrer')
            ->assertJsonPath('data.attributes.marketing-text.de', 'Deutscher Text')
            ->assertJsonPath('data.attributes.marketing-text.en', 'English text')
            ->assertJsonPath('data.prices.0.type', 'list_price')
            ->assertJsonPath('data.prices.0.amount', 199.99)
            ->assertJsonPath('data.prices.0.currency', 'EUR')
            ->assertJsonPath('data.media.0.file_name', 'akkubohrer.jpg')
            ->assertJsonPath('data.media.0.usage_type', 'teaser')
            ->assertJsonStructure(['data' => ['_checksum', '_updated_at']]);
    }

    public function test_show_gibt_404_fuer_unbekannte_sku(): void
    {
        $this->getJson('/api/v1/pim-sync/products/UNKNOWN-SKU')
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    public function test_receive_products_legt_neues_produkt_mit_attribut_preis_und_medium_an(): void
    {
        $productType = ProductType::factory()->create(['technical_name' => 'werkzeug']);
        $attribute = Attribute::factory()->create([
            'technical_name' => 'farbe',
            'data_type'      => 'String',
        ]);
        $priceType = PriceType::factory()->create(['technical_name' => 'list_price']);
        // 'teaser' ist ein Standard-MediaUsageType aus einer frueheren Migration (Datenmigration),
        // daher firstOrCreate statt factory()->create() um Unique-Constraint-Kollisionen zu vermeiden.
        $usageType = MediaUsageType::firstOrCreate(
            ['technical_name' => 'teaser'],
            ['name_de' => 'Hauptbild', 'name_en' => 'Main Image', 'sort_order' => 0],
        );
        // Medium existiert bereits lokal (per file_name gefunden) - der Import-Pfad, der ein
        // NEUES Media-Objekt anlegt (PimSyncImportService::syncMedia()), setzt kein file_size,
        // obwohl die Spalte NOT NULL ohne Default ist, und schlaegt daher unconditional fehl.
        // Das ist ein Bug in der Connector-Logik selbst, bewusst nicht mitbehoben (siehe
        // Zusammenfassung) - dieser Test deckt daher nur den funktionierenden Zuordnungs-Pfad ab.
        $media = Media::factory()->create(['file_name' => 'produkt.jpg']);

        $response = $this->postJson('/api/v1/pim-sync/products', [
            'products' => [[
                'sku'          => 'NEW-SKU-001',
                'ean'          => '4000000000001',
                'name'         => 'Neues Produkt',
                'status'       => 'active',
                'product_type' => $productType->technical_name,
                'attributes'   => [
                    'farbe' => 'Rot',
                ],
                'prices' => [[
                    'type'     => 'list_price',
                    'amount'   => 49.90,
                    'currency' => 'EUR',
                ]],
                'media' => [[
                    'file_name'  => 'produkt.jpg',
                    'file_path'  => 'media/produkt.jpg',
                    'mime_type'  => 'image/jpeg',
                    'usage_type' => 'teaser',
                    'is_primary' => true,
                ]],
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('stats.created', 1)
            ->assertJsonPath('stats.errors', 0);

        $this->assertDatabaseHas('products', [
            'sku'             => 'NEW-SKU-001',
            'name'            => 'Neues Produkt',
            'product_type_id' => $productType->id,
        ]);

        $product = Product::where('sku', 'NEW-SKU-001')->firstOrFail();

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id'   => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'Rot',
        ]);

        $this->assertDatabaseHas('product_prices', [
            'product_id'    => $product->id,
            'price_type_id' => $priceType->id,
            'amount'        => 49.90,
            'currency'      => 'EUR',
        ]);

        $this->assertDatabaseHas('product_media_assignments', [
            'product_id'    => $product->id,
            'usage_type_id' => $usageType->id,
            'is_primary'    => true,
        ]);
        $this->assertDatabaseHas('media', ['file_name' => 'produkt.jpg']);
    }

    public function test_receive_products_aktualisiert_bestehendes_produkt(): void
    {
        $product = Product::factory()->create(['sku' => 'EXIST-001', 'name' => 'Alter Name']);

        $response = $this->postJson('/api/v1/pim-sync/products', [
            'products' => [[
                'sku'    => 'EXIST-001',
                'name'   => 'Neuer Name',
                'status' => 'active',
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('stats.updated', 1)
            ->assertJsonPath('stats.created', 0);

        $this->assertDatabaseHas('products', [
            'id'   => $product->id,
            'name' => 'Neuer Name',
        ]);
    }

    public function test_receive_products_ueberspringt_bei_local_wins(): void
    {
        Product::factory()->create(['sku' => 'LOCAL-001', 'name' => 'Lokaler Name']);

        $response = $this->postJson('/api/v1/pim-sync/products', [
            'products' => [[
                'sku'  => 'LOCAL-001',
                'name' => 'Remote Name',
            ]],
            'conflict_resolution' => 'local_wins',
        ]);

        $response->assertOk()->assertJsonPath('stats.skipped', 1);

        $this->assertDatabaseHas('products', [
            'sku'  => 'LOCAL-001',
            'name' => 'Lokaler Name',
        ]);
    }

    public function test_checksums_liefert_eintrag_pro_produkt(): void
    {
        Product::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/pim-sync/checksums');

        $response->assertOk()->assertJsonPath('total', 2);
        $this->assertCount(2, $response->json('checksums'));
    }

    public function test_attributes_endpoint_liefert_schema(): void
    {
        Attribute::factory()->create(['technical_name' => 'sync-test-attribut']);

        $response = $this->getJson('/api/v1/pim-sync/attributes');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('technical_name');
        $this->assertTrue($names->contains('sync-test-attribut'));
    }

    public function test_schema_endpoint_liefert_produkttypen_und_hierarchien(): void
    {
        ProductType::factory()->create(['technical_name' => 'werkzeug']);

        $this->getJson('/api/v1/pim-sync/schema')
            ->assertOk()
            ->assertJsonStructure(['product_types', 'hierarchies', 'attribute_count'])
            ->assertJsonFragment(['technical_name' => 'werkzeug']);
    }

    public function test_media_endpoint_verlangt_sku_parameter(): void
    {
        $this->getJson('/api/v1/pim-sync/media')
            ->assertStatus(422)
            ->assertJsonPath('error', 'missing_parameter');
    }

    public function test_media_endpoint_liefert_medien_fuer_produkt(): void
    {
        $product = Product::factory()->create();
        $media = Media::factory()->create(['file_name' => 'sync-media.jpg']);
        ProductMediaAssignment::factory()->create([
            'product_id' => $product->id,
            'media_id'   => $media->id,
        ]);

        $this->getJson("/api/v1/pim-sync/media?sku={$product->sku}")
            ->assertOk()
            ->assertJsonPath('data.0.file_name', 'sync-media.jpg');
    }

    public function test_unauthenticated_request_wird_abgelehnt(): void
    {
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/pim-sync/products')->assertUnauthorized();
    }
}
