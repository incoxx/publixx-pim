---
title: Database
---

# Database

The Database Viewer provides administrators with read-only insight into the database structure and contents of anyPIM. It is designed for troubleshooting, understanding the data model, and running simple queries without requiring an external database client.

## Overview

Access the Database Viewer via **Administration > Database** in the sidebar. The interface is divided into two main areas:

| Area | Description |
|---|---|
| **Table List** | Sidebar listing all database tables, sorted alphabetically |
| **Table View** | Main area displaying the structure and data of the selected table |

::: danger Warning
The Database Viewer is accessible only to users with the **Admin** role. It intentionally provides read-only access -- data modifications through this viewer are not possible. Use the regular application features for any changes.
:::

## Viewing Table Structure

Select a table from the sidebar to inspect its structure. The **Structure** tab displays all columns of the table with the following information:

| Column | Description |
|---|---|
| **Name** | Column name |
| **Type** | Data type (e.g., `varchar(255)`, `integer`, `timestamp`, `uuid`) |
| **Nullable** | Whether the column allows NULL values |
| **Default** | Default value of the column |
| **Index** | Existing indexes (Primary, Unique, Index) |

### Key Tables

| Table | Description |
|---|---|
| `products` | Product master data |
| `product_attribute_values` | Product attribute values |
| `attributes` | Attribute definitions |
| `attribute_groups` | Attribute groups |
| `hierarchies` | Hierarchy definitions |
| `hierarchy_nodes` | Individual nodes within a hierarchy |
| `media` | Media files (images, documents) |
| `prices` | Price data |
| `users` | User accounts |

## Browsing Data

Switch to the **Data** tab to browse the contents of a table. Data is displayed with pagination, defaulting to 50 entries per page.

### Sorting

Click a column header to sort the data by that column. Click again to reverse the sort direction.

### Column Filters

Use the filter icon in each column header to narrow the display to specific values:

| Filter Type | Description | Example |
|---|---|---|
| **Contains** | Substring match | Name contains "Drill" |
| **Equals** | Exact value match | Status equals "active" |
| **Not Equal** | Exclude values | Type not equal "variant" |
| **Greater/Less** | Numeric comparisons | Price greater than 100 |
| **Null/Not Null** | Filter NULL values | Expiry date is Null |

## Simple Queries

The **Query** tab provides a SQL editor for simple SELECT queries. Use it to retrieve specific data from one or more tables.

::: warning Warning
Only `SELECT` statements are allowed. Write statements (`INSERT`, `UPDATE`, `DELETE`, `DROP`, etc.) are blocked by the system. Queries have a time limit of 30 seconds.
:::

### Example Queries

**All active products with SKU:**

```sql
SELECT id, sku, created_at
FROM products
WHERE status = 'active'
ORDER BY created_at DESC
LIMIT 100
```

**Products with a specific attribute value:**

```sql
SELECT p.sku, pav.value
FROM products p
JOIN product_attribute_values pav ON p.id = pav.product_id
JOIN attributes a ON pav.attribute_id = a.id
WHERE a.code = 'color' AND pav.value = 'Red'
LIMIT 50
```

**Product count per hierarchy node:**

```sql
SELECT hn.name, COUNT(phn.product_id) AS product_count
FROM hierarchy_nodes hn
LEFT JOIN product_hierarchy_node phn ON hn.id = phn.hierarchy_node_id
GROUP BY hn.id, hn.name
ORDER BY product_count DESC
```

## Exporting Results

Both the data view and query results can be exported:

1. Click **Export** above the results table.
2. Select the format:

| Format | Description |
|---|---|
| **CSV** | Comma-separated values |
| **JSON** | Structured JSON array |

3. The download starts automatically.

::: tip Note
Exports are limited to a maximum of 10,000 rows. For larger datasets, use filtering or restrict your query with `LIMIT`.
:::

## Best Practices

- **Troubleshooting** -- Use the Database Viewer to analyze data inconsistencies before making changes through the application interface.
- **Understand the Data Model** -- Explore the table structures to gain a deeper understanding of the anyPIM data model, especially before developing integrations.
- **Mind Performance** -- Avoid queries without `LIMIT` on large tables to prevent impacting system performance.
- **Sensitive Data** -- Be aware that the users table contains password hashes. Do not share query results containing sensitive data.

## Next Steps

- Learn more about the [data model](../architektur/datenmodell) to understand the relationships between tables.
- Use the [API Tester](./api-tester) for structured data access through the REST API.
- Return to the [overview](../bedienung/index) to explore other functional areas.
