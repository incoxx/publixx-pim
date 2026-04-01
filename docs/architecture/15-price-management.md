# anyPIM — Pricing & Price Regions

> **Purpose:** Price management with regional support, scale pricing, and date ranges. Use this skill when working with price types, price regions, product prices, currency handling, or variant price inheritance.

---

## Data Model Overview

The pricing system builds on the core tables defined in `01-datenmodell`:

- **price_types** — Types of prices (retail, wholesale, cost, etc.)
- **product_prices** — Actual price values per product
- **price_regions** — Regional pricing zones (new table, see below)

---

## Price Types (Reference)

Defined in `01-datenmodell`. Key fields:

```
price_types: id, technical_name UNIQUE, name_de, name_en, name_json
```

Common price types:

| technical_name | name_de | name_en |
|---------------|---------|---------|
| retail | UVP | Retail Price |
| wholesale | Großhandelspreis | Wholesale Price |
| cost | Einkaufspreis | Cost Price |
| map | Mindestverkaufspreis | Minimum Advertised Price |
| special | Aktionspreis | Special Price |

---

## Price Regions

### price_regions

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | CHAR(36) PK | No | UUID |
| technical_name | VARCHAR(100) UNIQUE | No | e.g. `dach`, `nordics`, `benelux` |
| name_de | VARCHAR(255) | No | German display name |
| name_en | VARCHAR(255) | Yes | English display name |
| countries | JSON | No | ISO 3166-1 alpha-2 codes, e.g. `["DE","AT","CH"]` |
| currency | VARCHAR(3) | No | ISO 4217 currency code, e.g. `EUR` |
| is_default | BOOLEAN | No | Default: false. Only one region can be default |
| sort_order | INT | No | Display order |
| is_active | BOOLEAN | No | Default: true |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | |

