---
title: Variants & Versions
---

# Variants & Versions

The product detail view provides two related but distinct features: **Variants** for product variations (e.g. colour/size) and **Versions** for versioning a product over time.

## Variants

Variants are child products linked to a parent product. They inherit the parent's product type but have their own SKU, name, EAN and status, and can inherit or override attribute values depending on the inheritance rule.

The features live in the product's **"Variants"** tab (visible when the product type allows variants):

- **New variant** — create a single variant with SKU, name, EAN and status.
- **Generate variants** — use the *variant generator* to select multiple dimensions (e.g. selection attributes) and automatically create all combinations (e.g. 3 colours × 4 sizes = 12 variants). The SKU prefix and initial status are configurable; existing SKUs are skipped.
- **Variant attributes** — define per attribute whether it is inherited from the parent (*inherit*) or maintained independently (*override*).

::: tip
For an attribute to be selectable as a dimension in the generator, it must be marked as a variant attribute.
:::

## Versions

Versions are immutable snapshots of the product state (base fields + all attribute values). They serve traceability, scheduled publishing and rollback. The features live in the **"Versions"** tab.

### Lifecycle

```
Draft ──activate──► Active ──(superseded by a new active version)──► Archived
  │
  └──schedule (publish at)──► Scheduled ──(automatic)──► Active
```

- **Create version** — creates a *draft*; optionally with a *change reason*.
- **Activate** — publishes a version; it becomes the current state.
- **Schedule** — set publishing to a point in time (*publish at*); the version is activated automatically. Reversible via *cancel schedule*.
- **Revert** — restore an *archived* version; this creates a new active version.
- **Compare** — diff two versions, or a version against the *current state* (base fields and attribute values).

::: tip Scheduled versions & calendar
Scheduled publications also appear in the [Planning Calendar](/en/advanced/calendar) alongside scheduled actions and export jobs.
:::

## Permissions

Both features are protected by the permission system and (for variants) by tab access rights. Configuration is under [Roles & Permissions](/en/administration/roles).
