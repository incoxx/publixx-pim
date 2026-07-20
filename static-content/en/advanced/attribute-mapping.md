---
title: Attribute Mapping
---

# Attribute Mapping

**Attribute mapping** maps source attributes onto target attributes of another classification/hierarchy — for example from your own master data onto the features of an externally imported classification hierarchy. It is the basis for outputting the same product correctly in multiple classifications without maintaining values more than once.

## How it works

1. Navigation: **Attribute Mapping**.
2. Select a **source schema** and a **target schema** (each a hierarchy).
3. In the mapping table, define a source attribute and a **transform type** per target attribute:

| Transform | Description |
|---|---|
| **Direct (1:1)** | Take the value unchanged |
| **Unit conversion** | Convert the value between units (e.g. mm → cm) |
| **Value mapping** | Map values onto each other (e.g. own selection values → standard value list) |

### Conditional rules

In addition, you can define **rules** that set one or more target attributes depending on a condition (attribute, operator, value).

## Synchronising values

The mapped values are applied to the products' target attributes:

- Synchronise a **single product** — with immediate feedback (created/updated/skipped).
- Synchronise a **selection** — process selected products immediately.
- Synchronise in **batch** — process many products asynchronously as a background job.

::: tip Manual values take precedence
On export, a value maintained directly on the product (classification-specific) always wins over a value computed via mapping. This preserves manual corrections.
:::

## Excel export/import

Mappings and rules can be exported as an Excel file (two sheets: *Mappings*, *Rules*), edited externally and re-imported (upsert).

## Permissions

Dedicated rights govern viewing, creating and deleting mappings and rules. Configuration is under [Roles & Permissions](/en/administration/roles).
