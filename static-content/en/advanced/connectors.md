---
title: Connectors & Integrations
---

# Connectors & Integrations

Via **connectors**, anyPIM links product data and media with external systems — shop systems, asset services and AI/translation APIs. The framework stores credentials encrypted, logs synchronisations and supports both single and batch syncs.

## Available connectors

| Connector | Purpose |
|---|---|
| **Shopware 6** | Sync categories, products, properties and media |
| **Shopify** | Sync products, categories, metafields and media |
| **Salesforce Commerce** | Sync categories, products and media |
| **anyPIM** | Bidirectional sync with other anyPIM instances (incl. translations) |
| **Canva** | Send product data and images to Canva for designs |
| **Cloudinary** | Upload media/assets to Cloudinary |
| **DeepL** | Machine translation (e.g. for [Translation Jobs](/en/usage/translation-jobs)) |
| **Claude AI** | AI text generation (e.g. for [Copilot](/en/ai/copilot) and the [Reel Generator](/en/ai/reel-generator)) |

## Setup

1. Navigation: **Connectors → Plugin Settings**. Store the credentials of the desired service there (stored encrypted).
2. Choose **"Connect"** on the connector card. For OAuth services (Shopware, Shopify, Salesforce, Canva, anyPIM) the authorisation opens; the connection then appears under **"Connections"**.

## Synchronising (shop systems)

1. Open a connection and choose a **website profile** that defines the product scope.
2. Configure mappings (e.g. Shopware properties ↔ PIM attributes).
3. Run the synchronisation:
   - **Profile sync** — all products in the profile
   - **Delta sync** — only changed products (detected via checksums)
   - **Single product** — directly from the product view
4. Review successes/failures in the **sync log** (also exportable as Excel). Maintenance actions (e.g. *reset shop*, *remove categories/media*) are available per connector.

## Permissions & module

The connector area is enabled via the `connectors` module and governed by dedicated rights (*view*, *manage*, *sync*). Administrators have full access. Configuration is under [Roles & Permissions](/en/administration/roles).

::: tip Marketing overview
For a product-oriented overview of integrations, see [Integrations](/en/marketing/integrations).
:::
