---
title: Translation Jobs (XLIFF)
---

# Translation Jobs & XLIFF

While [Translations](/en/usage/translations) describes maintaining multilingual values directly, **Translation Jobs** batch the translation of many attributes and system texts — automatically via DeepL or outsourced to an agency via the XLIFF format.

## Creating a translation job

1. Navigation: **Translations → Translation Jobs → "New Job"**.
2. Fill in the fields:
   - **Name** of the job
   - **Source language** and **target language**
   - **Scope**: *products* (select products + attributes), *system* (system objects such as attributes, value lists, hierarchies, product types …) or *mixed*
   - optional **filter** to narrow the products
3. The job is created with status **Draft**.

## Lifecycle

```
Draft ──submit──► Pending ──► In progress ──► Completed ──approve──► into the PIM
                                    └──► Failed ──retry──► …
```

- **Submit** — hands the job to DeepL (an active [DeepL connection](/en/advanced/connectors) must be configured). Processing runs asynchronously.
- **Approve** — applies the finished translations to the product data.
- **Cancel** / **Retry** — control running or failed jobs.

## XLIFF export/import (agency workflow)

For translation by external service providers, translatable content can be exported as **XLIFF 1.2** and re-imported after translation:

1. **Export** — download the translatable attributes as an XLIFF file (optionally limited to specific products and the language pair).
2. The agency translates the `<target>` elements and returns the file.
3. **Import** — upload the completed XLIFF file; the values are mapped to the matching product attributes. The result reports imported, skipped and failed entries.

::: tip DeepL prerequisite
Automatic translation requires a configured DeepL connection under [Connectors](/en/advanced/connectors). The XLIFF workflow also works without DeepL.
:::

## Access

Individual jobs are accessible only to their creator and to administrators. Processing runs as a queue job — make sure a worker is running (see [Cron Jobs & Scheduling](/en/installation/cron-jobs)).
