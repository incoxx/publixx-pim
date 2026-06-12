# 21 — Semantische Schnellsuche (Meilisearch Hybrid Search)

Vektorbasierte Produktsuche, die natürlichsprachige Anfragen versteht
(„smartes Telefon mit 8 Zoll Bildschirm") und schnell qualifizierte Treffer
liefert. Kombiniert semantisches Verstehen unscharfer Begriffe (Embeddings)
mit deterministischer Präzision bei Zahlen, Einheiten und Facetten
(harte Filter).

## Architektur-Überblick

```
Anfrage „smartes Telefon mit 8 Zoll Bildschirm unter 300 €"
   │
   ▼
ConstraintExtractor (Regex, deterministisch — kein LLM im heißen Pfad)
   │  ├─ „8 Zoll"      → attr_diagonale ∈ [7.6, 8.4]   (Toleranz ±5 %)
   │  ├─ „unter 300 €" → list_price <= 300
   │  └─ Rest: „smartes Telefon Bildschirm"  → Suchtext
   ▼
HybridSearchService
   │  Meilisearch-Query: q + filter + hybrid{embedder, semanticRatio}
   ▼
Meilisearch (ein Store: Keyword-Index + Vektoren + Facetten)
   │
   ▼
JSON-Antwort: gerankte Treffer, angewandte Constraints, Facetten
```

| Baustein | Datei |
|----------|-------|
| REST-Client (ohne Composer-Abhängigkeit) | `app/Services/Search/MeilisearchClient.php` |
| Such-Schema aus dem PIM (gecacht) | `app/Services/Search/SearchSchemaService.php` |
| Constraint-Extraktor (Zahl/Einheit/Preis) | `app/Services/Search/ConstraintExtractor.php` |
| Filter-Ausdrücke für Meilisearch | `app/Services/Search/MeilisearchFilterBuilder.php` |
| Dokument-Aufbau (inkl. Einheiten-Normalisierung) | `app/Services/Search/MeilisearchDocumentBuilder.php` |
| Such-Orchestrierung (Hybrid/Keyword/Filter) | `app/Services/Search/HybridSearchService.php` |
| Inkrementeller Sync-Job | `app/Jobs/SyncProductToMeilisearch.php` |
| Index-Setup (Settings, Synonyme, Embedder) | `app/Console/Commands/MeilisearchSetup.php` |
| Erstaufbau / Catch-up | `app/Console/Commands/MeilisearchIndex.php` |
| API-Endpoint | `app/Http/Controllers/Api/V1/SemanticSearchController.php` |
| Konfiguration | `config/meilisearch.php` |

## Schema-getrieben (Single Source of Truth: PIM)

`SearchSchemaService` baut das Filter-Vokabular aus den PIM-Attributen
(Datenbasis von `list_attributes`) und dem Einheiten-Modell — **gecacht**
(Standard 1 h, `MEILISEARCH_SCHEMA_CACHE_TTL`), nicht pro Anfrage:

- **Attribute** (Number/Selection/Flag, aktiv) → filterbare Felder
  `attr_{technical_name}` (Bindestriche → Unterstriche).
- **Einheiten** → Tokens (Abkürzung, technical_name) mit `conversion_factor`
  zur Basiseinheit der Gruppe. Index- und Query-Seite normalisieren beide in
  die Basiseinheit, dadurch matcht „500 g" auf einen als „0,5 kg"
  gepflegten Wert.
- **Synonyme** → Meilisearch-Synonyme aus Attributnamen (de ↔ en) plus
  Config-Einträge (`meilisearch.synonyms`).

Lücken im Datenmodell (z. B. bmecat-importierte Attribute ohne
`unit_group`, etwa `diagonale`) werden bis zur Korrektur durch den
Auftraggeber per Config überbrückt:

- `meilisearch.unit_aliases` — bindet Einheiten-Tokens direkt an Attribute
  (`'zoll' => ['attributes' => ['diagonale']]`).
- `meilisearch.concept_synonyms` — disambiguiert mehrdeutige Einheiten über
  Kontextwörter („Bildschirm" → `diagonale`); ohne Kontext werden alle
  Kandidaten OR-verknüpft.

Schema-Cache invalidieren: `pim:meili-setup --fresh-schema` oder
`SearchSchemaService::flush()`.

## Constraint-Extraktion (deterministisch)

Regex-basiert, kein LLM — Latenz im Mikrosekundenbereich, reproduzierbar:

| Muster | Beispiel | Ergebnis |
|--------|----------|---------|
| Zahl + Einheit | „8 Zoll" | Toleranzbereich `[8·(1−t), 8·(1+t)]`, t = `MEILISEARCH_NUMERIC_TOLERANCE` (Default 0.05) |
| Komparator | „unter 300 €", „mindestens 2 kg", „max. 50 mm" | `<=` / `>=` |
| Bereich | „zwischen 5 und 8 Zoll", „von 1 bis 2 kg" | `>= AND <=` |
| Währung | „300 EUR" (ohne Komparator) | Budget-Obergrenze: `list_price <= 300` |

Erkannte Constraints werden aus dem Suchtext entfernt; nicht auflösbare
(unbekannte Einheit, kein Attribut-Kandidat) bleiben Teil der Keyword-Suche
und werden in der Antwort als `unresolved_constraints` ausgewiesen.

## Embeddings & inkrementelle Aktualität

- **Ein Store:** Embeddings liegen in Meilisearch (Embedder-Feature,
  benötigt **Meilisearch ≥ 1.10**). Kein zweiter Vektorstore.
- **Modell konfigurierbar:** Default Ollama lokal (`nomic-embed-text`,
  kein API-Kostenrisiko, Daten bleiben im Haus); per Env auf `openAi`/`rest`
  umstellbar (`MEILISEARCH_EMBEDDER_*`).
- **Inkrementell:** Meilisearch berechnet Embeddings selbst aus dem
  `documentTemplate` (Name, Hierarchiepfad, Beschreibung, searchable_text)
  und **nur neu, wenn sich der gerenderte Text ändert**. Kein Full-Reindex
  pro Lauf.
- **Trigger statt Zeitsteuerung:** Der bestehende Observer-Pfad
  (`ProductObserver`/`AttributeValueObserver` → `UpdateSearchIndex`) kettet
  nach Aktualisierung des denormalisierten `products_search_index` den Job
  `SyncProductToMeilisearch` an (Queue `indexing`, dedupliziert). Löschungen
  entfernen das Dokument.
- **Catch-up:** `php artisan pim:meili-index` synchronisiert nur seit dem
  letzten Lauf geänderte Produkte (`--all` für Vollaufbau, `--since=` für
  expliziten Zeitpunkt).

## Such-API

`POST /api/v1/semantic-search`

```json
{
  "q": "smartes Telefon mit 8 Zoll Bildschirm unter 300 €",
  "filters": [{"attribute": "farbe", "operator": "eq", "value": "Rot"}],
  "facets": ["farbe"],
  "limit": 20,
  "offset": 0,
  "semantic_ratio": 0.5,
  "mode": "auto",
  "status": "active"
}
```

- `mode`: `auto` (Default) | `hybrid` | `keyword` | `filter`.
  Bei `auto`: leerer Restsuchtext → reiner Filter-Betrieb; Embedder
  deaktiviert → Keyword; sonst Hybrid.
- `filters`: explizite GUI-Filter (technical_name + Operator
  `eq|neq|gt|gte|lt|lte|between`), zusätzlich zu extrahierten Constraints.
- Antwort (strukturiertes JSON, keine LLM-Prosa): `hits` (mit
  `score` = Ranking-Score), `total`, `applied_constraints`,
  `unresolved_constraints`, `residual_query`, `mode`, `facets`,
  `processing_time_ms`.

`GET /api/v1/semantic-search/health` — Status von Index, Dokumentanzahl
und Embedder.

## Inbetriebnahme

```bash
# 1. Meilisearch >= 1.10 starten, Env konfigurieren (MEILISEARCH_*)
# 2. Index-Settings (Filter, Facetten, Synonyme, Embedder) anwenden:
php artisan pim:meili-setup

# 3. Erstbefüllung:
php artisan pim:meili-index --all

# Danach hält der Observer-Pfad den Index automatisch aktuell.
# Optionaler Catch-up (z. B. Cron, nach Ausfällen):
php artisan pim:meili-index
```

## Tuning

| Parameter | Env | Default | Wirkung |
|-----------|-----|---------|---------|
| Semantic Ratio | `MEILISEARCH_SEMANTIC_RATIO` | 0.5 | 0 = nur Keyword, 1 = nur Vektor; pro Anfrage via `semantic_ratio` übersteuerbar |
| Numerische Toleranz | `MEILISEARCH_NUMERIC_TOLERANCE` | 0.05 | Breite des Bereichs bei „8 Zoll" ohne Komparator; 0 = exakte Gleichheit |
| Schema-Cache | `MEILISEARCH_SCHEMA_CACHE_TTL` | 3600 s | Lebensdauer des Attribut-/Einheiten-Vokabulars |

## Bewusste Entscheidungen

- **Kein LLM im heißen Pfad:** Die Constraint-Extraktion ist Regex-basiert.
  Ein kleines LLM als einmaliger Extraktor bliebe laut Auftrag zulässig und
  könnte später hinter derselben `ConstraintExtractor`-Schnittstelle ergänzt
  werden — die nachgelagerte Filter-Logik bleibt identisch.
- **Bestehender Denormalisierungs-Pfad als Quelle:** Das Meilisearch-Dokument
  wird aus `products_search_index` gebaut (dort liegen Name, Beschreibung,
  `searchable_text`, Hierarchiepfad, Listenpreis bereits flach vor) und um
  filterbare `attr_*`-Werte ergänzt. Dadurch ein einziger Sync-Trigger und
  keine zweite Aggregationslogik.
- **Kein neues Composer-Paket:** Meilisearch wird über einen schlanken
  REST-Client (Laravel HTTP-Client) angebunden.
