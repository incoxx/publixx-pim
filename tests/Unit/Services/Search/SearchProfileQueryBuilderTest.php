<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\SearchProfile;
use App\Services\Search\SearchProfileQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Unit-Tests für den SearchProfileQueryBuilder — die zentrale Stelle,
 * an der Suchprofil-Filter auf Product-Queries angewendet werden.
 */
class SearchProfileQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    private SearchProfileQueryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->builder = new SearchProfileQueryBuilder();
    }

    /** Suchprofil mit Standardwerten anlegen. */
    private function createProfile(array $attrs = []): SearchProfile
    {
        return SearchProfile::create(array_merge([
            'name' => 'Testprofil',
            'is_shared' => true,
        ], $attrs));
    }

    public function test_status_filter_wird_angewendet(): void
    {
        $aktiv = Product::factory()->active()->create();
        Product::factory()->create(['status' => 'draft']);

        $profile = $this->createProfile(['status_filter' => 'active']);

        $treffer = $this->builder->forProducts($profile)->get();

        $this->assertCount(1, $treffer);
        $this->assertSame($aktiv->id, $treffer->first()->id);
    }

    public function test_freitext_suche_filtert_nach_name_sku_ean(): void
    {
        $treffer = Product::factory()->create(['name' => 'Akkubohrer Profi']);
        Product::factory()->create(['name' => 'Hammer', 'sku' => 'HA-001', 'ean' => '1111111111111']);

        $profile = $this->createProfile(['search_text' => 'Akkubohrer']);

        $ids = $this->builder->forProducts($profile)->pluck('id');

        $this->assertSame([$treffer->id], $ids->all());
    }

    public function test_kategorie_filter_mit_unterknoten(): void
    {
        $hierarchy = Hierarchy::factory()->create();
        $root = HierarchyNode::factory()->create(['hierarchy_id' => $hierarchy->id]);
        $root->update(['path' => '/' . $root->id . '/']);
        $child = HierarchyNode::factory()->create([
            'hierarchy_id' => $hierarchy->id,
            'parent_node_id' => $root->id,
            'depth' => 1,
        ]);
        $child->update(['path' => $root->path . $child->id . '/']);

        Product::factory()->create(['master_hierarchy_node_id' => $root->id]);
        Product::factory()->create(['master_hierarchy_node_id' => $child->id]);
        Product::factory()->create();

        $mitUnterknoten = $this->createProfile([
            'category_ids' => [$root->id],
            'include_descendants' => true,
        ]);
        $ohneUnterknoten = $this->createProfile([
            'category_ids' => [$root->id],
            'include_descendants' => false,
        ]);

        $this->assertSame(2, $this->builder->forProducts($mitUnterknoten)->count());
        $this->assertSame(1, (new SearchProfileQueryBuilder())->forProducts($ohneUnterknoten)->count());
    }

    public function test_attribut_filter_im_modernen_format(): void
    {
        $attribut = Attribute::factory()->create(['data_type' => 'String']);
        $rot = Product::factory()->create();
        $blau = Product::factory()->create();
        ProductAttributeValue::factory()->create([
            'product_id' => $rot->id,
            'attribute_id' => $attribut->id,
            'value_string' => 'rot',
        ]);
        ProductAttributeValue::factory()->create([
            'product_id' => $blau->id,
            'attribute_id' => $attribut->id,
            'value_string' => 'blau',
        ]);

        $profile = $this->createProfile([
            'attribute_filters' => [
                ['attribute_id' => $attribut->id, 'value' => 'rot', 'operator' => 'eq'],
            ],
        ]);

        $ids = $this->builder->forProducts($profile)->pluck('id');

        $this->assertSame([$rot->id], $ids->all());
    }

    public function test_attribut_filter_im_legacy_format(): void
    {
        $attribut = Attribute::factory()->create(['data_type' => 'String']);
        $treffer = Product::factory()->create();
        Product::factory()->create();
        ProductAttributeValue::factory()->create([
            'product_id' => $treffer->id,
            'attribute_id' => $attribut->id,
            'value_string' => 'Edelstahl',
        ]);

        // Legacy: {attributeId: wert} statt Liste von Regel-Objekten
        $profile = $this->createProfile([
            'attribute_filters' => [$attribut->id => 'Edelstahl'],
        ]);

        $ids = $this->builder->forProducts($profile)->pluck('id');

        $this->assertSame([$treffer->id], $ids->all());
    }

    public function test_verschachtelte_filtergruppen_mit_or(): void
    {
        $attribut = Attribute::factory()->create(['data_type' => 'String']);
        $rot = Product::factory()->create();
        $blau = Product::factory()->create();
        $gruen = Product::factory()->create();
        foreach ([[$rot, 'rot'], [$blau, 'blau'], [$gruen, 'grün']] as [$produkt, $wert]) {
            ProductAttributeValue::factory()->create([
                'product_id' => $produkt->id,
                'attribute_id' => $attribut->id,
                'value_string' => $wert,
            ]);
        }

        $profile = $this->createProfile([
            'attribute_filter_groups' => [
                'operator' => 'OR',
                'rules' => [
                    ['attribute_id' => $attribut->id, 'value' => 'rot', 'operator' => 'eq'],
                    ['attribute_id' => $attribut->id, 'value' => 'blau', 'operator' => 'eq'],
                ],
            ],
        ]);

        $ids = $this->builder->forProducts($profile)->pluck('id');

        $this->assertEqualsCanonicalizing([$rot->id, $blau->id], $ids->all());
    }

    public function test_for_products_schliesst_varianten_aus(): void
    {
        $haupt = Product::factory()->create();
        Product::factory()->variant()->create();

        $profile = $this->createProfile();

        $ids = $this->builder->forProducts($profile)->pluck('id');

        $this->assertSame([$haupt->id], $ids->all());
    }

    public function test_sortierung_wird_optional_angewendet(): void
    {
        Product::factory()->create(['name' => 'Zange']);
        Product::factory()->create(['name' => 'Akkubohrer']);

        $profile = $this->createProfile([
            'sort_field' => 'name',
            'sort_order' => 'asc',
        ]);

        $namen = $this->builder->forProducts($profile, applySort: true)->pluck('name');

        $this->assertSame(['Akkubohrer', 'Zange'], $namen->all());
    }

    public function test_apply_by_id_liefert_false_ohne_profil(): void
    {
        $query = Product::query();

        $this->assertFalse($this->builder->applyById($query, null));
        $this->assertFalse($this->builder->applyById($query, '00000000-0000-0000-0000-000000000000'));
    }

    public function test_apply_by_id_wendet_vorhandenes_profil_an(): void
    {
        $aktiv = Product::factory()->active()->create();
        Product::factory()->create(['status' => 'draft']);

        $profile = $this->createProfile(['status_filter' => 'active']);

        $query = Product::query();
        $this->assertTrue($this->builder->applyById($query, $profile->id));
        $this->assertSame([$aktiv->id], $query->pluck('id')->all());
    }
}
