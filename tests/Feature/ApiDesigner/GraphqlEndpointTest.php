<?php

declare(strict_types=1);

namespace Tests\Feature\ApiDesigner;

use App\Models\ApiTemplate;
use App\Models\Attribute;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-Tests für den GraphQL-Endpunkt POST /api/v1/api-streams/{slug}
 * (Templates mit output_format = graphql; Queries und Mutations).
 */
class GraphqlEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** GraphQL-Template mit Standard-Feldern (sku, name, status) erstellen. */
    private function makeGraphqlTemplate(array $overrides = [], ?array $detailElements = null): ApiTemplate
    {
        $detailElements ??= [
            ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string'],
            ['type' => 'field', 'field' => 'name', 'jsonKey' => 'name', 'dataType' => 'string'],
            ['type' => 'field', 'field' => 'status', 'jsonKey' => 'status', 'dataType' => 'string'],
        ];

        return ApiTemplate::create(array_merge([
            'name' => 'GraphQL Test',
            'slug' => 'graphql-test',
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
            'output_format' => 'graphql',
            'direction' => 'export',
        ], $overrides));
    }

    private function graphql(string $query, ?array $variables = null): array
    {
        $payload = ['query' => $query];
        if ($variables !== null) {
            $payload['variables'] = $variables;
        }

        return $this->postJson('/api/v1/api-streams/graphql-test', $payload)->json();
    }

    public function test_query_liefert_produkte_ueber_template_schema(): void
    {
        Product::factory()->active()->create(['sku' => 'GQL-1', 'name' => 'Bohrer']);
        $this->makeGraphqlTemplate();

        $result = $this->graphql('{ total groups { count products { sku name } } }');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame(1, $result['data']['total']);
        $this->assertSame('GQL-1', $result['data']['groups'][0]['products'][0]['sku']);
        $this->assertSame('Bohrer', $result['data']['groups'][0]['products'][0]['name']);
    }

    public function test_feld_auswahl_liefert_nur_angefragte_felder(): void
    {
        Product::factory()->active()->create(['sku' => 'GQL-1', 'name' => 'Bohrer']);
        $this->makeGraphqlTemplate();

        $result = $this->graphql('{ groups { products { sku } } }');

        $productData = $result['data']['groups'][0]['products'][0];
        $this->assertSame(['sku'], array_keys($productData));
    }

    public function test_unbekanntes_feld_liefert_validierungsfehler(): void
    {
        $this->makeGraphqlTemplate();

        $result = $this->graphql('{ groups { products { gibtEsNicht } } }');

        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('gibtEsNicht', $result['errors'][0]['message']);
    }

    public function test_syntax_fehler_liefert_errors_payload(): void
    {
        $this->makeGraphqlTemplate();

        // webonyx fängt Syntax-Fehler intern ab → 200 mit errors[] (GraphQL-Spec-konform)
        $response = $this->postJson('/api/v1/api-streams/graphql-test', ['query' => '{ kaputt']);

        $response->assertOk()->assertJsonStructure(['errors' => [['message']]]);
        $this->assertStringContainsString('Syntax Error', $response->json('errors.0.message'));
    }

    public function test_fehlende_query_liefert_400(): void
    {
        $this->makeGraphqlTemplate();

        $response = $this->postJson('/api/v1/api-streams/graphql-test', []);

        $response->assertBadRequest();
        $this->assertStringContainsString('GraphQL-Query fehlt', $response->json('errors.0.message'));
    }

    public function test_mutation_wird_bei_export_template_abgelehnt(): void
    {
        // direction = export → readonly, Mutations verboten
        $this->makeGraphqlTemplate(['direction' => 'export']);

        $response = $this->postJson('/api/v1/api-streams/graphql-test', [
            'query' => 'mutation { deleteProduct(sku: "X") { success } }',
        ]);

        $response->assertStatus(405);
        $this->assertStringContainsString('keine Mutationen', $response->json('errors.0.message'));

        // SDL eines Export-Templates enthält gar keinen Mutation-Type
        $sdlTemplate = $this->makeGraphqlTemplate(['slug' => 'graphql-export-sdl']);
        $this->assertStringNotContainsString(
            'type Mutation',
            app(\App\Services\ApiDesigner\GraphqlSchemaBuilder::class)
                ->toSdl($sdlTemplate->template_json, 'de', 'export'),
        );
    }

    public function test_mutation_create_product_legt_produkt_mit_attributen_und_preisen_an(): void
    {
        $this->makeGraphqlTemplate(['direction' => 'bidirectional']);

        $productType = ProductType::factory()->create();
        $attribute = Attribute::factory()->create(['technical_name' => 'farbe', 'data_type' => 'String']);
        $priceType = PriceType::factory()->create();

        $result = $this->graphql(<<<GRAPHQL
            mutation {
              createProduct(input: {
                sku: "NEU-001"
                name: "Neues Produkt"
                product_type_id: "{$productType->id}"
                status: "draft"
                attributes: [{ technical_name: "farbe", value: "blau", language: "de" }]
                prices: [{ price_type_id: "{$priceType->id}", amount: 49.95, currency: "EUR" }]
              }) {
                success
                message
                product { id sku name status }
                errors
              }
            }
            GRAPHQL);

        $this->assertArrayNotHasKey('errors', $result);
        $payload = $result['data']['createProduct'];
        $this->assertTrue($payload['success']);
        $this->assertSame('NEU-001', $payload['product']['sku']);

        $product = Product::where('sku', 'NEU-001')->firstOrFail();
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'value_string' => 'blau',
            'language' => 'de',
        ]);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'price_type_id' => $priceType->id,
            'currency' => 'EUR',
        ]);
    }

    public function test_mutation_update_product_per_sku_aktualisiert_werte(): void
    {
        $this->makeGraphqlTemplate(['direction' => 'import']);

        $product = Product::factory()->create(['sku' => 'UPD-001', 'name' => 'Alt', 'status' => 'draft']);
        $attribute = Attribute::factory()->create(['technical_name' => 'gewicht', 'data_type' => 'Number']);

        $result = $this->graphql(<<<'GRAPHQL'
            mutation {
              updateProduct(sku: "UPD-001", input: {
                name: "Neu"
                status: "active"
                attributes: [{ technical_name: "gewicht", value: "1.5" }]
              }) {
                success
                product { sku name status }
              }
            }
            GRAPHQL);

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertTrue($result['data']['updateProduct']['success']);
        $this->assertSame('Neu', $result['data']['updateProduct']['product']['name']);

        $product->refresh();
        $this->assertSame('active', $product->status);

        $value = ProductAttributeValue::where('product_id', $product->id)
            ->where('attribute_id', $attribute->id)
            ->firstOrFail();
        $this->assertSame(1.5, (float) $value->value_number);
    }

    public function test_mutation_update_unbekanntes_produkt_liefert_fehler_payload(): void
    {
        $this->makeGraphqlTemplate(['direction' => 'import']);

        $result = $this->graphql(<<<'GRAPHQL'
            mutation {
              updateProduct(sku: "GIBT-ES-NICHT", input: { name: "X" }) {
                success
                message
                errors
              }
            }
            GRAPHQL);

        $payload = $result['data']['updateProduct'];
        $this->assertFalse($payload['success']);
        $this->assertSame('Produkt nicht gefunden.', $payload['message']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function test_mutation_delete_product_loescht_produkt(): void
    {
        $this->makeGraphqlTemplate(['direction' => 'bidirectional']);

        $product = Product::factory()->create(['sku' => 'DEL-001']);

        $result = $this->graphql('mutation { deleteProduct(sku: "DEL-001") { success message } }');

        $this->assertTrue($result['data']['deleteProduct']['success']);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_mutation_import_products_zaehlt_created_und_updated(): void
    {
        $this->makeGraphqlTemplate(['direction' => 'import']);

        $productType = ProductType::factory()->create();
        Product::factory()->create(['sku' => 'BATCH-EXISTIERT', 'name' => 'Alt']);

        $result = $this->graphql(<<<GRAPHQL
            mutation {
              importProducts(products: [
                { sku: "BATCH-NEU", name: "Neu A", product_type_id: "{$productType->id}" }
                { sku: "BATCH-EXISTIERT", name: "Aktualisiert", product_type_id: "{$productType->id}" }
              ]) {
                total
                created
                updated
                failed
                results { success product { sku } }
              }
            }
            GRAPHQL);

        $this->assertArrayNotHasKey('errors', $result);
        $batch = $result['data']['importProducts'];
        $this->assertSame(2, $batch['total']);
        $this->assertSame(1, $batch['created']);
        $this->assertSame(1, $batch['updated']);
        $this->assertSame(0, $batch['failed']);

        $this->assertDatabaseHas('products', ['sku' => 'BATCH-NEU', 'name' => 'Neu A']);
        $this->assertDatabaseHas('products', ['sku' => 'BATCH-EXISTIERT', 'name' => 'Aktualisiert']);
    }

    public function test_write_only_feld_ist_nicht_im_query_schema(): void
    {
        Product::factory()->active()->create(['sku' => 'WO-1']);

        $this->makeGraphqlTemplate([], [
            ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku', 'dataType' => 'string'],
            ['type' => 'field', 'field' => 'name', 'jsonKey' => 'geheim', 'dataType' => 'string', 'access' => 'write'],
        ]);

        // Write-only Feld darf nicht abfragbar sein → GraphQL-Validierungsfehler
        $result = $this->graphql('{ groups { products { sku geheim } } }');

        $this->assertArrayHasKey('errors', $result);
        $this->assertStringContainsString('geheim', $result['errors'][0]['message']);
    }

    public function test_query_mit_variablen_wird_unterstuetzt(): void
    {
        Product::factory()->active()->create(['sku' => 'VAR-1']);
        $this->makeGraphqlTemplate();

        $result = $this->graphql(
            'query Produkte($mitName: Boolean!) { groups { products { sku name @include(if: $mitName) } } }',
            ['mitName' => false],
        );

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame(['sku'], array_keys($result['data']['groups'][0]['products'][0]));
    }
}
