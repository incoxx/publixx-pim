<?php

declare(strict_types=1);

namespace Tests\Feature\Pql;

use App\Models\Product;
use App\Models\Tag;
use App\Services\Pql\FuzzyMatcher;
use App\Services\Pql\PqlExecutor;
use App\Services\Pql\PqlParser;
use App\Services\Pql\PqlSqlGenerator;
use App\Services\Pql\PqlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PQL-Feld `tags`.
 *
 * Tags liegen in einer Zuordnungstabelle statt in einer Spalte — der Generator
 * löst sie über eine EXISTS-Unterabfrage auf. Getestet wird end-to-end über den
 * Executor, damit auch der JOIN auf products_search_index abgedeckt ist.
 */
final class PqlTagsTest extends TestCase
{
    use RefreshDatabase;

    private PqlExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->executor = new PqlExecutor(
            parser: new PqlParser(),
            validator: new PqlValidator(),
            generator: new PqlSqlGenerator(),
            fuzzyMatcher: new FuzzyMatcher(),
        );
    }

    /** Produkt inkl. Suchindex-Eintrag (der Generator joint products_search_index). */
    private function indexedProduct(string $sku, array $tagIds = []): Product
    {
        $product = Product::factory()->active()->create(['sku' => $sku, 'name' => $sku]);

        DB::table('products_search_index')->insert([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'name_de' => $product->name,
            'status' => $product->status,
        ]);

        if ($tagIds !== []) {
            $product->tags()->attach($tagIds);
        }

        return $product;
    }

    /** @return array<int, string> SKUs der Treffer */
    private function skus(string $pql): array
    {
        $result = $this->executor->execute($pql);
        $skus = array_map(static fn ($row) => $row['sku'] ?? null, $result['data']);
        sort($skus);

        return $skus;
    }

    public function test_gleichheit_findet_ueber_technischen_namen(): void
    {
        $tag = Tag::factory()->create(['technical_name' => 'neuheit', 'name_de' => 'Neuheit']);
        $this->indexedProduct('MIT', [$tag->id]);
        $this->indexedProduct('OHNE');

        $this->assertSame(['MIT'], $this->skus('SELECT * WHERE tags = "neuheit"'));
    }

    public function test_gleichheit_findet_auch_ueber_anzeigenamen(): void
    {
        $tag = Tag::factory()->create(['technical_name' => 'neuheit', 'name_de' => 'Neuheit']);
        $this->indexedProduct('MIT', [$tag->id]);
        $this->indexedProduct('OHNE');

        $this->assertSame(['MIT'], $this->skus('SELECT * WHERE tags = "Neuheit"'));
    }

    public function test_in_findet_produkte_mit_einem_der_tags(): void
    {
        $neuheit = Tag::factory()->create(['technical_name' => 'neuheit']);
        $aktion = Tag::factory()->create(['technical_name' => 'aktion']);
        $this->indexedProduct('A', [$neuheit->id]);
        $this->indexedProduct('B', [$aktion->id]);
        $this->indexedProduct('C');

        $this->assertSame(['A', 'B'], $this->skus('SELECT * WHERE tags IN ("neuheit", "aktion")'));
    }

    public function test_ungleich_schliesst_produkte_mit_dem_tag_aus(): void
    {
        $neuheit = Tag::factory()->create(['technical_name' => 'neuheit']);
        $this->indexedProduct('MIT', [$neuheit->id]);
        $this->indexedProduct('OHNE');

        $this->assertSame(['OHNE'], $this->skus('SELECT * WHERE tags != "neuheit"'));
    }

    public function test_like_findet_ueber_namensteil(): void
    {
        $tag = Tag::factory()->create(['technical_name' => 'fuer-aussenbereich', 'name_de' => 'Für Außenbereich']);
        $this->indexedProduct('MIT', [$tag->id]);
        $this->indexedProduct('OHNE');

        $this->assertSame(['MIT'], $this->skus('SELECT * WHERE tags LIKE "%aussen%"'));
    }

    public function test_exists_trennt_getaggte_von_ungetaggten_produkten(): void
    {
        $tag = Tag::factory()->create();
        $this->indexedProduct('MIT', [$tag->id]);
        $this->indexedProduct('OHNE');

        $this->assertSame(['MIT'], $this->skus('SELECT * WHERE tags EXISTS'));
        $this->assertSame(['OHNE'], $this->skus('SELECT * WHERE tags NOT EXISTS'));
    }

    public function test_kombination_mit_anderen_feldern(): void
    {
        $tag = Tag::factory()->create(['technical_name' => 'neuheit']);
        $aktiv = $this->indexedProduct('AKTIV', [$tag->id]);
        $entwurf = $this->indexedProduct('ENTWURF', [$tag->id]);

        $entwurf->update(['status' => 'draft']);
        DB::table('products_search_index')->where('product_id', $entwurf->id)->update(['status' => 'draft']);
        $this->assertSame('active', $aktiv->fresh()->status);

        $this->assertSame(
            ['AKTIV'],
            $this->skus('SELECT * WHERE tags = "neuheit" AND status = "active"'),
        );
    }

    public function test_ein_produkt_erscheint_trotz_mehrerer_treffer_nur_einmal(): void
    {
        $neuheit = Tag::factory()->create(['technical_name' => 'neuheit']);
        $aktion = Tag::factory()->create(['technical_name' => 'aktion']);
        $this->indexedProduct('MEHRFACH', [$neuheit->id, $aktion->id]);

        // EXISTS statt JOIN — sonst käme das Produkt je Tag einmal zurück
        $this->assertSame(['MEHRFACH'], $this->skus('SELECT * WHERE tags IN ("neuheit", "aktion")'));
    }

    public function test_tags_ist_ein_bekanntes_feld(): void
    {
        $result = $this->executor->validate('SELECT * WHERE tags = "neuheit"');

        $this->assertTrue($result['valid'], json_encode($result['errors']));
    }

    public function test_fuzzy_auf_tags_wird_mit_hinweis_abgelehnt(): void
    {
        $result = $this->executor->validate('SELECT * WHERE tags FUZZY "neuheit"');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tags LIKE', $result['errors'][0]['error']);
    }


    public function test_search_fields_auf_tags_wird_abgelehnt(): void
    {
        // Ohne Guard validiert die Abfrage, erzeugt eine leere Bedingung und
        // liefert damit ALLE Produkte statt der getaggten.
        $result = $this->executor->validate('SELECT * WHERE SEARCH_FIELDS(tags) FUZZY "neuheit"');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tags LIKE', $result['errors'][0]['error']);
    }

    public function test_sounds_like_auf_tags_wird_abgelehnt(): void
    {
        $result = $this->executor->validate('SELECT * WHERE tags SOUNDS LIKE "nojheit"');

        $this->assertFalse($result['valid']);
    }
}
