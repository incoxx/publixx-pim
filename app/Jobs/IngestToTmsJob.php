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
use App\Models\Language;
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

    /** Anzahl der Batches, die das TMS nicht angenommen hat. */
    private int $failedBatches = 0;

    /** Bereits im PIM gepflegte Uebersetzungen, die gemeldet wurden. */
    private int $importedTranslations = 0;

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

        if ($this->failedBatches > 0) {
            Log::error("TMS ingest: {$this->failedBatches} Batch(es) abgelehnt, {$totalSent} Entitäten gesendet.");

            return [
                'total_sent' => $totalSent,
                'imported_translations' => $this->importedTranslations,
                'failed_batches' => $this->failedBatches,
                'skipped' => false,
                'message' => "{$totalSent} Entitäten gesendet, {$this->failedBatches} Batch(es) abgelehnt — Details im Log.",
            ];
        }

        Log::info("TMS ingest completed: {$totalSent} entities sent, {$this->importedTranslations} vorhandene Übersetzungen gemeldet.");

        return [
            'total_sent' => $totalSent,
            'imported_translations' => $this->importedTranslations,
            'failed_batches' => 0,
            'skipped' => false,
            'message' => "{$totalSent} Entitäten an TMS gesendet, "
                . "{$this->importedTranslations} bereits gepflegte Übersetzungen übernommen.",
        ];
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
        $targetLanguages = Language::targetCodes();
        $sourceLang = Language::sourceCode();

        [$valid, $knownTranslations] = $this->splitBySourceLanguage($entities, $sourceLang, $targetLanguages);

        $skipped = count($entities) - count($valid);
        if ($skipped > 0) {
            Log::debug("TMS ingest: {$skipped} Entitäten ohne übersetzbare Felder übersprungen.");
        }

        // Vorhandene Uebersetzungen ZUERST melden. Der Ingest plant im TMS nur
        // die Sprachen zur Uebersetzung ein, die noch fehlen — ist die englische
        // Fassung vorher bekannt, entfaellt der MT-Aufruf dafuer ganz.
        if (!empty($knownTranslations)) {
            $imported = 0;
            foreach ($client->importTranslations($knownTranslations) as $result) {
                $imported += (int) ($result['imported'] ?? 0);
            }

            $this->importedTranslations += $imported;

            // Ein fehlgeschlagener Batch liefert [] — dann fehlt hier etwas.
            if ($imported < count($knownTranslations)) {
                $this->failedBatches++;
                Log::warning('TMS ingest: ' . (count($knownTranslations) - $imported)
                    . ' vorhandene Übersetzungen wurden nicht übernommen.');
            }
        }

        $sent = 0;

        foreach (array_chunk($valid, $batchSize) as $batch) {
            // Nur zaehlen, was das TMS auch angenommen hat. Frueher wurde jeder
            // Batch als gesendet gezaehlt — bei durchgehend fehlschlagenden
            // Requests meldete der Ingest damit Erfolg, obwohl nichts ankam.
            if ($client->ingest($batch, $targetLanguages)) {
                $sent += count($batch);
            } else {
                $this->failedBatches++;
            }
        }

        return $sent;
    }

    /**
     * Trennt Quelltexte von bereits gepflegten Uebersetzungen.
     *
     * Ein Uebersetzungs-Segment wird ueber sha256("sprache|text") identifiziert.
     * Frueher gingen name_de UND name_en als eigenstaendige Quell-Segmente raus
     * — damit lagen zwei Units im TM, obwohl der Rueckweg
     * (SyncTmsTranslationsJob) ausschliesslich die Quellsprache nachschlaegt.
     * Die englischen Units las also nie jemand, sie kosteten aber je Begriff
     * eine weitere Runde maschineller Uebersetzung in alle uebrigen Sprachen.
     *
     * Jetzt gilt: die Quellsprache bildet die Unit, jede andere gepflegte
     * Sprache wird als deren fertige Uebersetzung gemeldet. Die Zuordnung
     * laeuft ueber den Feldnamen ohne Sprachsuffix (name_de/name_en -> name).
     *
     * @param  array<int, array<string, mixed>>  $entities
     * @param  string[]  $targetLanguages
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function splitBySourceLanguage(array $entities, string $sourceLang, array $targetLanguages): array
    {
        $sourceEntities = [];
        $knownTranslations = [];

        foreach ($entities as $entity) {
            $sourceFields = [];
            $sourceTextByBase = [];
            $sourceFieldByBase = [];
            $secondary = [];

            foreach ($entity['fields'] ?? [] as $field) {
                if (empty($field['text'])) {
                    continue;
                }

                // Ohne Sprachangabe gilt der Wert als Quelltext — sonst wuerde
                // ein aelterer Aufrufer stillschweigend nichts mehr liefern.
                $lang = $field['lang'] ?? $sourceLang;
                $base = $this->fieldBase((string) $field['field'], $lang);

                if ($lang === $sourceLang) {
                    $sourceFields[] = $field;
                    $sourceTextByBase[$base] = $field['text'];
                    $sourceFieldByBase[$base] = $field['field'];
                } else {
                    $secondary[] = ['base' => $base, 'lang' => $lang, 'text' => $field['text']];
                }
            }

            if (empty($sourceFields)) {
                continue;
            }

            $sourceEntities[] = array_merge($entity, ['fields' => $sourceFields]);

            foreach ($secondary as $item) {
                // Nur aktive Zielsprachen, und nur wenn der zugehoerige
                // Quelltext existiert — ohne ihn gibt es keine Unit, der die
                // Uebersetzung zugeordnet werden koennte.
                if (!in_array($item['lang'], $targetLanguages, true)
                    || !isset($sourceTextByBase[$item['base']])) {
                    continue;
                }

                $knownTranslations[] = [
                    'source_text' => $sourceTextByBase[$item['base']],
                    'source_lang' => $sourceLang,
                    'target_lang' => $item['lang'],
                    'translated_text' => $item['text'],
                    'entity_type' => $entity['entity_type'],
                    'entity_id' => $entity['entity_id'],
                    // Derselbe Feldname wie beim Ingest, damit keine zweite
                    // Usage-Zeile fuer dieselbe Stelle entsteht.
                    'field_name' => $sourceFieldByBase[$item['base']],
                    'entity_label' => $entity['entity_label'] ?? null,
                    'provider' => 'import',
                ];
            }
        }

        return [array_values($sourceEntities), $knownTranslations];
    }

    /**
     * Feldname ohne Sprachsuffix: name_de -> name, display_value_en -> display_value.
     */
    private function fieldBase(string $field, string $lang): string
    {
        $suffix = '_' . $lang;

        return str_ends_with($field, $suffix)
            ? substr($field, 0, -strlen($suffix))
            : $field;
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
