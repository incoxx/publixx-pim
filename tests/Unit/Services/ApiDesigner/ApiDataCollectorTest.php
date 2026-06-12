<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ApiDesigner;

use App\Models\ApiTemplate;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\ApiDesigner\ApiDataCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-Tests für den ApiDataCollector (Datensammlung + Gruppierung für API-Templates).
 */
class ApiDataCollectorTest extends TestCase
{
    use RefreshDatabase;

    private ApiDataCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = app(ApiDataCollector::class);
    }

    /** Template (nicht persistiert) mit gegebener groups-Struktur bauen. */
    private function makeTemplate(array $groups, string $language = 'de'): ApiTemplate
    {
        $template = new ApiTemplate([
            'name' => 'Collector Test',
            'template_json' => ['version' => 1, 'groups' => $groups],
            'language' => $language,
        ]);

        return $template;
    }

    /** Eine Gruppe ohne Gruppierungsfeld mit den gegebenen Detail-Elementen. */
    private function flatGroup(array $detailElements = []): array
    {
        return [
            'id' => 'g1',
            'field' => 'none',
            'label' => 'Alle',
            'sortOrder' => 'asc',
            'header' => ['elements' => []],
            'footer' => ['elements' => []],
            'groups' => [],
            'detail' => ['elements' => $detailElements],
        ];
    }

    public function test_collect_liefert_nur_aktive_produkte_mit_total_und_count(): void
    {
        Product::factory()->active()->count(3)->create();
        Product::factory()->create(['status' => 'draft']);

        $template = $this->makeTemplate([$this->flatGroup()]);

        $result = $this->collector->collect($template);

        $this->assertSame(3, $result['total']);
        $this->assertSame(3, $result['count']);
        $this->assertCount(1, $result['grouped']);
        $this->assertCount(3, $result['grouped'][0]['products']);
    }

    public function test_collect_wendet_limit_und_offset_an(): void
    {
        foreach (['A-1', 'B-2', 'C-3'] as $sku) {
            Product::factory()->active()->create(['sku' => $sku]);
        }

        $template = $this->makeTemplate([$this->flatGroup()]);

        $result = $this->collector->collect($template, null, limit: 2, offset: 1, sortField: 'sku', sortOrder: 'asc');

        // total bleibt die Gesamtmenge, count die Seitengröße
        $this->assertSame(3, $result['total']);
        $this->assertSame(2, $result['count']);
        $this->assertSame(1, $result['offset']);
        $this->assertSame(2, $result['limit']);

        $skus = array_map(fn (Product $p) => $p->sku, $result['grouped'][0]['products']);
        $this->assertSame(['B-2', 'C-3'], $skus);
    }

    public function test_collect_sortiert_nach_erlaubtem_feld_absteigend(): void
    {
        foreach (['A-1', 'C-3', 'B-2'] as $sku) {
            Product::factory()->active()->create(['sku' => $sku]);
        }

        $template = $this->makeTemplate([$this->flatGroup()]);

        $result = $this->collector->collect($template, null, sortField: 'sku', sortOrder: 'desc');

        $skus = array_map(fn (Product $p) => $p->sku, $result['grouped'][0]['products']);
        $this->assertSame(['C-3', 'B-2', 'A-1'], $skus);
    }

    public function test_collect_filtert_mit_since_auf_updated_at(): void
    {
        $alt = Product::factory()->active()->create(['sku' => 'ALT-1']);
        $neu = Product::factory()->active()->create(['sku' => 'NEU-1']);

        // updated_at gezielt setzen (Timestamps ohne Model-Events)
        Product::where('id', $alt->id)->update(['updated_at' => now()->subDays(10)]);
        Product::where('id', $neu->id)->update(['updated_at' => now()]);

        $template = $this->makeTemplate([$this->flatGroup()]);

        $result = $this->collector->collect($template, null, since: new \DateTimeImmutable('-1 day'));

        $this->assertSame(1, $result['total']);
        $this->assertSame('NEU-1', $result['grouped'][0]['products'][0]->sku);
    }

    public function test_collect_gruppiert_nach_status(): void
    {
        Product::factory()->active()->count(2)->create();
        Product::factory()->create(['status' => 'draft']);

        $groupDef = $this->flatGroup();
        $groupDef['field'] = 'status';

        $template = $this->makeTemplate([$groupDef]);

        // Kein SearchProfile, aber Gruppierung nach Status: Basis-Query filtert auf active
        $result = $this->collector->collect($template);

        $this->assertCount(1, $result['grouped']);
        $this->assertSame('active', $result['grouped'][0]['value']);
        $this->assertSame(2, $result['grouped'][0]['count']);
    }

    public function test_collect_gruppiert_nach_attributwert(): void
    {
        $attribute = Attribute::factory()->create(['data_type' => 'String']);

        $rot1 = Product::factory()->active()->create(['sku' => 'ROT-1']);
        $rot2 = Product::factory()->active()->create(['sku' => 'ROT-2']);
        $blau = Product::factory()->active()->create(['sku' => 'BLAU-1']);

        foreach ([[$rot1, 'Rot'], [$rot2, 'Rot'], [$blau, 'Blau']] as [$product, $farbe]) {
            ProductAttributeValue::factory()->create([
                'product_id' => $product->id,
                'attribute_id' => $attribute->id,
                'value_string' => $farbe,
            ]);
        }

        $groupDef = $this->flatGroup();
        $groupDef['field'] = "attribute:{$attribute->id}";

        $result = $this->collector->collect($this->makeTemplate([$groupDef]));

        // Sortierung asc → Blau vor Rot
        $this->assertCount(2, $result['grouped']);
        $this->assertSame('Blau', $result['grouped'][0]['value']);
        $this->assertSame(1, $result['grouped'][0]['count']);
        $this->assertSame('Rot', $result['grouped'][1]['value']);
        $this->assertSame(2, $result['grouped'][1]['count']);
    }

    public function test_collect_laedt_attribut_relationen_nur_bei_attribut_elementen(): void
    {
        Product::factory()->active()->create();

        // Template ohne Attribut-Elemente → attributeValues nicht eager-geladen
        $ohneAttribute = $this->collector->collect($this->makeTemplate([$this->flatGroup([
            ['type' => 'field', 'field' => 'sku', 'jsonKey' => 'sku'],
        ])]));
        $this->assertFalse($ohneAttribute['grouped'][0]['products'][0]->relationLoaded('attributeValues'));

        // Template mit Attribut-Element → attributeValues eager-geladen
        $mitAttributen = $this->collector->collect($this->makeTemplate([$this->flatGroup([
            ['type' => 'attribute', 'attributeId' => 'irgendeine-id', 'jsonKey' => 'farbe'],
        ])]));
        $this->assertTrue($mitAttributen['grouped'][0]['products'][0]->relationLoaded('attributeValues'));
    }

    public function test_collect_ohne_gruppen_liefert_fallback_gruppe(): void
    {
        Product::factory()->active()->count(2)->create();

        $template = $this->makeTemplate([]); // keine groups definiert

        $result = $this->collector->collect($template);

        $this->assertCount(1, $result['grouped']);
        $this->assertSame('', $result['grouped'][0]['value']);
        $this->assertSame(2, $result['grouped'][0]['count']);
        $this->assertCount(2, $result['grouped'][0]['products']);
    }

    public function test_collect_by_ids_behaelt_reihenfolge_bei(): void
    {
        $a = Product::factory()->active()->create(['sku' => 'A-1']);
        $b = Product::factory()->active()->create(['sku' => 'B-2']);
        $c = Product::factory()->active()->create(['sku' => 'C-3']);

        $template = $this->makeTemplate([$this->flatGroup()]);

        // Bewusst gemischte Reihenfolge
        $result = $this->collector->collectByIds($template, [$c->id, $a->id, $b->id]);

        $this->assertSame(3, $result['total']);
        $this->assertSame(3, $result['count']);

        $skus = array_map(fn (Product $p) => $p->sku, $result['grouped'][0]['products']);
        $this->assertSame(['C-3', 'A-1', 'B-2'], $skus);
    }

    public function test_collect_by_ids_mit_leerer_liste_liefert_leeres_ergebnis(): void
    {
        $template = $this->makeTemplate([$this->flatGroup()]);

        $result = $this->collector->collectByIds($template, []);

        $this->assertSame([], $result['grouped']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['count']);
    }
}
