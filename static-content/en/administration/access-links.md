---
title: Access Links
---

# Access Links

Access Links allow you to grant external parties temporary, read-only access to selected product data in anyPIM without creating a user account. Links are ideal for sharing product catalogs with trading partners, agencies, or internal stakeholders.

## Overview

Access the link management via **Administration > Access Links** in the sidebar. The overview displays all created links in a tabular format:

| Column | Description |
|---|---|
| **Label** | Freely chosen name of the access link |
| **URL** | The generated link URL |
| **Valid Until** | Expiry date of the link |
| **Password Protected** | Whether a password is required |
| **Views** | Number of times the link has been accessed |
| **Status** | Active, expired, or disabled |
| **Created By** | User who created the link |

::: tip Note
Only users with the **Admin** role or the `access-links.*` permissions (`access-links.view`, `access-links.create`, `access-links.delete`) can create and manage access links.
:::

## Creating an Access Link

1. Navigate to **Administration > Access Links**.
2. Click **+ New Access Link**.
3. Fill in the form:

| Field | Description | Required |
|---|---|---|
| **Label** | Descriptive name (e.g., "Spring 2026 Catalog -- Agency XY") | Yes |
| **Expiry Date** | Date on which the link automatically becomes invalid | Yes |
| **Password** | Optional password for additional protection | No |
| **Hierarchy Nodes** | Restrict access to specific hierarchy nodes | No |
| **Products** | Restrict access to specific products | No |
| **Attribute View** | Show only specific attributes | No |
| **Language** | Display language for product data | Yes |

4. Click **Create**.
5. Copy the generated URL and share it with the recipient.

::: warning Warning
The full link URL is displayed only once after creation. Copy the URL immediately and store it securely.
:::

## Access Restrictions

### Restricting to Hierarchies

You can restrict the access link to one or more hierarchy nodes. The recipient will only see products assigned to the selected nodes (and their child nodes). All other product data remains hidden.

### Restricting to Products

Alternatively or additionally, you can restrict the link to an explicit list of products. Select the desired products using the search function.

### Attribute View

By selecting an attribute view, you control which product attributes are visible to the recipient. For example, you can share only marketing texts and images without revealing technical data or purchase prices.

## Password Protection

When a password is set, the recipient must enter the password on first access. The session then remains active for the duration of the browser window.

::: danger Warning
Always send the password through a separate communication channel (e.g., phone call or text message), never together with the link URL in the same email.
:::

## Tracking Usage

In the detail area of each access link, you will find access statistics:

| Information | Description |
|---|---|
| **Total Views** | Total number of link accesses |
| **Unique Visitors** | Number of distinct IP addresses |
| **Last Access** | Date and time of the most recent access |
| **Access Log** | Chronological list of all individual accesses with IP, timestamp, and user agent |

## Managing Links

### Disabling a Link

You can disable an active link at any time without deleting it. Click **Disable** in the detail view. The link becomes inaccessible immediately but can be re-enabled if needed.

### Extending a Link

The expiry date of an existing link can be changed at any time. Open the link in the detail view and adjust the date in the **Valid Until** field.

### Deleting a Link

Links that are no longer needed can be deleted from the detail view. The access statistics will also be removed.

## Best Practices

- **Short Validity Periods** -- Set the expiry date as short as practical. Extend if needed rather than granting long lifetimes upfront.
- **Use Password Protection** -- Enable password protection for links that expose sensitive product data.
- **Monitor Access** -- Regularly check the access statistics to identify unexpected usage.
- **Descriptive Labels** -- Use meaningful names that identify the purpose and recipient of the link.
- **Minimal Data Exposure** -- Restrict access to only the hierarchies, products, and attributes that are actually needed.

## Next Steps

- Learn more about [Roles & Permissions](./roles) to manage internal access control.
- Use the [User Audit](./user-audit) to track access to the system.
- Return to the [overview](../usage/index) to explore other functional areas.
