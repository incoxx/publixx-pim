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
use App\Models\Unit;
use App\Models\ValueList;
use App\Models\ValueListEntry;
use App\Services\Tms\TmsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IngestToTmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 60];

    public function handle(TmsClient $client): void
    {
        if (!$client->isEnabled()) {
            Log::info('TMS ingest skipped — TMS is disabled.');
            return;
        }

        $entities = [];

        // Unit Groups
        foreach (UnitGroup::all() as $g) {
            $entities[] = $this->buildEntity('unit_group', $g->id, $g->technical_name, [
                ['field' => 'name_de', 'text' => $g->name_de, 'lang' => 'de'],
                ['field' => 'name_en', 'text' => $g->name_en, 'lang' => 'en'],
            ]);
        }

        // Units
        foreach (Unit::all() as $u) {
            $fields = [
                ['field' => 'abbreviation', 'text' => $u->abbreviation, 'lang' => 'de'],
            ];
            $entities[] = $this->buildEntity('unit', $u->id, $u->technical_name, $fields);
        }

        // Attribute Views
        foreach (AttributeView::all() as $v) {
            $entities[] = $this->buildEntity('attribute_view', $v->id, $v->technical_name, [
                ['field' => 'name_de', 'text' => $v->name_de, 'lang' => 'de'],
                ['field' => 'name_en', 'text' => $v->name_en, 'lang' => 'en'],
            ]);
        }

        // Attribute Groups (Types)
        foreach (AttributeType::all() as $g) {
            $entities[] = $this->buildEntity('attribute_group', $g->id, $g->technical_name, [
                ['field' => 'name_de', 'text' => $g->name_de, 'lang' => 'de'],
                ['field' => 'name_en', 'text' => $g->name_en, 'lang' => 'en'],
            ]);
        }

        // Value Lists
        foreach (ValueList::with('entries')->get() as $list) {
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

        // Attributes
        foreach (Attribute::all() as $a) {
            $entities[] = $this->buildEntity('attribute', $a->id, $a->technical_name, [
                ['field' => 'name_de', 'text' => $a->name_de, 'lang' => 'de'],
                ['field' => 'name_en', 'text' => $a->name_en, 'lang' => 'en'],
            ]);
        }

        // Product Types
        foreach (ProductType::all() as $t) {
            $entities[] = $this->buildEntity('product_type', $t->id, $t->technical_name, [
                ['field' => 'name_de', 'text' => $t->name_de, 'lang' => 'de'],
                ['field' => 'name_en', 'text' => $t->name_en, 'lang' => 'en'],
            ]);
        }

        // Price Types
        foreach (PriceType::all() as $p) {
            $entities[] = $this->buildEntity('price_type', $p->id, $p->technical_name, [
                ['field' => 'name_de', 'text' => $p->name_de, 'lang' => 'de'],
                ['field' => 'name_en', 'text' => $p->name_en, 'lang' => 'en'],
            ]);
        }

        // Relation Types
        foreach (ProductRelationType::all() as $r) {
            $entities[] = $this->buildEntity('relation_type', $r->id, $r->technical_name, [
                ['field' => 'name_de', 'text' => $r->name_de, 'lang' => 'de'],
                ['field' => 'name_en', 'text' => $r->name_en, 'lang' => 'en'],
            ]);
        }

        // Hierarchies
        foreach (Hierarchy::all() as $h) {
            $entities[] = $this->buildEntity('hierarchy', $h->id, $h->technical_name, [
                ['field' => 'name_de', 'text' => $h->name_de, 'lang' => 'de'],
                ['field' => 'name_en', 'text' => $h->name_en, 'lang' => 'en'],
            ]);
        }

        // Hierarchy Nodes
        HierarchyNode::where('depth', '>', 0)->chunk(500, function ($nodes) use (&$entities) {
            foreach ($nodes as $n) {
                $entities[] = $this->buildEntity('hierarchy_node', $n->id, $n->name_de ?? $n->id, [
                    ['field' => 'name_de', 'text' => $n->name_de, 'lang' => 'de'],
                    ['field' => 'name_en', 'text' => $n->name_en, 'lang' => 'en'],
                ]);
            }
        });

        // Filter out entities with empty source text
        $entities = array_values(array_filter($entities, function ($entity) {
            return collect($entity['fields'])->contains(fn ($f) => !empty($f['text']));
        }));

        Log::info("TMS ingest: sending " . count($entities) . " entities");

        // Send in batches of 200
        foreach (array_chunk($entities, 200) as $batch) {
            $client->ingest($batch);
        }

        Log::info('TMS ingest completed.');
    }

    private function buildEntity(string $type, string $id, string $label, array $fields): array
    {
        return [
            'entity_type' => $type,
            'entity_id' => $id,
            'entity_label' => $label,
            'fields' => array_filter($fields, fn ($f) => !empty($f['text'])),
        ];
    }
}
