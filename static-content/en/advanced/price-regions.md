---
title: Price Regions
---

# Price Regions

The Price Regions module in anyPIM enables you to define geographic regions for managing location-specific product pricing. Each region is assigned a currency and can have its own price lists, allowing you to maintain different prices for different markets from within a single product catalog. This is essential for businesses that sell products internationally or across distinct market zones with varying pricing strategies.

## Region Overview

Navigate to **Price Regions** in the sidebar to see all configured regions:

| Column | Description |
|---|---|
| **Name** | The display name of the region (e.g., "Europe", "North America") |
| **Currency** | The assigned currency (e.g., EUR, USD, GBP) |
| **Countries** | The number of countries included in the region |
| **Products with Prices** | How many products have prices defined for this region |
| **Modified** | Timestamp of the last modification |

## Creating a Price Region

Click **+ New Region** to open the creation form:

| Field | Description | Required |
|---|---|---|
| **Name** | A descriptive name for the region | Yes |
| **Currency** | The currency used for all prices in this region | Yes |
| **Currency Symbol** | The display symbol (e.g., $, EUR) | Yes |
| **Countries** | A list of countries belonging to this region | No |
| **Description** | Internal notes about the region's purpose or scope | No |
| **Tax Included** | Whether prices in this region include tax | Yes |

After saving, the region is available for assigning prices to products.

### Currency Selection

anyPIM supports all standard ISO 4217 currency codes. When selecting a currency, both the three-letter code and the common name are displayed:

| Code | Currency | Symbol |
|---|---|---|
| EUR | Euro | EUR |
| USD | US Dollar | $ |
| GBP | British Pound | GBP |
| CHF | Swiss Franc | CHF |
| JPY | Japanese Yen | JPY |
| CNY | Chinese Yuan | CNY |
| CAD | Canadian Dollar | CA$ |
| AUD | Australian Dollar | A$ |

::: tip Note
Each price region uses exactly one currency. If you need to manage prices in multiple currencies for the same geographic area, create separate regions (for example, "Switzerland CHF" and "Switzerland EUR").
:::

## Assigning Countries to Regions

Countries can be assigned to regions for organizational purposes and to support region-based export filtering. Each country can belong to only one region.

### Adding Countries

1. Open the region detail view.
2. Click **+ Add Countries** in the countries section.
3. Select one or more countries from the country list.
4. Confirm the assignment.

### Country Conflict Detection

If you attempt to assign a country that is already part of another region, the system displays a warning indicating the conflict. You must remove the country from the existing region before assigning it to a new one.

::: warning Warning
Reassigning a country from one region to another does not automatically move the product prices. Products will retain their existing regional prices, but you may need to review and update them for the new region assignment.
:::

## Regional Price Lists

Once regions are defined, you can assign region-specific prices to products. Each product can have different prices for each region.

### Setting Prices per Region

1. Open the product detail view and navigate to the **Prices** tab.
2. The tab displays a section for each price region.
3. Enter the price value for the product in the respective region.
4. Save the product.

### Price Table Structure

The Prices tab presents a structured overview of all regional prices:

| Column | Description |
|---|---|
| **Region** | The name of the price region |
| **Currency** | The currency associated with the region |
| **List Price** | The standard retail price |
| **Net Price** | The wholesale or net price |
| **Sale Price** | An optional discounted price |
| **Valid From** | Start date for the price validity period |
| **Valid Until** | End date for the price validity period |

::: tip Note
Price fields beyond "List Price" are optional and depend on the price types configured in your system. The available price fields may vary based on your pricing setup.
:::

## Price Modifiers

Price modifiers allow you to apply systematic adjustments to prices for a specific region. Instead of entering individual prices for every product, you can define a modifier that adjusts prices based on a rule.

### Modifier Types

| Type | Description | Example |
|---|---|---|
| **Percentage Markup** | Adds a percentage on top of the base price | +15% for import costs |
| **Percentage Discount** | Reduces the base price by a percentage | -10% for promotional regions |
| **Fixed Amount Markup** | Adds a fixed amount to the base price | +5.00 for shipping surcharge |
| **Fixed Amount Discount** | Subtracts a fixed amount from the base price | -2.00 for bulk pricing |
| **Exchange Rate Factor** | Converts from a base currency using a multiplication factor | x1.08 for USD to EUR equivalent |

### Applying a Modifier

1. Open the price region detail view.
2. In the **Modifiers** section, click **+ Add Modifier**.
3. Select the modifier type and enter the value.
4. Choose the source price (the base region or base price type the modifier applies to).
5. Save the modifier.

Modifiers are applied automatically during price calculation. The resulting adjusted prices can be previewed in the product detail view and are used in exports for the respective region.

::: danger Warning
Modifiers are applied in the order they are defined. Multiple modifiers on the same region are calculated sequentially, not independently. Review the modifier order carefully to ensure the correct final price.
:::

## Exporting Regional Prices

When configuring export profiles, you can select which price regions to include. The export will contain the prices for the selected regions as separate columns or structured data, depending on the export format.

Regional prices are also available in the [Reports](./reports) module, where you can create region-specific pricing reports.

## Deleting a Price Region

To delete a region, open the detail view and click **Delete**. A confirmation dialog shows the number of products that have prices defined for this region.

::: danger Warning
Deleting a price region permanently removes all product prices associated with that region. This action cannot be undone. Consider exporting the regional prices before deleting as a backup.
:::

## Next Steps

- Learn about the [Pricing](../usage/pricing) configuration for price types and general pricing setup.
- Use [Export Jobs](./export-jobs) to deliver region-specific pricing to downstream channels.
- Explore [Reports](./reports) to generate regional pricing summaries and comparisons.
