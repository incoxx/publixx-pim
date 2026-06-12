<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Attribute;
use App\Models\Unit;
use Illuminate\Support\Facades\Cache;

/**
 * Such-Schema aus dem PIM — Single Source of Truth für Filter-Vokabular,
 * Einheiten und Synonyme der semantischen Schnellsuche.
 *
 * Das Schema wird aus den Attributen (list_attributes-Datenbasis) und dem
 * Einheiten-Modell gebaut und gecacht — nicht pro Anfrage abgefragt.
 * Config-Ergänzungen (unit_aliases, concept_synonyms) überbrücken Lücken
 * im Datenmodell (z. B. bmecat-Importe ohne unit_group).
 */
class SearchSchemaService
{
    private const CACHE_KEY = 'meilisearch:search-schema';

    /** Vorberechnetes Schema (nur für Tests). */
    public function __construct(private readonly ?array $preloaded = null)
    {
    }

    /**
     * Gecachtes Schema liefern.
     *
     * Struktur:
     * - attributes:      technical_name => {field, data_type, unit_group_id, default_to_base, aliases}
     * - units:           token => {group_id|attributes, to_base, currency?}
     * - unit_attributes: unit_group_id => [technical_name, ...]
     * - concepts:        kontextwort => [technical_name, ...]
     */
    public function schema(): array
    {
        if ($this->preloaded !== null) {
            return $this->preloaded;
        }

        return Cache::remember(
            self::CACHE_KEY,
            (int) config('meilisearch.schema_cache_ttl', 3600),
            fn (): array => $this->build(),
        );
    }

    /**
     * Schema-Cache invalidieren (z. B. nach Attribut-Änderungen).
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Meilisearch-Feldname für ein Attribut (Bindestriche → Unterstriche,
     * damit Filter-Ausdrücke ohne Quoting funktionieren).
     */
    public static function fieldName(string $technicalName): string
    {
        return 'attr_' . str_replace('-', '_', $technicalName);
    }

    /**
     * Komplettes Settings-Payload für den Meilisearch-Index, abgeleitet
     * aus dem Schema (Filter, Facetten, Synonyme, Embedder).
     */
    public function meilisearchSettings(): array
    {
        $schema = $this->schema();

        $attrFields = [];
        $numericFields = [];
        foreach ($schema['attributes'] as $attr) {
            $attrFields[] = $attr['field'];
            if ($attr['data_type'] === 'Number') {
                $numericFields[] = $attr['field'];
            }
        }

        $settings = [
            'searchableAttributes' => [
                'product_name',
                'name_de',
                'name_en',
                'sku',
                'ean',
                'hierarchy_path',
                'description_de',
                'searchable_text',
                'media_text',
            ],
            'filterableAttributes' => array_values(array_unique(array_merge(
                ['status', 'product_type', 'product_type_ref', 'hierarchy_path', 'hierarchy_path_ids', 'list_price'],
                $attrFields,
            ))),
            'sortableAttributes' => array_values(array_unique(array_merge(
                ['list_price', 'updated_at', 'attribute_completeness'],
                $numericFields,
            ))),
            'displayedAttributes' => ['*'],
            'synonyms' => $this->buildSynonyms($schema),
        ];

        if (config('meilisearch.embedder.enabled')) {
            $settings['embedders'] = [
                (string) config('meilisearch.embedder.name', 'products') => $this->embedderConfig(),
            ];
        }

        return $settings;
    }

    private function embedderConfig(): array
    {
        $embedder = [
            'source' => (string) config('meilisearch.embedder.source', 'ollama'),
            'model' => (string) config('meilisearch.embedder.model'),
            'documentTemplate' => (string) config('meilisearch.embedder.document_template'),
        ];

        if ($url = config('meilisearch.embedder.url')) {
            $embedder['url'] = $url;
        }
        if ($apiKey = config('meilisearch.embedder.api_key')) {
            $embedder['apiKey'] = $apiKey;
        }
        if ($dimensions = config('meilisearch.embedder.dimensions')) {
            $embedder['dimensions'] = (int) $dimensions;
        }

        return $embedder;
    }

