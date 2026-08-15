<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attribute;
use App\Models\AttributeType;
use App\Models\AttributeView;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\PriceType;
use App\Models\ProductRelationType;
use App\Models\ProductType;
use App\Models\UnitGroup;
use App\Models\ValueList;
use App\Services\Tms\TmsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IngestToTmsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 60];
    public int $uniqueFor = 300; // 5 minutes

    public function handle(TmsClient $client): array
    {
        if (!$client->isEnabled()) {
            Log::info('TMS ingest skipped — TMS is disabled.');
            return ['total_sent' => 0, 'skipped' => true, 'message' => 'TMS is disabled.'];
        }

        $entities = [];
        $batchSize = 200;
        $totalSent = 0;

        // Helper: flush entities in batches
        $flush = function () use ($client, &$entities, $batchSize, &$totalSent) {
            if (count($entities) >= $batchSize) {
                $totalSent += $this->sendBatches($client, $entities, $batchSize);
                $entities = [];
            }
        };

        // Unit Groups (typically small, < 50)
        UnitGroup::chunk(500, function ($groups) use (&$entities) {
            foreach ($groups as $g) {
                $entities[] = $this->buildEntity('unit_group', $g->id, $g->technical_name, [
                    ['field' => 'name_de', 'text' => $g->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $g->name_en, 'lang' => 'en'],
                ]);
            }
        });

        // Units: abbreviations are international symbols, skip TMS ingestion
        // (e.g. "kg", "mm", "m/s" don't need translation)

        // Attribute Views
        AttributeView::chunk(500, function ($views) use (&$entities) {
            foreach ($views as $v) {
                $entities[] = $this->buildEntity('attribute_view', $v->id, $v->technical_name, [
                    ['field' => 'name_de', 'text' => $v->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $v->name_en, 'lang' => 'en'],
                ]);
            }
        });

        // Attribute Groups (Types)
        AttributeType::chunk(500, function ($groups) use (&$entities) {
            foreach ($groups as $g) {
                $entities[] = $this->buildEntity('attribute_group', $g->id, $g->technical_name, [
                    ['field' => 'name_de', 'text' => $g->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $g->name_en, 'lang' => 'en'],
                ]);
            }
        });

        // Value Lists + Entries (can be large)
        ValueList::with('entries')->chunk(500, function ($lists) use (&$entities, $flush) {
            foreach ($lists as $list) {
                $entities[] = $this->buildEntity('value_list', $list->id, $list->technical_name, [
                    ['field' => 'name_de', 'text' => $list->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $list->name_en, 'lang' => 'en'],
                ]);

                foreach ($list->entries as $e) {
                    $entities[] = $this->buildEntity('value_list_entry', $e->id, "{$list->technical_name}.{$e->technical_name}", [
                        ['field' => 'display_value_de', 'text' => $e->display_value_de, 'lang' => 'de'],
                        ['field' => 'display_value_en', 'text' => $e->display_value_en, 'lang' => 'en'],
                    ]);
                }
            }
            $flush();
        });

        // Attributes (can be large)
        Attribute::chunk(500, function ($attrs) use (&$entities, $flush) {
            foreach ($attrs as $a) {
                $entities[] = $this->buildEntity('attribute', $a->id, $a->technical_name, [
                    ['field' => 'name_de', 'text' => $a->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $a->name_en, 'lang' => 'en'],
                ]);
            }
            $flush();
        });

        // Product Types
        ProductType::chunk(500, function ($types) use (&$entities) {
            foreach ($types as $t) {
                $entities[] = $this->buildEntity('product_type', $t->id, $t->technical_name, [
                    ['field' => 'name_de', 'text' => $t->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $t->name_en, 'lang' => 'en'],
                ]);
            }
        });

        // Price Types
        PriceType::chunk(500, function ($types) use (&$entities) {
            foreach ($types as $p) {
                $entities[] = $this->buildEntity('price_type', $p->id, $p->technical_name, [
                    ['field' => 'name_de', 'text' => $p->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $p->name_en, 'lang' => 'en'],
                ]);
            }
        });

        // Relation Types
        ProductRelationType::chunk(500, function ($types) use (&$entities) {
            foreach ($types as $r) {
                $entities[] = $this->buildEntity('relation_type', $r->id, $r->technical_name, [
                    ['field' => 'name_de', 'text' => $r->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $r->name_en, 'lang' => 'en'],
                ]);
            }
        });

        // Hierarchies
        Hierarchy::chunk(500, function ($hierarchies) use (&$entities) {
            foreach ($hierarchies as $h) {
                $entities[] = $this->buildEntity('hierarchy', $h->id, $h->technical_name, [
                    ['field' => 'name_de', 'text' => $h->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $h->name_en, 'lang' => 'en'],
                ]);
            }
        });

        // Hierarchy Nodes (can be very large)
        HierarchyNode::where('depth', '>', 0)->chunk(500, function ($nodes) use (&$entities, $flush) {
            foreach ($nodes as $n) {
                $entities[] = $this->buildEntity('hierarchy_node', $n->id, $n->name_de ?? $n->id, [
                    ['field' => 'name_de', 'text' => $n->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $n->name_en, 'lang' => 'en'],
                ]);
            }
            $flush();
        });

        // Restbestand senden
        $totalSent += $this->sendBatches($client, $entities, $batchSize);

        Log::info("TMS ingest completed: {$totalSent} entities sent.");

        return ['total_sent' => $totalSent, 'skipped' => false, 'message' => "{$totalSent} Entitäten an TMS gesendet."];
    }

    /**
     * Sendet Entitäten in Batches an das TMS.
     *
     * Entitäten ohne Felder werden hier — und nicht erst am Ende des Jobs —
     * aussortiert: der IngestController validiert `entities.*.fields` mit
     * `required|array|min:1`, eine einzige feldlose Entität lässt sonst den
     * kompletten Batch mit 422 scheitern und der TmsClient verschluckt das
     * still. Betroffen waren genau die Zwischen-Flushes der großen Tabellen
     * (Attribute, Wertelisten, Hierarchie-Knoten).
     *
     * @param  array<int, array<string, mixed>>  $entities
     * @return int  Anzahl tatsächlich gesendeter Entitäten
     */
    private function sendBatches(TmsClient $client, array $entities, int $batchSize): int
    {
        $valid = array_values(array_filter($entities, fn ($e) => !empty($e['fields'])));

        $skipped = count($entities) - count($valid);
        if ($skipped > 0) {
            Log::debug("TMS ingest: {$skipped} Entitäten ohne übersetzbare Felder übersprungen.");
        }

        $sent = 0;
        foreach (array_chunk($valid, $batchSize) as $batch) {
            $client->ingest($batch);
            $sent += count($batch);
        }

        return $sent;
    }

    private function buildEntity(string $type, string $id, string $label, array $fields): array
    {
        return [
            'entity_type' => $type,
            'entity_id' => $id,
            'entity_label' => $label,
            // array_values: array_filter erhält die Schlüssel — bei einer Lücke
            // (z.B. name_de leer, name_en gesetzt) würde json_encode sonst ein
            // Objekt {"1": ...} statt einer Liste erzeugen.
            'fields' => array_values(array_filter($fields, fn ($f) => !empty($f['text']))),
        ];
    }
}
