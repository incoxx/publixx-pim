---
title: Translations (TMS)
---

# Translations (TMS)

The **Translation Memory Service** (TMS) is an integrated translation system for metadata in anyPIM. While product data (attribute values) is translated via XLIFF export/import, the TMS handles the translation of **system labels** such as attribute names, value list entries, hierarchy nodes, and other metadata entities.

## Overview

The TMS manages translations for the following entity types:

| Entity Type | Field | Example |
|---|---|---|
| **Attributes** | `name_json` | "Farbe" → "Color", "Couleur", "Colore" |
| **Attribute Groups** | `name_json` | "Technische Daten" → "Technical Data" |
| **Attribute Views** | `name_json` | "Grunddaten" → "Basic Data" |
| **Value Lists** | `name_json` | "Materialien" → "Materials" |
| **Value List Entries** | `display_value_json` | "Baumwolle" → "Cotton" |
| **Product Types** | `name_json` | "Standardartikel" → "Standard Article" |
| **Price Types** | `name_json` | "Listenpreis" → "List Price" |
| **Relation Types** | `name_json` | "Zubehör" → "Accessories" |
| **Unit Groups** | `name_json` | "Gewicht" → "Weight" |
| **Units** | `abbreviation_json` | "kg" → "kg" |
| **Hierarchies** | `name_json` | "Produktkatalog" → "Product Catalog" |
| **Hierarchy Nodes** | `name_json` | "Elektrowerkzeuge" → "Power Tools" |

## The User Interface

Access the translation management via the **Translations** menu item in the sidebar (Languages icon). The interface is organized into four tabs:

### Tab: Overview

The overview page shows the current state of translation coverage:

- **Total Terms**: Number of all source texts registered in the TMS
- **Language Cards**: For each configured target language, a card displays:
  - Coverage percentage (progress bar)
  - Number of translated terms
  - Number of reviewed translations (manually confirmed)
  - Number of missing translations

### Tab: Terms

The terms table lists all translation units registered in the TMS. You can filter by:

- **Search text**: Full-text search in source text
- **Language**: Target language for translations
- **Domain**: Entity type (e.g. Attributes, Value Lists, Hierarchies)
- **Status**: Missing, Automatic, Reviewed

Click on an entry to open the **detail panel**. The panel shows:

- The source text with source language
- All existing translations with status and provider
- Usage locations (which entities use this text)

#### Editing Translations

Click on an existing translation or on **"+ Add translation"** in the detail panel to manually edit the text. Manually entered translations receive the **"Reviewed"** status and will not be overwritten by automatic translations.

### Tab: Missing

Shows all terms for which no translation exists in the selected target language. From here you can manually add missing translations or trigger automatic translation.

### Tab: Settings

The settings tab allows you to manually trigger two actions:

| Action | Description |
|---|---|
| **Start Ingest** | Transfers all metadata entities from PIM to the TMS. New terms are automatically forwarded for translation. |
| **Start Sync** | Retrieves all available translations from the TMS and writes them back to the `name_json` fields in the PIM database. |

Both actions run asynchronously as queue jobs in the background.

## Architecture

The TMS is implemented as a standalone Laravel application in the `/tms/` directory. Communication between PIM and TMS happens via a REST API with shared-key authentication.

```
┌─────────────────────┐         ┌─────────────────────┐
│      anyPIM         │         │        TMS           │
│                     │         │                      │
│  IngestToTmsJob ────┼── POST ─┼─► IngestController   │
│                     │ /ingest │                      │
│                     │         │  ProcessIngestBatch   │
│                     │         │         │             │
│                     │         │         ▼             │
│                     │         │  TranslateUnitJob     │
│                     │         │    │   │   │          │
│                     │         │  DeepL Google Claude  │
│                     │         │         │             │
│                     │         │         ▼             │
│  SyncTmsTranslations│◄─ GET ──┼── ResolveController  │
│  Job                │ /resolve│   (Redis → DB)       │
│    │                │         │                      │
│    ▼                │         │                      │
│  name_json          │         │  tms_units           │
│  display_value_json │         │  tms_translations    │
│  abbreviation_json  │         │  tms_usages          │
└─────────────────────┘         └──────────────────────┘
```

