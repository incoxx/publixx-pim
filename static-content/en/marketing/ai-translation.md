---
title: AI, Translation & API Designer — anyPIM
---

# AI, Translation & API Designer

<div class="sales-folder">

## Intelligent automation for product data

anyPIM leverages artificial intelligence and modern API technologies to automate product data workflows. From automatic translation to AI-powered text generation to the visual API Designer — all natively integrated.

---

## Translation Memory Service (TMS)

### Four providers, one workflow

anyPIM runs its own Translation Memory Service as a dedicated microservice. Translations are stored centrally, reused, and managed through an approval workflow.

**Supported translation providers:**

| Provider | Strength |
|----------|----------|
| **DeepL** | Highest quality for European languages, formal/informal control |
| **Claude AI** | Context-sensitive translation with understanding of domain terminology |
| **Google Translate** | Broadest language coverage, cost-effective for large volumes |
| **OpenAI** | Flexible alternative with GPT-4o |

### Translation Jobs

Structured translation workflow:

1. **Create** — Select source language, target language(s), and products
2. **Submit** — Send job to the TMS provider
3. **Review** — Review translations before applying
4. **Approve** — Apply approved translations to product data
5. **Retry** — Re-request individual translations if needed

### XLIFF Import & Export

For collaboration with translation agencies and CAT tools:

- **Export** — Export product texts as XLIFF file (industry standard)
- **Import** — Import finished translations from XLIFF back into the PIM
- **Watchlist Export** — XLIFF export directly from the watchlist for selective translation

---

## AI Integration

### Claude AI Connector

Direct integration with the Anthropic Claude API for AI-powered text processing:

- **Text Generation** — Generate product descriptions, SEO copy, and marketing texts
- **Text Optimization** — Improve, shorten, or rephrase existing texts
- **Context-aware** — The AI knows the product attributes and creates matching content
- **Configurable Model** — Default: Claude Sonnet, adjustable to other models

### OpenAI

Alternative AI integration via the OpenAI API (GPT-4o). Same functionality, different model basis.

---

## GraphQL & API Designer

### Visual API Designer

Create tailored API endpoints — no code required:

- **API Templates** — Define which fields and relations an endpoint should return
- **Schema Preview** — Live preview of the generated schema before publishing
- **API Keys** — Separate authentication per template, regenerable at any time
- **Dependencies** — Overview of which products and attributes a template uses

### GraphQL Support

Dynamically generated GraphQL schemas based on your data model:

- **Schema Builder** — Automatic generation from PIM attributes and relations
- **Flexible Queries** — Clients query only the fields they need
- **Nested Data** — Products with variants, media, and prices in a single query

### API Streams

Public API endpoints with slug-based access:

- **URL-based** — Each stream gets a readable URL (`/api-streams/my-catalog`)
- **Configurable** — Format, fields, and filters definable per stream
- **No Authentication** — For public catalogs and integrations

---

## Excel Template Designer

Visually configure custom Excel export templates:

- **Field Selection** — Freely selectable columns from attributes, prices, media, and relations
- **Preview** — Live preview before export
- **Import** — Import existing Excel structures as templates
- **Progress Indicator** — Real-time progress for large exports with cancel option

---

## Attribute Mapping

Cross-classification mapping for industry standards:

- **Source to Target** — Map PIM attributes to classification fields (e.g., ETIM, eCl@ss)
- **Mapping Rules** — Define transformation rules per assignment
- **Bulk Operations** — Create mappings for many attributes at once
- **Sync** — Synchronize mapped values per product or in batch
- **Excel Import/Export** — Maintain and import mapping tables as Excel

---

## More Automations

| Feature | Description |
|---------|-------------|
| **Projects** | Organize product groups in projects with bulk assignment |
| **Catalog Templates** | Templates for product catalogs with presets and preview |
| **User API Keys** | Self-service: users manage their own API keys |
| **PimSync API** | Dedicated API for PIM-to-PIM synchronization |
| **Export Profiles** | Configurable profiles with streaming support |
| **Export Jobs** | Scheduled exports with SFTP delivery |

---

## Ready for the future?

<div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 2rem; margin-bottom: 2rem;">
<a href="/web/help/en/installation/quickstart" class="marketing-cta-button marketing-cta-primary">Quick Start Guide</a>
<a href="/web/help/en/marketing/integrations" class="marketing-cta-button marketing-cta-secondary">E-Commerce Integrations</a>
<a href="/web/help/en/marketing/" class="marketing-cta-button marketing-cta-secondary">Back to Overview</a>
</div>

</div>
