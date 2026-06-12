<?php

declare(strict_types=1);

/*
 * Meilisearch — Vektorbasierte Schnellsuche (Hybrid Search).
 *
 * Die Produktsuche kombiniert Keyword-Matching, semantische Vektorsuche
 * (Embeddings) und harte Filter aus extrahierten Constraints („8 Zoll",
 * „unter 300 €"). Single Source of Truth für das Filter-Vokabular ist das
 * PIM-Schema (Attribute + Einheiten), siehe SearchSchemaService.
 *
 * Benötigt Meilisearch >= 1.10 (Hybrid Search / Embedders stabil).
 */
return [
    // Master-Schalter: bei false werden weder Dokumente synchronisiert
    // noch Suchanfragen an Meilisearch gestellt.
    'enabled' => env('MEILISEARCH_ENABLED', false),

    'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
    'api_key' => env('MEILISEARCH_API_KEY'),

    // Index-UID für Produkte.
    'index' => env('MEILISEARCH_INDEX', 'products'),

    // HTTP-Timeout in Sekunden (Suche muss eingabefeld-tauglich bleiben).
    'timeout' => (int) env('MEILISEARCH_TIMEOUT', 5),

    /*
     * Embedder-Konfiguration (Meilisearch berechnet die Embeddings selbst,
     * inkrementell pro geändertem Dokument — kein Full-Reindex pro Lauf).
     *
     * source: ollama | openAi | rest | huggingFace
     * - ollama:  lokal, keine Kosten, z. B. nomic-embed-text
     * - openAi:  API, benötigt MEILISEARCH_EMBEDDER_API_KEY
     */
    'embedder' => [
        'enabled' => env('MEILISEARCH_EMBEDDER_ENABLED', true),
        'name' => 'products',
        'source' => env('MEILISEARCH_EMBEDDER_SOURCE', 'ollama'),
        'model' => env('MEILISEARCH_EMBEDDER_MODEL', 'nomic-embed-text'),
        'url' => env('MEILISEARCH_EMBEDDER_URL', 'http://localhost:11434/api/embeddings'),
        'api_key' => env('MEILISEARCH_EMBEDDER_API_KEY'),
        'dimensions' => env('MEILISEARCH_EMBEDDER_DIMENSIONS'),

        // Liquid-Template: bestimmt, welcher Text embedded wird. Ändert sich
        // der gerenderte Text eines Dokuments nicht, berechnet Meilisearch
        // kein neues Embedding (inkrementelle Aktualität).
        'document_template' => env(
            'MEILISEARCH_EMBEDDER_TEMPLATE',
            '{{ doc.product_name }} {{ doc.name_de }} {{ doc.hierarchy_path }} {{ doc.description_de | truncatewords: 120 }} {{ doc.searchable_text | truncatewords: 240 }}',
        ),
    ],

    'search' => [
        // Gewichtung Vektor vs. Keyword (0 = nur Keyword, 1 = nur Vektor).
        'semantic_ratio' => (float) env('MEILISEARCH_SEMANTIC_RATIO', 0.5),

        // Toleranz für „weiche Gleichheit" bei Zahl+Einheit ohne Komparator:
        // „8 Zoll" → [8·(1−t), 8·(1+t)]. 0 = exakte Gleichheit.
        'numeric_tolerance' => (float) env('MEILISEARCH_NUMERIC_TOLERANCE', 0.05),

        'default_limit' => 20,
        'max_limit' => 100,
    ],

    // Cache-Dauer des Such-Schemas (Attribute/Einheiten) in Sekunden.
    'schema_cache_ttl' => (int) env('MEILISEARCH_SCHEMA_CACHE_TTL', 3600),

    /*
     * Meilisearch-Synonyme (Term ↔ Term, wirken auf den Dokumenttext).
     * Schließt die Vokabular-Lücke für Begriffe, die nicht als Attributwert
     * vorkommen. Schema-getriebene Synonyme (Attributnamen) werden vom
     * SearchSchemaService ergänzt.
     */
    'synonyms' => [
        'bildschirm' => ['display', 'monitor', 'screen'],
        'display' => ['bildschirm', 'monitor', 'screen'],
        'handy' => ['smartphone', 'telefon', 'mobiltelefon'],
        'telefon' => ['smartphone', 'handy', 'mobiltelefon'],
    ],

    /*
     * Konzept-Synonyme für den Constraint-Extraktor (Term → Attribute).
     * Disambiguiert, auf welches Attribut sich eine Zahl+Einheit bezieht:
     * „8 Zoll Bildschirm" → bildschirm → diagonale.
     * Keys kleingeschrieben, Values sind technical_names von Attributen.
     */
    'concept_synonyms' => [
        'bildschirm' => ['diagonale', 'display'],
        'display' => ['diagonale', 'display'],
        'monitor' => ['diagonale', 'display'],
        'screen' => ['diagonale', 'display'],
        'gewicht' => ['gewicht', 'product-weight-num'],
    ],

    /*
     * Einheiten-Aliase, die (noch) nicht im Einheiten-Schema des PIM stehen —
     * z. B. weil importierte (bmecat) Attribute keine unit_group tragen.
     * Übergangslösung, bis der Auftraggeber das Datenmodell korrigiert;
     * Einträge mit 'attributes' binden den Token direkt an Attribute.
     *
     * to_base: Faktor in die Basiseinheit der indexierten Werte.
     */
    'unit_aliases' => [
        'zoll' => ['attributes' => ['diagonale'], 'to_base' => 1.0],
        'inch' => ['attributes' => ['diagonale'], 'to_base' => 1.0],
        '"' => ['attributes' => ['diagonale'], 'to_base' => 1.0],
        '″' => ['attributes' => ['diagonale'], 'to_base' => 1.0],
    ],

    // Währungs-Tokens → Filter auf list_price (kein Attribut nötig).
    'currency_tokens' => ['€', 'eur', 'euro'],
];
