---
title: Relation Types
---

# Relation Types

Relation Types in anyPIM define the kinds of connections that can exist between products. By configuring different relation types, you can model cross-sell recommendations, upsell opportunities, accessory assignments, spare parts references, and any other product-to-product relationships relevant to your business. Relations enrich your product data and enable downstream systems to present related products to customers.

## Overview

Navigate to **Relation Types** in the sidebar to access the relation type management. The overview shows all defined relation types in a table:

| Column | Description |
|---|---|
| **Name** | The display name of the relation type (e.g., "Cross-Sell") |
| **Technical Key** | A unique system identifier in snake_case (e.g., `cross_sell`) |
| **Direction** | Whether the relation is unidirectional or bidirectional |
| **Relations** | The total number of product relations using this type |

## Creating a Relation Type

Click the **+ New Relation Type** button to open the creation form. The following fields are available:

| Field | Description | Required |
|---|---|---|
| **Name** | Display name shown in the product detail view | Yes |
| **Technical Key** | Unique identifier used in API calls and exports (snake_case) | Yes |
| **Description** | An internal description explaining when this relation type should be used | No |
| **Direction** | Whether the relation applies in one direction or both (see below) | Yes |

### Directionality

The direction setting determines how a relation behaves between two products:

| Direction | Behavior |
|---|---|
| **Unidirectional** | The relation applies only from product A to product B. Product B does not automatically show a relation back to product A. |
| **Bidirectional** | The relation applies in both directions. When you link product A to product B, product B automatically shows a relation back to product A. |

::: tip Note
Choose **Bidirectional** for symmetric relationships like "is related to" or "is compatible with." Choose **Unidirectional** for directional relationships like "is accessory of" or "replaces," where the relationship has a clear source and target.
:::

## Default Relation Types

anyPIM comes with several predefined relation types that cover common use cases:

| Relation Type | Technical Key | Direction | Typical Use |
|---|---|---|---|
| **Cross-Sell** | `cross_sell` | Unidirectional | Suggest related products that complement a purchase |
| **Up-Sell** | `up_sell` | Unidirectional | Recommend higher-value alternatives |
| **Accessories** | `accessories` | Unidirectional | Link accessory products to a main product |
| **Spare Parts** | `spare_parts` | Unidirectional | Reference replacement parts for a product |
| **Similar Products** | `similar` | Bidirectional | Connect products that serve the same purpose |
| **Successor** | `successor` | Unidirectional | Link a product to its newer replacement |

These default types can be edited or deleted if they do not match your requirements. You can also create additional types to model any custom relationship.

## Assigning Relations Between Products

Once relation types are defined, you can assign relations between products in the product detail view.

### Adding a Relation

1. Open the product detail view and navigate to the **Relations** tab.
2. Select the desired relation type from the dropdown (e.g., "Cross-Sell").
3. Click **+ Add Relation**.
4. A product search dialog opens. Search for the target product by SKU or name.
5. Select the target product and confirm.

The relation is created immediately and appears in the relations list under the selected type.

### Viewing Relations

The Relations tab organizes all assigned relations by type. Each section shows:

- **Relation type heading** -- The name of the relation type.
- **Product list** -- All products linked under this type, with SKU, name, and status.
- **Remove button** -- An action to delete the individual relation.

### Removing a Relation

Click the **Remove** icon next to a related product to delete the relation. For bidirectional relations, removing the relation from one product automatically removes it from the other product as well.

## Relation Management in Bulk

For large catalogs, managing relations one by one can be time-consuming. anyPIM supports bulk relation management through the following methods:

### Import

Product relations can be imported via Excel or CSV files. The import file should contain the following columns:

| Column | Description |
|---|---|
| **Source SKU** | The SKU of the product that holds the relation |
| **Target SKU** | The SKU of the product being related to |
| **Relation Type** | The technical key of the relation type (e.g., `cross_sell`) |

::: warning Warning
When importing relations, both the source and target products must already exist in the system. Relations referencing unknown SKUs will be skipped and reported in the import log.
:::

### Export

Relations can be included in export profiles as a structured field. Each relation is exported with its type, direction, and the target product's SKU and name.

## Editing a Relation Type

Click on a relation type in the overview to open the edit form. You can modify the display name, description, and direction. The technical key cannot be changed after creation.

::: danger Warning
Changing the direction of a relation type from unidirectional to bidirectional (or vice versa) affects all existing relations of that type. Bidirectional relations that become unidirectional will lose their reverse link. Review existing relations before changing the direction.
:::

## Deleting a Relation Type

To delete a relation type, open the detail view and click **Delete**. A confirmation dialog shows the number of product relations that will be removed. Deleting a relation type removes all product relations of that type from the system.

## Next Steps

- Learn how to manage relations in the [Products](./products) detail view under the Relations tab.
- Use [Manufacturers](./manufacturers) alongside relations to organize products by supplier.
- Explore [Export Jobs](../advanced/export-jobs) to include relation data in your exports.