    /**
     * Meilisearch-Synonyme: Config-Einträge + schema-getriebene Ergänzung
     * (deutscher ↔ englischer Attributname, schließt die Vokabular-Lücke).
     */
    private function buildSynonyms(array $schema): array
    {
        $synonyms = [];

        foreach ((array) config('meilisearch.synonyms', []) as $term => $list) {
            $synonyms[mb_strtolower((string) $term)] = array_values(array_map(
                fn ($s) => mb_strtolower((string) $s),
                (array) $list,
            ));
        }

        foreach ($schema['attributes'] as $attr) {
            $aliases = array_values(array_unique(array_filter($attr['aliases'])));
            if (count($aliases) < 2) {
                continue;
            }
            foreach ($aliases as $alias) {
                // Einwort-Aliase als wechselseitige Synonyme registrieren
                if (str_contains($alias, ' ')) {
                    continue;
                }
                $others = array_values(array_filter(
                    $aliases,
                    fn ($a) => $a !== $alias && !str_contains($a, ' '),
                ));
                if ($others !== []) {
                    $synonyms[$alias] = array_values(array_unique(array_merge(
                        $synonyms[$alias] ?? [],
                        $others,
                    )));
                }
            }
        }

        return $synonyms;
    }

    /**
     * Schema aus der Datenbank aufbauen.
     */
    private function build(): array
    {
        $units = Unit::query()->get();

        // Basiseinheit je Gruppe ermitteln
        $baseUnitByGroup = [];
        foreach ($units as $unit) {
            if ($unit->is_base_unit && $unit->unit_group_id) {
                $baseUnitByGroup[$unit->unit_group_id] = $unit;
            }
        }

        // Einheiten-Tokens: Abkürzung + technical_name → Gruppe + Faktor zur Basiseinheit
        $unitTokens = [];
        foreach ($units as $unit) {
            if (!$unit->unit_group_id) {
                continue;
            }
            $info = [
                'group_id' => $unit->unit_group_id,
                'to_base' => $unit->conversion_factor !== null ? (float) $unit->conversion_factor : 1.0,
            ];
            foreach ([$unit->abbreviation, $unit->technical_name] as $token) {
                $token = mb_strtolower(trim((string) $token));
                if ($token !== '') {
                    $unitTokens[$token] = $info;
                }
            }
        }

        // Config-Aliase ergänzen/überschreiben (z. B. Zoll → diagonale)
        foreach ((array) config('meilisearch.unit_aliases', []) as $token => $alias) {
            $unitTokens[mb_strtolower((string) $token)] = [
                'attributes' => array_values((array) ($alias['attributes'] ?? [])),
                'to_base' => (float) ($alias['to_base'] ?? 1.0),
            ];
        }

        // Währungs-Tokens → Filter auf list_price
        foreach ((array) config('meilisearch.currency_tokens', []) as $token) {
            $unitTokens[mb_strtolower((string) $token)] = ['currency' => true, 'to_base' => 1.0];
        }

        // Filterbare Attribute: Number, Selection, Flag
        $attributes = [];
        $unitAttributes = [];
        $attrModels = Attribute::query()
            ->where('status', 'active')
            ->whereIn('data_type', ['Number', 'Selection', 'Flag'])
            ->with('defaultUnit')
            ->get();

        foreach ($attrModels as $attr) {
            $defaultToBase = 1.0;
            if ($attr->defaultUnit && $attr->defaultUnit->conversion_factor !== null) {
                $defaultToBase = (float) $attr->defaultUnit->conversion_factor;
            }

            $attributes[$attr->technical_name] = [
                'id' => $attr->id,
                'field' => self::fieldName($attr->technical_name),
                'data_type' => $attr->data_type,
                'unit_group_id' => $attr->unit_group_id,
                'base_unit' => $attr->unit_group_id
                    ? ($baseUnitByGroup[$attr->unit_group_id]->abbreviation ?? null)
                    : null,
                'default_to_base' => $defaultToBase,
                'aliases' => array_values(array_unique(array_filter([
                    mb_strtolower((string) $attr->name_de),
                    mb_strtolower((string) $attr->name_en),
                    mb_strtolower(str_replace(['-', '_'], ' ', $attr->technical_name)),
                ]))),
            ];

            if ($attr->data_type === 'Number' && $attr->unit_group_id) {
                $unitAttributes[$attr->unit_group_id][] = $attr->technical_name;
            }
        }

        // Konzept-Synonyme: nur Attribute behalten, die es wirklich gibt
        $concepts = [];
        foreach ((array) config('meilisearch.concept_synonyms', []) as $word => $names) {
            $existing = array_values(array_filter(
                (array) $names,
                fn ($name) => isset($attributes[$name]),
            ));
            if ($existing !== []) {
                $concepts[mb_strtolower((string) $word)] = $existing;
            }
        }

        return [
            'attributes' => $attributes,
            'units' => $unitTokens,
            'unit_attributes' => $unitAttributes,
            'concepts' => $concepts,
        ];
    }
}
