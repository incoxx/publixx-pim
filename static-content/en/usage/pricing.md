---
title: Pricing
---

# Pricing

The price management of Publixx PIM enables the central maintenance of product prices in various price types and currencies. This chapter describes the configuration of price types, the entry of prices per product, and the management of validity periods.

## Price Types

Price types define the various categories of prices that can be maintained in the system. You can access the price type management via the **Prices** menu item in the sidebar.

### Default Price Types

The system supports any number of price types. Typical configurations include:

| Price Type | Description | Typical Usage |
|---|---|---|
| **List Price** (MSRP) | Manufacturer's suggested retail price | Catalog, online shop |
| **Net Price** | Price without VAT | B2B trade, key accounts |
| **Gross Price** | Price including VAT | B2C end-customer prices |
| **Promotional Price** | Time-limited special price | Promotions, sales |
| **Tiered Price** | Quantity-dependent price | Bulk orders |
| **Purchase Price** | Internal procurement price | Cost calculation, controlling |

### Creating a Price Type

1. Navigate to the price type management.
2. Click **+ New Price Type**.
3. Assign a technical name and display name (DE/EN).
4. Save the price type.

::: tip Note
Price types are defined system-wide and apply to all products. The actual price maintenance is done at the product level.
:::

## Currencies

Publixx PIM supports **multiple currencies** simultaneously. For each price, the currency is stored as an ISO 4217 code (e.g., EUR, USD, CHF, GBP).

### Available Currencies

The currencies configured in the system are offered as a selection field during price entry. Typical configurations:

| Code | Currency | Symbol |
|---|---|---|
| EUR | Euro | EUR |
| USD | US Dollar | $ |
| CHF | Swiss Franc | CHF |
| GBP | British Pound | £ |

The available currencies are defined in the system configuration.

## Price Maintenance per Product

Price entry is done in the **Product detail view** in the **Prices** tab. There you will see a tabular overview of all recorded prices for the current product.

### Creating a Price

1. Open the product detail view and switch to the **Prices** tab.
2. Click **+ Add Price**.
3. Fill in the price form:

| Field | Description | Required |
|---|---|---|
| **Price Type** | Selection of the price type (e.g., List Price) | Yes |
| **Amount** | Numeric price value | Yes |
| **Currency** | ISO 4217 currency code | Yes |
| **Valid From** | Start of validity | No |
| **Valid Until** | End of validity | No |

4. Save the price.

### Validity Periods

Prices can be given a **validity period**, defined via the "Valid From" and "Valid Until" fields:

- **Without validity** -- The price applies indefinitely.
- **With start date** -- The price applies from the specified date.
- **With end date** -- The price applies until the specified date.
- **With start and end date** -- The price applies only within the defined period.

Validity periods are particularly useful for **promotional prices** and seasonal price adjustments. During export, you can control whether only currently valid prices or all prices are exported.

::: info Example
A product has a list price of 49.99 EUR (without validity) and a promotional price of 39.99 EUR (valid from Dec 1 to Dec 24). During export on Dec 15, both prices are delivered. The promotional price can be treated as having priority by the target system.
:::

### Editing a Price

Click on an existing price in the table to edit it. You can change the amount, currency, and validity period. The price type cannot be changed after creation -- in this case, delete the price and create a new one.

### Deleting a Price

Click the delete icon next to a price to remove it. Deletion occurs after a confirmation prompt.

## Price Overview (Price Management)

Via the **Prices** menu item in the sidebar, you can access the global price overview. This displays a cross-product table of all recorded prices and offers the following functions:

- **Filter** by price type, currency, or validity status
- **Search** by product SKU or product name
- **Sort** by amount, price type, product, or validity date
- **Export** the price table for external processing

## Prices and Variants

Variants do **not** inherit prices from the parent product. Each variant has its own price structure, which is maintained separately. This enables different prices per variant (e.g., different prices for different sizes or finishes).

## Best Practices

- **Consistent price types** -- Use uniform price types in your system. Define which price types you need before the first price maintenance.
- **Validity periods** -- Use validity periods for temporary price changes instead of overwriting existing prices. This way you retain the price history.
- **Currency consistency** -- Maintain prices in all currencies that your export channels require. Do not rely on automatic conversion.

## Next Steps

- Learn how [Products](./products) are created and managed.
- Get to know the [Export](/en/export/) to transfer prices to external systems.
