---
title: API Designer
---

# API Designer

The **API Designer** is a no-code tool for assembling your own data endpoints for product data — without programming. You pick fields, define filters and an output format, and get an endpoint ready to use immediately.

## Creating an API template

1. Navigation: **API Designer → "New API Template"**, give it a name.
2. In the editor, pick fields from the **field palette**:
   - **Base fields** (SKU, name, EAN, status, product type, dates …)
   - **Attributes** (your own features)
   - **Prices** (by price type)
   - **Media** (by usage type)
   - **Relations** (by relation type)
3. Optionally **group** by product type, category or status.
4. Use a linked **search profile** to define which products are exposed.
5. Choose the **output format**: **JSON** or **GraphQL**.

## Test & publish

- **Preview** shows a sample output with a few products; for GraphQL it also shows the schema (SDL) and a sample query.
- Set *active* to go live; *shared* makes it visible to others.
- The endpoint is then reachable at `…/api-streams/{slug}`.

## Authentication & limits

| Setting | Options |
|---|---|
| **Auth type** | none · bearer token · API key (custom header) |
| **Rate limit** | limit requests per minute |
| **Key** | regenerate the API key when needed (the old one is invalidated) |

::: tip MCP for Claude
Templates can optionally be enabled as an **MCP endpoint** and registered as a custom connector in Claude — Claude can then access the defined product data live.
:::

## Relationship to the JSON API

The API Designer creates **tailored** endpoints for individual use cases. The general, complete interface is documented under [JSON API](/en/api/).

## Permissions & module

The API Designer is enabled via the `api_designer` module. Non-shared templates are accessible only to their creator. Configuration is under [Roles & Permissions](/en/administration/roles).
