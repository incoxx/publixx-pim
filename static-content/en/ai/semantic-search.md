---
title: Semantic Search
---

# Semantic Search

The **semantic quick search** understands natural-language queries. You type, for example, *"drill under 200 euros"* or *"8 inch screen in red"*, and anyPIM automatically splits the query into hard filters (price, dimensions, weight …) and a search text that is ranked using a hybrid approach (keyword + vector).

## What the search can do

- **Constraint extraction:** Numbers, units and prices are extracted from the query rule-based (without an LLM) and translated into exact filters. *"under 300 €"* becomes `list_price ≤ 300`, *"8 inch"* a range `[7.6 … 8.4]` (tolerance configurable).
- **Hybrid ranking:** The remaining search text is scored by keyword and vector search simultaneously (provided an embedder is active).
- **Facets:** Optionally the search returns distributions across attribute values.
- **Structured response:** The result contains the ranked hits, the applied constraints (shown as chips) and any unresolved constraints — deliberately no LLM-generated prose.

## Usage

The **"Semantic search…"** field (sparkles icon ✨) appears at the top of the navigation bar once the feature is enabled.

1. Type a query — the search reacts with a debounce.
2. The dropdown shows the detected **constraints** as green chips at the top and the **hits** below (image, name, SKU, hierarchy path, price, relevance score).
3. Navigate with the arrow keys, open a product with `Enter`.
4. Use *"Show all results in quick search"* to reach the full hit list (the **"Semantic"** tab).

## Architecture

```
Query
  → ConstraintExtractor (rule-based, no LLM)
      ├─ numbers + units → hard Meilisearch filters
      └─ residual text → search text
  → HybridSearchService
      ├─ no search text   → "filter" mode  (filters only)
      ├─ embedder off      → "keyword" mode (keyword only)
      └─ otherwise         → "hybrid" mode  (keyword + vector)
  → Meilisearch
  → structured JSON response (hits, constraints, facets, mode)
```

The search is based on **Meilisearch**. Products are stored in a denormalised index (name, description, searchable text, price, hierarchy path, plus one `attr_*` field per filterable attribute). The filter vocabulary is derived dynamically from the PIM attributes and units — nothing is hardcoded.

::: tip Works without an embedder, too
If no embedder is configured or active, the search automatically degrades to pure keyword search. Constraint extraction works regardless.
:::

### Relationship to Typesense

Meilisearch (product search) and [Typesense](/en/installation/typesense) (full-text search in PDF documents) complement each other and do not replace one another. Meilisearch answers *"show me products matching this description"*, Typesense *"which PDF contains this passage?"*.

## Setup & administration

### Environment variables

```bash
MEILISEARCH_ENABLED=false
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_API_KEY=
MEILISEARCH_INDEX=products

# Embedder (optional, for semantic/vector search)
MEILISEARCH_EMBEDDER_ENABLED=true
MEILISEARCH_EMBEDDER_SOURCE=ollama          # ollama | openAi | rest | huggingFace
MEILISEARCH_EMBEDDER_MODEL=nomic-embed-text
MEILISEARCH_EMBEDDER_URL=http://localhost:11434/api/embeddings
MEILISEARCH_EMBEDDER_API_KEY=

# Behaviour
MEILISEARCH_SEMANTIC_RATIO=0.5              # 0 = keyword only, 1 = vector only
MEILISEARCH_NUMERIC_TOLERANCE=0.05          # tolerance, e.g. "8 inch" → [7.6 … 8.4]
```

::: warning Activation
Set `MEILISEARCH_ENABLED=true` and configure `MEILISEARCH_HOST`. If the feature is disabled, the endpoints respond with `503`. The nomic embedder default requires a running [Ollama](https://ollama.com) service.
:::

### Console commands

```bash
# Create/update the index (attributes, filters, embedder)
php artisan pim:meili-setup [--no-embedder] [--fresh-schema]

# Index products (incremental; --all for a full index)
php artisan pim:meili-index [--all] [--since="2026-06-01 00:00"] [--chunk=500]
```

Indexing runs in chunks (default 500 products) without N+1 queries. Embeddings are computed by Meilisearch asynchronously and only for changed documents.

### Administration in the UI

The admin area provides a status view with diagnostics (reachability of Meilisearch and Ollama, loaded model, document count, embeddings present, failed tasks). Maintenance actions (`pull-model`, `setup`, `index-all`) can be started as a background job and their progress polled.

## Permissions

| Permission | Meaning |
|---|---|
| `semantic-search.view` | Use semantic search |
| `meilisearch-admin.view` | View status & diagnostics in the admin area |
| `meilisearch-admin.manage` | Trigger setup/indexing/model download |

`semantic-search.view` is granted broadly by default (incl. Admin, Data Steward, Product Manager, Export Manager, Viewer). The `meilisearch-admin.*` permissions are reserved for Sysadmin and Admin.