```sql
CREATE TABLE price_regions (
  id CHAR(36) PRIMARY KEY,
  technical_name VARCHAR(100) NOT NULL UNIQUE,
  name_de VARCHAR(255) NOT NULL,
  name_en VARCHAR(255) NULL,
  countries JSON NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
  is_default BOOLEAN NOT NULL DEFAULT false,
  sort_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Example Regions

| technical_name | countries | currency |
|---------------|-----------|----------|
| dach | ["DE","AT","CH"] | EUR |
| nordics | ["SE","NO","DK","FI"] | SEK |
| benelux | ["BE","NL","LU"] | EUR |
| uk | ["GB"] | GBP |
| usa | ["US"] | USD |

---

## Product Prices (Extended)

The `product_prices` table (from `01-datenmodell`) supports regions via the `country` field. With price regions, the workflow becomes:

```
product_prices.country → matched against price_regions.countries JSON array
```

### Scale Pricing

Scale pricing uses `scale_from` and `scale_to` to define quantity brackets:

```json
// Example: Tiered pricing for product BM-2024-001, retail price, DACH region
[
  { "amount": 299.99, "currency": "EUR", "scale_from": 1,   "scale_to": 9,    "country": "DE" },
  { "amount": 279.99, "currency": "EUR", "scale_from": 10,  "scale_to": 49,   "country": "DE" },
  { "amount": 249.99, "currency": "EUR", "scale_from": 50,  "scale_to": null, "country": "DE" }
]
```

`scale_to: null` means "50 and above" (open-ended bracket).

### Valid-From / Valid-To Date Ranges

```json
{
  "amount": 249.99,
  "currency": "EUR",
  "valid_from": "2026-03-01",
  "valid_to": "2026-03-31",
  "country": "DE"
}
```

- `valid_from` — Price becomes effective on this date
- `valid_to` — Price expires after this date (NULL = no expiration)
- When multiple prices match for the same type/country, the most specific date range wins

### Price Resolution Order

1. Product's own price with matching date range + country + scale
2. Product's own price with matching date range + country (no scale)
3. Parent product's price (for variants, if not overridden)
4. No price found

---

## Currency Handling

- All currencies follow **ISO 4217** (3-letter codes)
- Amounts stored as `DECIMAL(12,2)` — no floating-point rounding issues
- Currency is stored per price row, not globally
- No automatic currency conversion — prices must be entered per region/currency

| Code | Currency | Symbol |
|------|----------|--------|
| EUR | Euro | € |
| USD | US Dollar | $ |
| GBP | British Pound | £ |
| CHF | Swiss Franc | CHF |
| SEK | Swedish Krona | kr |

---

## Price Inheritance (Variants)

Variants inherit prices from their parent product unless explicitly overridden:

1. Check `product_prices` for the variant's `product_id`
2. If no price found → use parent product's price (via `products.parent_product_id`)
3. Override: Creating a price row for the variant breaks inheritance for that specific price_type + country + scale combination

```php
// Price resolution in PriceService
public function resolvePrice(Product $product, PriceType $type, string $country): ?ProductPrice
{
    $price = $product->prices()
        ->where('price_type_id', $type->id)
        ->where('country', $country)
        ->whereDate('valid_from', '<=', now())
        ->where(fn($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', now()))
        ->orderBy('scale_from')
        ->first();

    if (!$price && $product->parent_product_id) {
        return $this->resolvePrice($product->parentProduct, $type, $country);
    }

    return $price;
}
```

---

## API Endpoints

### Price Types

```
GET    /api/v1/price-types                     List all price types
POST   /api/v1/price-types                     Create price type
GET    /api/v1/price-types/{id}                Get price type
PUT    /api/v1/price-types/{id}                Update price type
DELETE /api/v1/price-types/{id}                Delete price type
```

### Price Regions

```
GET    /api/v1/price-regions                   List all price regions
POST   /api/v1/price-regions                   Create region
GET    /api/v1/price-regions/{id}              Get region details
PUT    /api/v1/price-regions/{id}              Update region
DELETE /api/v1/price-regions/{id}              Delete region
```

### Product Prices

```
GET    /api/v1/products/{id}/prices            List all prices for a product
POST   /api/v1/products/{id}/prices            Add price to product
PUT    /api/v1/products/{id}/prices/{priceId}  Update a price
DELETE /api/v1/products/{id}/prices/{priceId}  Remove a price
GET    /api/v1/products/{id}/prices/resolved   Get resolved prices (with inheritance)
```

### Create/Update Price Request

```json
// POST /api/v1/products/{id}/prices
{
  "price_type": "retail",
  "amount": 299.99,
  "currency": "EUR",
  "country": "DE",
  "valid_from": "2026-01-01",
  "valid_to": null,
  "scale_from": 1,
  "scale_to": 9
}
```

---

## Laravel Classes

| Type | Class | Path |
|------|-------|------|
| Model | `App\Models\PriceType` | `app/Models/PriceType.php` |
| Model | `App\Models\PriceRegion` | `app/Models/PriceRegion.php` |
| Model | `App\Models\ProductPrice` | `app/Models/ProductPrice.php` |
| Controller | `App\Http\Controllers\Api\PriceTypeController` | `app/Http/Controllers/Api/` |
| Controller | `App\Http\Controllers\Api\PriceRegionController` | `app/Http/Controllers/Api/` |
| Controller | `App\Http\Controllers\Api\ProductPriceController` | `app/Http/Controllers/Api/` |
| Service | `App\Services\PriceService` | `app/Services/` |
| Resource | `App\Http\Resources\ProductPriceResource` | `app/Http/Resources/` |
| Resource | `App\Http\Resources\PriceRegionResource` | `app/Http/Resources/` |

---

## Permissions

```
price-types.view             View price types
price-types.create           Create price types
price-types.edit             Edit price types
price-types.delete           Delete price types
price-regions.view           View price regions
price-regions.edit           Manage price regions
prices.view                  View product prices
prices.edit                  Edit product prices
```
