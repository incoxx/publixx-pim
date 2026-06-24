---
title: Portals (Document Portal & Asset Catalog)
---

# Portals

With **portals** you publish selected product information — with or without login. anyPIM offers a configurable portal builder plus two ready-to-use portal types: the **Document Portal** and the **Asset Catalog**.

## Portal builder (portal configuration)

Under **Portals** you create portals and design them across several tabs:

- **Settings** — name, unique slug, default language, linked catalog template, *active* (published) and *shared* (visible to others).
- **Branding** — title, subtitle, hero text and feature list.
- **Filter steps** — step-by-step filtering via widgets (country select, language select, filter dropdown, filter cards).
- **HTML template** — custom HTML with placeholders for the portal widgets.
- **CSS** — custom styling.

A **live preview** (desktop/tablet/mobile) shows the result. Portals can be created from presets with one click or duplicated from existing ones. The published portal is reachable at `…/portal/{slug}`.

## Document Portal

The Document Portal provides product documents (e.g. instruction manuals, brochures) by **country** and **language**:

1. Select a country.
2. Search for a product by SKU, EAN or name.
3. The portal shows the primary document plus the files grouped by document type — switchable by language.

## Asset Catalog

The Asset Catalog is a searchable media library:

- **Folder tree** with an asset count per folder.
- **Search** across filename, title, description, attributes and hierarchy names — including phonetic matches (Cologne phonetics).
- **Filters** by usage purpose (print/web) and media type.
- **Detail view** with metadata, related products and hierarchy paths.
- **ZIP download** of multiple assets at once.

## Access & protection

The public portal routes are secured via the catalog access control (open or access-protected). Portals are managed inside the PIM; the visibility of portal configurations is user/team-scoped.

::: tip Related features
For embedded product catalogs on your own website, see [Catalog Embed](/en/advanced/catalog-embed).
:::
