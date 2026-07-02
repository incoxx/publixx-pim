<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Events\ImportCompleted;
use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductRelation;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Services\Import\ImportExecutor;
use App\Services\Import\ParseResult;
use App\Services\Import\ReferenceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ImportExecutorTest extends TestCase
{
    use RefreshDatabase;

    private ImportExecutor $executor;
    private string $productTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new ImportExecutor(new ReferenceResolver());

        // Stammdaten anlegen
        $pt = ProductType::forceCreate([
            'id' => 'pt-physical',
            'technical_name' => 'physical_product',
            'name_de' => 'Physisches Produkt',
            'has_variants' => true,
            'has_ean' => true,
            'has_prices' => true,
            'has_media' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $this->productTypeId = $pt->id;
    }

    public function test_creates_new_products(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'NEW-001',
                        'name' => 'Neues Produkt',
                        'name_en' => 'New Product',
                        'product_type' => 'physical_product',
                        'ean' => '4012345678901',
                        'status' => 'draft',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, $result->stats['08_Produkte']['created']);
        $this->assertEquals(0, $result->stats['08_Produkte']['updated']);
        $this->assertDatabaseHas('products', ['sku' => 'NEW-001', 'name' => 'Neues Produkt']);
    }

    public function test_updates_existing_products(): void
    {
        // Vorhandenes Produkt
        Product::forceCreate([
            'id' => 'existing-prod-1',
            'sku' => 'EXIST-001',
            'name' => 'Altes Produkt',
            'product_type_id' => $this->productTypeId,
            'status' => 'draft',
            'product_type_ref' => 'product',
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'EXIST-001',
                        'name' => 'Aktualisiertes Produkt',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => 'active',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(0, $result->stats['08_Produkte']['created']);
        $this->assertEquals(1, $result->stats['08_Produkte']['updated']);
        $this->assertDatabaseHas('products', [
            'sku' => 'EXIST-001',
            'name' => 'Aktualisiertes Produkt',
            'status' => 'active',
        ]);
    }

    public function test_imports_product_attribute_values(): void
    {
        // Produkt und Attribut anlegen
        Product::forceCreate([
            'id' => 'prod-1',
            'sku' => 'SKU-001',
            'name' => 'Test',
            'product_type_id' => $this->productTypeId,
            'status' => 'draft',
            'product_type_ref' => 'product',
        ]);

        Attribute::forceCreate([
            'id' => 'attr-weight',
            'technical_name' => 'product-weight',
            'name_de' => 'Gewicht',
            'data_type' => 'Number',
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_inheritable' => true,
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['09_Produktwerte'],
            data: [
                '09_Produktwerte' => [
                    2 => [
                        'sku' => 'SKU-001',
                        'attribute' => 'product-weight',
                        'value' => '4.5',
                        'unit' => null,
                        'language' => null,
                        'index' => null,
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, $result->stats['09_Produktwerte']['created']);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => 'prod-1',
            'attribute_id' => 'attr-weight',
            'value_number' => 4.5,
        ]);
    }

    public function test_affected_product_ids_collected(): void
    {
        Product::forceCreate([
            'id' => 'prod-aff-1',
            'sku' => 'AFF-001',
            'name' => 'Test',
            'product_type_id' => $this->productTypeId,
            'status' => 'draft',
            'product_type_ref' => 'product',
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'AFF-001',
                        'name' => 'Updated',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => 'active',
                        '_row' => 2,
                    ],
                    3 => [
                        'sku' => 'AFF-002',
                        'name' => 'Neu',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => 'draft',
                        '_row' => 3,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertCount(2, $result->affectedProductIds);
        $this->assertContains('prod-aff-1', $result->affectedProductIds);
    }

    public function test_skips_products_with_unresolvable_type(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'SKIP-001',
                        'name' => 'Kein Typ',
                        'name_en' => null,
                        'product_type' => 'nonexistent_type',
                        'ean' => null,
                        'status' => 'draft',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, $result->stats['08_Produkte']['skipped']);
        $this->assertEquals(0, $result->stats['08_Produkte']['created']);
        $this->assertDatabaseMissing('products', ['sku' => 'SKIP-001']);
    }

    public function test_imports_product_types(): void
    {
        $parseResult = new ParseResult(
            sheetsFound: ['01_Produkttypen'],
            data: [
                '01_Produkttypen' => [
                    2 => [
                        'technical_name' => 'new_type',
                        'name_de' => 'Neuer Typ',
                        'name_en' => 'New Type',
                        'description' => 'Ein neuer Typ',
                        'has_variants' => 'Ja',
                        'has_ean' => 'Nein',
                        'has_prices' => 'Ja',
                        'has_media' => 'Ja',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, $result->stats['01_Produkttypen']['created']);
        $this->assertDatabaseHas('product_types', [
            'technical_name' => 'new_type',
            'name_de' => 'Neuer Typ',
            'has_variants' => true,
            'has_ean' => false,
        ]);
    }

    public function test_executes_in_transaction(): void
    {
        // Wenn ein Fehler auftritt, sollte nichts committed werden
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'TXN-001',
                        'name' => 'Test Transaction',
                        'name_en' => null,
                        'product_type' => 'physical_product',
                        'ean' => null,
                        'status' => 'draft',
                        '_row' => 2,
                    ],
                ],
            ],
        );

        // Normaler Import sollte funktionieren
        $result = $this->executor->execute($parseResult);
        $this->assertDatabaseHas('products', ['sku' => 'TXN-001']);
    }

    public function test_value_mapping_for_different_data_types(): void
    {
        Product::forceCreate([
            'id' => 'prod-vt',
            'sku' => 'VT-001',
            'name' => 'Value Type Test',
            'product_type_id' => $this->productTypeId,
            'status' => 'draft',
            'product_type_ref' => 'product',
        ]);

        // String-Attribut
        Attribute::forceCreate([
            'id' => 'attr-desc',
            'technical_name' => 'description',
            'name_de' => 'Beschreibung',
            'data_type' => 'String',
            'is_translatable' => true,
            'is_multipliable' => false,
            'is_searchable' => true,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_inheritable' => true,
        ]);

        // Flag-Attribut
        Attribute::forceCreate([
            'id' => 'attr-flag',
            'technical_name' => 'is-hazardous',
            'name_de' => 'Gefahrgut',
            'data_type' => 'Flag',
            'is_translatable' => false,
            'is_multipliable' => false,
            'is_searchable' => false,
            'is_mandatory' => false,
            'is_unique' => false,
            'is_inheritable' => true,
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['09_Produktwerte'],
            data: [
                '09_Produktwerte' => [
                    2 => [
                        'sku' => 'VT-001',
                        'attribute' => 'description',
                        'value' => 'Ein Text',
                        'unit' => null,
                        'language' => 'de',
                        'index' => null,
                        '_row' => 2,
                    ],
                    3 => [
                        'sku' => 'VT-001',
                        'attribute' => 'is-hazardous',
                        'value' => 'Ja',
                        'unit' => null,
                        'language' => null,
                        'index' => null,
                        '_row' => 3,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        // String-Wert
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => 'prod-vt',
            'attribute_id' => 'attr-desc',
            'value_string' => 'Ein Text',
            'language' => 'de',
        ]);

        // Flag-Wert
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => 'prod-vt',
            'attribute_id' => 'attr-flag',
            'value_flag' => true,
        ]);
    }

    public function test_auto_assigns_missing_attribute_to_hierarchy_node_of_imported_product(): void
    {
        // Regression: das Anlegen der Zuordnung allein reicht nicht — ohne das
        // HierarchyAttributeChanged-Event bleibt der Effektiv-Attribute-Cache des
        // Knotens/Produkts stehen und das Attribut ist im Produkteditor trotzdem
        // unsichtbar (genau der beobachtete Kundenfall).
        Event::fake([\App\Events\HierarchyAttributeChanged::class]);

        // Attribut existiert bereits, ist aber am Zielknoten noch NICHT zugeordnet
        // (07_Hierarchie_Attribute wurde vom Nutzer nicht gepflegt).
        Attribute::forceCreate([
            'id' => 'attr-farbe',
            'technical_name' => 'farbe',
            'name_de' => 'Farbe',
            'data_type' => 'String',
        ]);

        $hierarchy = Hierarchy::forceCreate([
            'id' => 'hier-master',
            'technical_name' => 'sortiment',
            'name_de' => 'Sortiment',
            'hierarchy_type' => 'master',
        ]);

        $node = HierarchyNode::forceCreate([
            'id' => 'node-heizung',
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Heizung',
            'path' => '/Heizung',
            'depth' => 0,
        ]);

        // 08_Produkte + 09_Produktwerte + 11_Produkt_Hierarchien in einem Lauf,
        // wie bei einem echten Vorlagen-Import.
        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte', '09_Produktwerte', '11_Produkt_Hierarchien'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'HZ-001', 'name' => 'Heizkessel', 'name_en' => null,
                        'product_type' => 'physical_product', 'ean' => null, 'status' => 'active', '_row' => 2,
                    ],
                ],
                '09_Produktwerte' => [
                    2 => [
                        'sku' => 'HZ-001', 'attribute' => 'farbe', 'value' => 'Weiß',
                        'unit' => null, 'language' => null, 'index' => null, '_row' => 2,
                    ],
                ],
                '11_Produkt_Hierarchien' => [
                    2 => ['sku' => 'HZ-001', 'hierarchy' => 'sortiment', 'node_path' => 'Heizung', '_row' => 2],
                ],
            ],
        );

        $this->assertDatabaseMissing('hierarchy_node_attribute_assignments', [
            'hierarchy_node_id' => $node->id,
            'attribute_id' => 'attr-farbe',
        ]);

        $result = $this->executor->execute($parseResult);

        $this->assertDatabaseHas('hierarchy_node_attribute_assignments', [
            'hierarchy_node_id' => $node->id,
            'attribute_id' => 'attr-farbe',
        ]);
        $this->assertEquals(1, $result->stats['_hierarchy_attribute_assignments']['created']);

        Event::assertDispatched(\App\Events\HierarchyAttributeChanged::class, function ($event) use ($node) {
            return $event->nodeId === $node->id
                && $event->attributeId === 'attr-farbe'
                && $event->changeType === 'added';
        });
    }

    public function test_does_not_duplicate_existing_hierarchy_node_attribute_assignment(): void
    {
        Attribute::forceCreate([
            'id' => 'attr-groesse',
            'technical_name' => 'groesse',
            'name_de' => 'Größe',
            'data_type' => 'String',
        ]);

        $hierarchy = Hierarchy::forceCreate([
            'id' => 'hier-master-2',
            'technical_name' => 'sortiment2',
            'name_de' => 'Sortiment 2',
            'hierarchy_type' => 'master',
        ]);

        $node = HierarchyNode::forceCreate([
            'id' => 'node-klima',
            'hierarchy_id' => $hierarchy->id,
            'name_de' => 'Klima',
            'path' => '/Klima',
            'depth' => 0,
        ]);

        // Zuordnung existiert bereits (z.B. manuell in der GUI angelegt)
        HierarchyNodeAttributeAssignment::forceCreate([
            'id' => 'hnaa-existing',
            'hierarchy_node_id' => $node->id,
            'attribute_id' => 'attr-groesse',
            'attribute_sort' => 5,
        ]);

        $parseResult = new ParseResult(
            sheetsFound: ['08_Produkte', '09_Produktwerte', '11_Produkt_Hierarchien'],
            data: [
                '08_Produkte' => [
                    2 => [
                        'sku' => 'KL-001', 'name' => 'Klimagerät', 'name_en' => null,
                        'product_type' => 'physical_product', 'ean' => null, 'status' => 'active', '_row' => 2,
                    ],
                ],
                '09_Produktwerte' => [
                    2 => [
                        'sku' => 'KL-001', 'attribute' => 'groesse', 'value' => 'L',
                        'unit' => null, 'language' => null, 'index' => null, '_row' => 2,
                    ],
                ],
                '11_Produkt_Hierarchien' => [
                    2 => ['sku' => 'KL-001', 'hierarchy' => 'sortiment2', 'node_path' => 'Klima', '_row' => 2],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, HierarchyNodeAttributeAssignment::where('hierarchy_node_id', $node->id)
            ->where('attribute_id', 'attr-groesse')->count());
        $this->assertArrayNotHasKey('_hierarchy_attribute_assignments', $result->stats);
    }

    // ──────────────────────────────────────────────
    //  12_Produktbeziehungen
    // ──────────────────────────────────────────────

    public function test_creates_new_product_relation_and_auto_creates_missing_relation_type(): void
    {
        $this->createProduct('SRC-001');
        $this->createProduct('TGT-001');

        $this->assertDatabaseMissing('product_relation_types', ['technical_name' => 'zubehoer']);

        $parseResult = new ParseResult(
            sheetsFound: ['12_Produktbeziehungen'],
            data: [
                '12_Produktbeziehungen' => [
                    2 => [
                        'source_sku' => 'SRC-001', 'target_sku' => 'TGT-001',
                        'relation_type' => 'zubehoer', 'sort_order' => 1, '_row' => 2,
                    ],
                ],
            ],
        );

        $result = $this->executor->execute($parseResult);

        $this->assertEquals(1, $result->stats['12_Produktbeziehungen']['created']);
        $this->assertEquals(0, $result->stats['12_Produktbeziehungen']['skipped']);
        $this->assertEquals(0, $result->stats['12_Produktbeziehungen']['errors']);

        $relationType = ProductRelationType::where('technical_name', 'zubehoer')->first();
        $this->assertNotNull($relationType, 'Beziehungstyp wurde nicht automatisch angelegt');

        $this->assertDatabaseHas('product_relations', [
            'source_product_id' => 'SRC-001',
            'target_product_id' => 'TGT-001',
            'relation_type_id' => $relationType->id,
            'sort_order' => 1,
        ]);
    }

    public function test_reimporting_same_relation_updates_sort_order_without_duplicating(): void
    {
        $this->createProduct('SRC-002');
        $this->createProduct('TGT-002');

        $relationType = ProductRelationType::forceCreate([
            'id' => 'rt-zubehoer',
            'technical_name' => 'zubehoer',
            'name_de' => 'Zubehör',
        ]);

        $baseRow = [
            'source_sku' => 'SRC-002', 'target_sku' => 'TGT-002',
            'relation_type' => 'zubehoer', 'sort_order' => 1, '_row' => 2,
        ];

        $this->executor->execute(new ParseResult(
            sheetsFound: ['12_Produktbeziehungen'],
            data: ['12_Produktbeziehungen' => [2 => $baseRow]],
        ));

        // Erneuter Import derselben Beziehung mit geänderter Sortierung
        $result = $this->executor->execute(new ParseResult(
            sheetsFound: ['12_Produktbeziehungen'],
            data: ['12_Produktbeziehungen' => [2 => array_merge($baseRow, ['sort_order' => 5])]],
        ));

        $this->assertEquals(0, $result->stats['12_Produktbeziehungen']['created']);
        $this->assertEquals(1, $result->stats['12_Produktbeziehungen']['updated']);

        $this->assertEquals(1, ProductRelation::where('source_product_id', 'SRC-002')
            ->where('target_product_id', 'TGT-002')
            ->where('relation_type_id', $relationType->id)
            ->count());
        $this->assertDatabaseHas('product_relations', [
            'source_product_id' => 'SRC-002',
            'target_product_id' => 'TGT-002',
            'relation_type_id' => $relationType->id,
            'sort_order' => 5,
        ]);
    }

    public function test_uses_existing_relation_type_without_creating_duplicate(): void
    {
        $this->createProduct('SRC-003');
        $this->createProduct('TGT-003');

        ProductRelationType::forceCreate([
            'id' => 'rt-existing',
            'technical_name' => 'ersatzteil',
            'name_de' => 'Ersatzteil',
        ]);

        $this->executor->execute(new ParseResult(
            sheetsFound: ['12_Produktbeziehungen'],
            data: [
                '12_Produktbeziehungen' => [
                    2 => [
                        'source_sku' => 'SRC-003', 'target_sku' => 'TGT-003',
                        'relation_type' => 'ersatzteil', 'sort_order' => 0, '_row' => 2,
                    ],
                ],
            ],
        ));

        $this->assertEquals(1, ProductRelationType::where('technical_name', 'ersatzteil')->count());
    }

    public function test_skips_relation_with_unresolvable_sku(): void
    {
        $this->createProduct('SRC-004');
        // TGT-004 existiert absichtlich nicht

        $result = $this->executor->execute(new ParseResult(
            sheetsFound: ['12_Produktbeziehungen'],
            data: [
                '12_Produktbeziehungen' => [
                    2 => [
                        'source_sku' => 'SRC-004', 'target_sku' => 'TGT-004',
                        'relation_type' => 'zubehoer', 'sort_order' => 0, '_row' => 2,
                    ],
                ],
            ],
        ));

        $this->assertEquals(0, $result->stats['12_Produktbeziehungen']['created']);
        $this->assertEquals(1, $result->stats['12_Produktbeziehungen']['skipped']);
        $this->assertDatabaseMissing('product_relations', ['source_product_id' => 'SRC-004']);
        // Der Beziehungstyp wird trotzdem nicht sinnlos angelegt, wenn die Zeile ohnehin übersprungen wird
        $this->assertCount(1, $result->skippedDetails);
        $this->assertStringContainsString("Ziel-SKU 'TGT-004'", $result->skippedDetails[0]['reason']);
    }

    public function test_bulk_import_creates_many_relations_and_auto_creates_relation_types(): void
    {
        // Der Bulk-Pfad schaltet MySQL-Session-Optimierungen (SET SESSION unique_checks=0)
        // ein, die SQLite (lokale Testkonfiguration) nicht kennt — nur unter MySQL testbar.
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Bulk-Import-Pfad benötigt MySQL (SET SESSION unique_checks/foreign_key_checks).');
        }

        // BULK_THRESHOLD liegt bei 500 Gesamtzeilen — mit genug Zeilen läuft
        // importProductRelationsBulk() statt der Einzelzeilen-Methode.
        $rowCount = 520;
        for ($i = 1; $i <= $rowCount; $i++) {
            $this->createProduct("BULK-SRC-{$i}");
        }
        $this->createProduct('BULK-TARGET');

        $rows = [];
        for ($i = 1; $i <= $rowCount; $i++) {
            $rows[$i + 1] = [
                'source_sku' => "BULK-SRC-{$i}",
                'target_sku' => 'BULK-TARGET',
                'relation_type' => 'zubehoer-bulk',
                'sort_order' => $i,
                '_row' => $i + 1,
            ];
        }

        $result = $this->executor->execute(new ParseResult(
            sheetsFound: ['12_Produktbeziehungen'],
            data: ['12_Produktbeziehungen' => $rows],
        ));

        $this->assertEquals($rowCount, $result->stats['12_Produktbeziehungen']['created']);
        $this->assertEquals(0, $result->stats['12_Produktbeziehungen']['errors']);
        $this->assertEquals(1, ProductRelationType::where('technical_name', 'zubehoer-bulk')->count());
        $this->assertEquals($rowCount, ProductRelation::whereHas(
            'relationType',
            fn ($q) => $q->where('technical_name', 'zubehoer-bulk')
        )->count());
    }

    private function createProduct(string $sku): string
    {
        $product = Product::forceCreate([
            'id' => $sku,
            'sku' => $sku,
            'name' => $sku,
            'product_type_id' => $this->productTypeId,
            'status' => 'active',
            'product_type_ref' => 'product',
        ]);

        return $product->id;
    }
}
