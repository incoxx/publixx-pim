<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\Export\JsonFormatExporter;
use App\Services\Import\JsonFormatImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test: eine ProductAttributeValue-Zeile ohne gesetzten Wert
 * (alle value_*-Spalten NULL — Attribut ist zugeordnet, aber kein Wert gepflegt)
 * wird beim Export korrekt ohne "value"-Key serialisiert (array_filter entfernt
 * NULL-Werte), muss beim Re-Import aber trotzdem funktionieren statt mit
 * "Undefined array key value" abzubrechen (JsonFormatImporter::buildParseResult()
 * griff bisher ungeschuetzt auf $v['value'] zu).
 */
class JsonFormatProductValuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_produktwert_ohne_gesetzten_wert_wird_ohne_value_key_exportiert(): void
    {
        $product = Product::factory()->create(['sku' => 'LEER-001']);
        $attribute = Attribute::factory()->create(['technical_name' => 'ohne-wert']);
        ProductAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $attribute->id,
            'multiplied_index' => 0,
        ]);

        $exporter = new JsonFormatExporter();
        $data = json_decode($exporter->export(['product_attribute_values']), true);

        $row = collect($data['product_attribute_values'])->firstWhere('attribute', 'ohne-wert');
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('value', $row);
    }

    public function test_import_bricht_nicht_ab_wenn_eine_produktwert_zeile_keinen_value_key_hat(): void
    {
        $product = Product::factory()->create(['sku' => 'LEER-002']);
        Attribute::factory()->create(['technical_name' => 'mit-wert', 'data_type' => 'String']);
        Attribute::factory()->create(['technical_name' => 'ohne-wert', 'data_type' => 'String']);

        $payload = [
            '_meta' => [],
            'product_attribute_values' => [
                ['sku' => 'LEER-002', 'attribute' => 'ohne-wert'],
                ['sku' => 'LEER-002', 'attribute' => 'mit-wert', 'value' => 'Testwert'],
            ],
        ];

        $importer = app(JsonFormatImporter::class);
        $result = $importer->importData($payload);

        $this->assertSame(0, $result->totalErrors());
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'value_string' => 'Testwert',
        ]);
    }
}
