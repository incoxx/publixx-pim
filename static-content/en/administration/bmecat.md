---
title: BMEcat Import/Export
---

# BMEcat Import/Export

anyPIM supports the import and export of product data in the BMEcat format, Europe's leading standard for the electronic exchange of product catalogs. Both BMEcat 1.2 and BMEcat 2005 are supported. The BMEcat interface enables data exchange with ERP systems, e-procurement platforms, and trading partners.

## Overview

Access the BMEcat features via **Administration > BMEcat** in the sidebar. The interface provides two main areas:

| Area | Description |
|---|---|
| **Import** | Load BMEcat XML files into anyPIM |
| **Export** | Generate BMEcat XML files from anyPIM |

::: tip Note
Access to the BMEcat features requires the **Admin** role or the `imports.create` and `exports.create` permissions respectively.
:::

## Supported Versions

| Version | Standard | Supported Transactions |
|---|---|---|
| **BMEcat 1.2** | DIN/ISO | `T_NEW_CATALOG`, `T_UPDATE_PRODUCTS`, `T_UPDATE_PRICES` |
| **BMEcat 2005** | BME e.V. | `T_NEW_CATALOG`, `T_UPDATE_PRODUCTS`, `T_UPDATE_PRICES` |

### Transaction Types

| Transaction | Description |
|---|---|
| `T_NEW_CATALOG` | Complete new catalog with all products |
| `T_UPDATE_PRODUCTS` | Update existing product data |
| `T_UPDATE_PRICES` | Update prices without modifying product data |

## BMEcat Import

### Starting an Import

1. Navigate to **Administration > BMEcat > Import**.
2. Click **Choose File** and upload a BMEcat XML file.
3. The system analyzes the file and displays a preview.

### Import Preview

The preview shows a summary of the data to be imported:

| Information | Description |
|---|---|
| **Catalog Name** | Name of the BMEcat catalog |
| **Version** | Detected BMEcat version |
| **Transaction** | Transaction type |
| **Supplier** | Supplier information from the header |
| **Article Count** | Number of products in the file |
| **New Products** | Number of products to be created |
| **Updates** | Number of products to be updated |

### Catalog Structure Mapping

anyPIM maps the BMEcat catalog structure to its internal hierarchy. In the mapping dialog, you assign BMEcat catalog groups to hierarchy nodes in anyPIM:

| BMEcat Element | anyPIM Mapping |
|---|---|
| `CATALOG_GROUP_SYSTEM` | Hierarchy |
| `CATALOG_GROUP` | Hierarchy node |
| `GROUP_PRODUCT_ORDER` | Product-node assignment |

::: warning Warning
When importing a full catalog (`T_NEW_CATALOG`), existing assignments may be overwritten. Create a backup through the regular export function before importing.
:::

### Media References

BMEcat files reference media (images, data sheets, safety data sheets) through the `MIME` element. anyPIM processes media references as follows:

| Scenario | Behavior |
|---|---|
| **URL Reference** | Media is downloaded from the specified URL |
| **File Reference** | Media is loaded from an accompanying ZIP archive |
| **Already Exists** | Existing media is matched by filename and linked |

### Price Handling

BMEcat prices are mapped to anyPIM price types based on the price type identifier:

| BMEcat Price Type | Description | Mapping |
|---|---|---|
| `net_list` | Net list price | Configurable |
| `net_customer` | Net customer price | Configurable |
| `nrp` | Non-binding recommended price | Configurable |
| `gros_list` | Gross list price | Configurable |

Configure the price type mapping in the import dialog under **Price Mapping**.

### Validation

Before the final import, the system performs validation:

| Check | Description |
|---|---|
| **XML Validation** | Validates the XML structure against the BMEcat schema |
| **Required Fields** | Verifies that all mandatory BMEcat elements are present |
| **Data Types** | Validates values against defined attribute types |
| **References** | Checks that referenced catalog groups and classifications exist |
| **Duplicates** | Detects duplicate article numbers |

Validation errors are displayed in a clear list. You can fix errors before executing the import.

::: danger Warning
For large files (more than 10,000 articles), run the import outside of peak hours as the process can consume significant system resources.
:::

## BMEcat Export

### Configuring an Export

1. Navigate to **Administration > BMEcat > Export**.
2. Configure the export parameters:

| Parameter | Description |
|---|---|
| **Version** | BMEcat 1.2 or BMEcat 2005 |
| **Transaction** | Transaction type (T_NEW_CATALOG, T_UPDATE_PRODUCTS, T_UPDATE_PRICES) |
| **Hierarchy** | Hierarchy to use as catalog structure |
| **Language** | Export language |
| **Price Types** | Price types to export with BMEcat price type mapping |
| **Include Media** | Include media references in the export |

3. Click **Start Export**.
4. The export is processed as a background task. You will receive a notification when the file is ready.

### Export Result

The export result includes:

| File | Description |
|---|---|
| **BMEcat XML** | The actual BMEcat file |
| **Media ZIP** | Optional ZIP archive containing all referenced media files |
| **Log** | Export log with statistics and any warnings |

## Best Practices

- **Take Validation Seriously** -- Resolve all validation errors before importing. Invalid data can lead to inconsistencies.
- **Test Run** -- Use the preview function and, for large imports, perform a test run with a reduced file first.
- **Document Mappings** -- Keep a written record of the mappings between BMEcat fields and anyPIM attributes to ensure consistency across repeated imports.
- **Version Choice** -- Use BMEcat 2005 if your trading partner supports it. It offers extended capabilities compared to version 1.2.
- **Regular Updates** -- Use `T_UPDATE_PRODUCTS` for incremental updates instead of importing a full catalog each time.

## Next Steps

- Learn about the [JSON Export](../export/json-export) to provide product data via the REST API.
- Explore the [import features](../import/index) to import data from other formats.
- Return to the [overview](../usage/index) to explore other functional areas.
