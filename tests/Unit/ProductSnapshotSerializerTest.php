<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProductSnapshotSerializer;
use Tests\TestCase;

/**
 * Reine Vergleichslogik des Snapshot-Serializers (ohne DB).
 */
class ProductSnapshotSerializerTest extends TestCase
{
    private function baseSnapshot(array $overrides = []): array
    {
        return array_merge([
            '_schema' => 2,
            'name' => 'Produkt A',
            'sku' => 'SKU1',
            'ean' => null,
            'status' => 'active',
            'master_hierarchy_node_id' => null,
            'manufacturer_id' => null,
            'workflow_status' => null,
            'workflow_assignee_id' => null,
            'attributes' => [],
            'prices' => [],
            'media' => [],
            'relations' => [],
            'output_hierarchies' => [],
        ], $overrides);
    }

    public function test_compare_detects_base_and_price_changes(): void
    {
        $serializer = new ProductSnapshotSerializer();
        $key = 'pt1|EUR||';

        $old = $this->baseSnapshot([
            'prices' => [$key => ['label' => 'list', 'value' => '10 EUR', 'raw' => ['price_type_id' => 'pt1']]],
        ]);
        $new = $this->baseSnapshot([
            'name' => 'Produkt B',
            'prices' => [$key => ['label' => 'list', 'value' => '12 EUR', 'raw' => ['price_type_id' => 'pt1']]],
        ]);

        $fields = collect($serializer->compare($old, $new)['fields']);

        $name = $fields->firstWhere('field', 'name');
        $this->assertTrue($name['changed']);
        $this->assertSame('Produkt A', $name['old_value']);
        $this->assertSame('Produkt B', $name['new_value']);

        $price = $fields->firstWhere('type', 'price');
        $this->assertNotNull($price);
        $this->assertTrue($price['changed']);
        $this->assertSame('10 EUR', $price['old_value']);
        $this->assertSame('12 EUR', $price['new_value']);
    }

    public function test_compare_flags_added_assignment(): void
    {
        $serializer = new ProductSnapshotSerializer();

        $old = $this->baseSnapshot();
        $new = $this->baseSnapshot([
            'media' => ['m1|u1|' => ['label' => 'img.jpg', 'value' => 'Pos 0', 'raw' => ['media_id' => 'm1']]],
        ]);

        $media = collect($serializer->compare($old, $new)['fields'])->firstWhere('type', 'media');
        $this->assertNotNull($media);
        $this->assertTrue($media['changed']);
        $this->assertNull($media['old_value']);
        $this->assertSame('Pos 0', $media['new_value']);
    }

    public function test_equals_is_order_independent(): void
    {
        $serializer = new ProductSnapshotSerializer();

        $a = $this->baseSnapshot(['prices' => ['k1' => ['a' => 1, 'b' => 2]]]);
        $b = $this->baseSnapshot(['prices' => ['k1' => ['b' => 2, 'a' => 1]]]);
        $this->assertTrue($serializer->equals($a, $b));

        $c = $this->baseSnapshot(['name' => 'Anders']);
        $this->assertFalse($serializer->equals($a, $c));
    }
}