### Data Flow

1. **Ingest**: The PIM job `IngestToTmsJob` collects all metadata entities and sends them as a batch to the TMS endpoint `POST /api/ingest`. The TMS calculates SHA-256 hashes and creates new translation units.

2. **Translation**: For new units, a `TranslateUnitJob` is automatically dispatched, which translates the text via the configured provider chain (DeepL → Google → Claude).

3. **Resolve/Sync**: The PIM job `SyncTmsTranslationsJob` queries the TMS endpoint `GET /api/resolve`. This returns translations from the Redis cache (fallback: database). The results are written to the `name_json` fields of the PIM entities.

### Redis Cache

Translations are cached in Redis:

- **Key schema**: `tms:t:{text_hash}:{language}` (e.g. `tms:t:abc123:fr`)
- **TTL**: 24 hours (configurable)
- **Fallback**: On cache miss, data is automatically loaded from the database

## MT Providers (Machine Translation)

The TMS supports three MT providers in configurable order:

### DeepL (Default)

- Highest translation quality for European languages
- Supports 29 languages (DE, EN, FR, ES, IT, NL, PL, PT, etc.)
- Cost: approx. EUR 20 per 1M characters
- Configuration: `DEEPL_API_KEY` and `DEEPL_API_URL`

### Google Translate (Fallback)

- Broad language support (100+ languages)
- Suitable as fallback for languages not supported by DeepL
- Configuration: `GOOGLE_TRANSLATE_API_KEY`

### Claude / Anthropic (Optional)

- AI-based translation for difficult texts and review
- Particularly suitable for domain-specific terminology
- Not intended for bulk translation
- Configuration: `ANTHROPIC_API_KEY`

### Provider Chain

The provider order is configured via the environment variable `TMS_PROVIDER_CHAIN` (default: `deepl,google`). On error or missing language support, the next provider is automatically tried.

## Configuration

TMS configuration is done via environment variables in the PIM:

| Variable | Description | Default |
|---|---|---|
| `TMS_ENABLED` | Enable/disable TMS | `false` |
| `TMS_BASE_URL` | URL of the TMS application | `http://localhost:8001/api` |
| `TMS_API_KEY` | Shared secret for API authentication | — |
| `TMS_TIMEOUT` | HTTP timeout in seconds | `2` |
| `TMS_TARGET_LANGUAGES` | Comma-separated target languages | `en,fr,es,it,nl` |

## Automation

The TMS is integrated into the Laravel Scheduler:

| Schedule | Command | Description |
|---|---|---|
| Daily | `tms:ingest` | Transfers all metadata entities to the TMS |
| Daily at 04:00 | `tms:sync` | Writes translations back to the PIM database |

Both commands can also be run manually via the Artisan CLI:

```bash
php artisan tms:ingest    # Manual ingest
php artisan tms:sync      # Manual sync
```

## Comparison with XLIFF Translations

| Feature | TMS (Metadata) | XLIFF (Product Data) |
|---|---|---|
| **Subject** | Attribute names, value lists, hierarchies | Product attribute values |
| **Menu item** | Translations | Product Detail → Translations |
| **Workflow** | Automatic (MT) + manual review | Export → Translate → Import |
| **Format** | JSON (name_json) | XLIFF 1.2 |
| **Automation** | Scheduler + MT providers | Manual export/import |

## FAQ

**Will manually entered translations be overwritten by automatic ones?**
No. Translations with "Reviewed" status are never automatically overwritten. Only translations with "Automatic" status can be updated by a new MT run.

**What happens if the TMS is unreachable?**
PIM continues to function without any restrictions (graceful degradation). The TmsClient has a 2-second timeout and returns empty results on errors without affecting PIM functionality.

**How do I add a new target language?**
Add the desired language to the environment variable `TMS_TARGET_LANGUAGES` (e.g. `en,fr,es,it,nl,pl`) and then run an ingest so that new translations are created.
