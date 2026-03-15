---
title: Dictionary
---

# Dictionary

The Dictionary in anyPIM serves as a central terminology management system. It stores standardized terms with their translations, ensuring that product data across all languages uses consistent, approved vocabulary. By linking dictionary entries to attributes, you maintain uniform naming conventions throughout your product catalog and across all output channels.

## Purpose and Benefits

In a multilingual product information environment, inconsistent terminology is a common challenge. Different team members might use different translations for the same concept, or product descriptions might vary in how they refer to technical terms. The Dictionary addresses this by providing:

- **A single source of truth** for approved terms and their translations.
- **Consistency enforcement** by linking dictionary entries to attribute values.
- **Reduced translation effort** by reusing pre-approved translations across products.
- **Improved data quality** by preventing ad-hoc translations that may be inaccurate.

## Dictionary Overview

Navigate to **Dictionary** in the sidebar to access the terminology management. The overview displays all dictionary entries in a searchable table:

| Column | Description |
|---|---|
| **Term** | The primary term in the default language |
| **Translations** | A preview of available translations (language codes) |
| **Linked Attributes** | The number of attributes referencing this term |
| **Category** | Optional grouping category for the term |
| **Modified** | Timestamp of the last modification |

### Searching and Filtering

Use the search bar to find terms by name in any language. You can also filter by category to narrow down the list to a specific domain (for example, "Materials" or "Colors").

## Creating a Dictionary Entry

Click **+ New Entry** to create a new term. The creation form contains the following fields:

| Field | Description | Required |
|---|---|---|
| **Term** | The primary term in the system's default language | Yes |
| **Category** | A grouping label for organizing terms (e.g., "Materials", "Certifications") | No |
| **Description** | An internal note explaining the term's meaning and intended use | No |

After creating the entry, you are taken to the detail view where you can add translations.

## Managing Translations

Each dictionary entry can have translations in all languages configured in anyPIM. The translations panel in the entry detail view shows a list of all available languages:

| Field | Description |
|---|---|
| **Language** | The language code (e.g., EN, DE, FR, ES) |
| **Translation** | The approved translation of the term in that language |
| **Status** | Whether the translation has been reviewed and approved |

### Adding a Translation

1. In the dictionary entry detail view, locate the translations panel.
2. Click the **Edit** icon next to the target language.
3. Enter the approved translation.
4. Optionally mark the translation as **Approved** to indicate it has been reviewed.
5. Click **Save**.

::: tip Note
Marking a translation as "Approved" is an organizational indicator for your team. It does not affect system behavior but helps track which translations have been reviewed by a language specialist.
:::

### Bulk Translation

For entries with many languages, you can use the **Bulk Edit** mode:

1. Click **Bulk Edit** at the top of the translations panel.
2. All language fields become editable simultaneously.
3. Enter or update translations for multiple languages at once.
4. Click **Save All** to store the changes.

## Linking Dictionary Entries to Attributes

Dictionary entries can be linked to specific attributes to provide standardized terminology for that attribute's context. This link serves as a reference for users editing product data, reminding them to use the approved term.

### Creating a Link

1. Open the dictionary entry detail view.
2. In the **Linked Attributes** section, click **+ Link Attribute**.
3. Search for the attribute by technical name or display name.
4. Select the attribute and confirm.

### How Links Work

When a dictionary entry is linked to an attribute:

- Users editing the attribute value in the product detail view can see suggested terms from the dictionary.
- The dictionary term and its translation for the current language are available as a suggestion or reference.
- This does not restrict the user to only use dictionary terms -- it provides guidance while allowing flexibility.

::: warning Warning
Linking a dictionary entry to an attribute does not automatically replace existing values in products. It only provides terminology suggestions for future edits. To update existing product data, use the bulk editing or import features.
:::

## Categories

Categories help organize dictionary entries into logical groups. They are simple labels that you can assign to entries for easier filtering and browsing.

### Common Categories

| Category | Example Terms |
|---|---|
| **Materials** | Stainless Steel, Aluminum, Polycarbonate, Cotton |
| **Colors** | Anthracite, Cobalt Blue, Signal Red, Ivory |
| **Certifications** | CE, TUV, ISO 9001, RoHS, REACH |
| **Surface Finishes** | Matte, Glossy, Brushed, Powder-Coated |
| **Packaging** | Blister Pack, Carton, Shrink Wrap, Pallet |

Categories are free-form text labels. There is no predefined list -- you can create any category name that fits your organizational needs.

## Importing and Exporting Dictionary Data

### Import

Dictionary entries and their translations can be imported from an Excel or CSV file. The import file should contain columns for the primary term, category, and one column per language for translations.

### Export

Click **Export** in the dictionary overview to download all entries with their translations. The export is available in CSV and Excel formats and includes all fields: term, category, description, and translations for every configured language.

## Deleting a Dictionary Entry

To delete an entry, open the detail view and click **Delete**. If the entry is linked to attributes, a confirmation dialog shows which attributes will lose their dictionary reference. The attributes themselves are not affected -- only the terminology link is removed.

## Next Steps

- Explore [Attributes](./attributes) to understand how dictionary terms connect to product data fields.
- Learn about [Translations](./translations) for managing multilingual product content.
- Use [Reports](../advanced/reports) to audit terminology consistency across your product catalog.
