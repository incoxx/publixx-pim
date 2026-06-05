<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ApiDesigner\GraphqlSchemaBuilder;
use Tests\TestCase;

class GraphqlSchemaBuilderTest extends TestCase
{
    private function templateWithPriceAndMedia(): array
    {
        return [
            'version' => 1,
            'groups'  => [[
                'id'     => 'g1',
                'field'  => 'none',
                'label'  => 'Test',
                'sortOrder'    => 'asc',
                'detailLayout' => 'table',
                'header' => ['elements' => []],
                'footer' => ['elements' => []],
                'groups' => [],
                'detail' => ['elements' => [
                    ['type' => 'field',    'field' => 'sku',        'jsonKey' => 'sku',        'dataType' => 'string'],
                    ['type' => 'price',    'priceTypeId' => 'pt-1', 'jsonKey' => 'list_price'],
                    ['type' => 'media',    'usageTypeId' => 'mt-1', 'jsonKey' => 'gallery',    'mediaMode' => 'array'],
                    ['type' => 'relation', 'relationTypeId' => 'rt-1', 'jsonKey' => 'accessories'],
                ]],
            ]],
        ];
    }

    /**
     * Zwei aufeinanderfolgende Schema-Builds müssen ohne "Duplicate type"-Exception
     * durchlaufen. Mit `static $type = null` in Instanzmethoden scheiterte der
     * zweite Aufruf weil webonyx/graphql-php dieselbe ObjectType-Instanz mit
     * gleichem Namen erneut registrierte.
     */
    public function test_schema_can_be_built_twice_without_duplicate_type_error(): void
    {
        $template = $this->templateWithPriceAndMedia();
        $builder  = app(GraphqlSchemaBuilder::class);

        // Erster Build — darf nicht werfen
        $schema1 = $builder->build($template, 'de');
        $this->assertNotNull($schema1);

        // Zweiter Build auf derselben Builder-Instanz — darf nicht mit
        // "Duplicate type" werfen
        $schema2 = $builder->build($template, 'de');
        $this->assertNotNull($schema2);
    }

    /**
     * Zwei Builder-Instanzen dürfen parallel Schemas mit identischen Typnamen
     * erzeugen (typischer Szenario bei gleichzeitigen Requests im selben FPM-Worker).
     */
    public function test_two_builder_instances_can_build_schemas_independently(): void
    {
        $template = $this->templateWithPriceAndMedia();

        $schema1 = app(GraphqlSchemaBuilder::class)->build($template, 'de');
        $schema2 = app(GraphqlSchemaBuilder::class)->build($template, 'de');

        $this->assertNotNull($schema1);
        $this->assertNotNull($schema2);
    }

    public function test_schema_contains_price_media_and_relation_types(): void
    {
        $template = $this->templateWithPriceAndMedia();
        $builder  = app(GraphqlSchemaBuilder::class);
        $schema   = $builder->build($template, 'de');

        // Schema muss die generierten Typen enthalten
        $typeMap = $schema->getTypeMap();
        $this->assertArrayHasKey('Price', $typeMap);
        $this->assertArrayHasKey('MediaItem', $typeMap);
        $this->assertArrayHasKey('RelationItem', $typeMap);
    }
}
