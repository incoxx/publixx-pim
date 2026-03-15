---
title: Journal
---

# Journal

The Journal is anyPIM's system-wide change log. It records all data changes -- from creation through editing to deletion of records. For each operation, the old and new values are stored, ensuring that changes can be traced and reviewed at any time.

## Overview

Access the Journal via **Administration > Journal** in the sidebar. The main view displays a chronological list of all data changes in the system.

| Column | Description |
|---|---|
| **Timestamp** | Date and time of the change |
| **User** | Name of the user who made the change |
| **Entity** | Type of the changed record (Product, Attribute, Hierarchy, etc.) |
| **Action** | Type of change (Created, Updated, Deleted) |
| **Label** | Name or identifier of the affected record |
| **Fields** | Number of changed fields |

::: tip Note
The Journal differs from the [User Audit](./user-audit), which logs user actions such as logins. The Journal focuses exclusively on content-level data changes.
:::

## Tracked Entities

The Journal records changes to the following entity types:

| Entity | Description | Example |
|---|---|---|
| **Product** | Product master data and attribute values | SKU, name, status, all attribute values |
| **Attribute** | Attribute definitions | Name, type, validation rules |
| **Attribute Group** | Grouping of attributes | Name, sort order |
| **Hierarchy** | Hierarchy definitions | Name, type |
| **Hierarchy Node** | Nodes within a hierarchy | Name, parent node, sort order |
| **Media** | Media files | Filename, type, assignments |
| **Price** | Price data | Amount, currency, validity |
| **User** | User account data | Name, email, role |
| **Value List** | Predefined selection values | Entries, sort order |

## Action Types

Each journal entry is associated with one of three action types:

### Created

Records the creation of a new record. All initial values are logged as "new values."

### Updated

Records the modification of an existing record. Both old and new values are stored, allowing every change to be reviewed in detail.

### Deleted

Records the deletion of a record. The last values before deletion are logged as "old values."

## Detail View

Click on a journal entry to view the full change details. The detail view shows all changed fields in a comparison layout:

| Column | Description |
|---|---|
| **Field** | Name of the changed field |
| **Old Value** | Value before the change |
| **New Value** | Value after the change |

Changed values are color-coded: removed content appears in red, added content in green.

::: tip Note
For extensive text changes (e.g., product descriptions), the detail view displays a diff that highlights added and removed text passages at the word level.
:::

## Filter Options

The journal list can be filtered by several criteria:

| Filter | Description | Example |
|---|---|---|
| **Entity Type** | Filter by record type | Products only |
| **Action** | Filter by change type | Deletions only |
| **User** | Filter by the user who made the change | John Smith |
| **Date Range** | Filter by start/end date | 2026-03-01 -- 2026-03-15 |
| **Search Term** | Full-text search in labels and values | "ABS-100-PRO" |

Filters can be combined freely. The result list updates in real time.

### Quick Filters

Clicking the entity name in the journal list takes you directly to a filtered view of all changes for that specific record. This allows you to review the complete change history of a single product, attribute, or other entity.

## Search

The Journal offers a full-text search that covers the following fields:

- Label of the changed record
- Old and new field values
- Username of the person who made the change
- Field names

Enter your search term in the search field. Results are displayed sorted by relevance.

## Retention

Journal entries are retained indefinitely by default. A maximum retention period can be configured in the system settings:

| Setting | Default | Description |
|---|---|---|
| `journal.retention_days` | 0 (unlimited) | Retention period in days |
| `journal.archive_enabled` | false | Automatic archiving of old entries |

::: warning Warning
Shortening the retention period will delete older entries. This action cannot be undone.
:::

## Best Practices

- **Regular Review** -- Check the journal regularly to detect unintended changes early.
- **Review Before Deletion** -- Before deleting data, review the journal entry to ensure no important information is lost.
- **Training** -- Familiarize your team with the Journal so users can independently trace who made which changes.
- **Retention Policy** -- Define a retention policy that meets your regulatory requirements.

## Next Steps

- Use the [User Audit](./user-audit) to monitor login events and security-relevant actions.
- Learn more about [Roles & Permissions](./roles) to control who can modify data.
- Return to the [overview](../bedienung/index) to explore other functional areas.
