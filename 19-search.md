# anyPIM — Search & Filtering

> **Purpose:** Global search, full-text search, phonetic search, and filter system. Use this skill when implementing product search, the command palette, filter bar, search profiles, or search index maintenance.

---

## Search Architecture

The search system uses a **denormalized search index** (`products_search_index`) for fast queries. This table is defined in `01-datenmodell` and contains pre-computed, flattened product data.

### products_search_index (Reference)

```
product_id CHAR(36) PK, sku, ean, product_type, status,
name_de VARCHAR(500), name_en VARCHAR(500), description_de TEXT,
hierarchy_path VARCHAR(1000), primary_image VARCHAR(500),
list_price DECIMAL(12,2), attribute_completeness TINYINT,
phonetic_name_de VARCHAR(100), updated_at TIMESTAMP,
FULLTEXT(name_de, name_en), FULLTEXT(description_de),
INDEX(status), INDEX(product_type), INDEX(sku), INDEX(list_price)
```

### Why a Denormalized Index?

The normalized PIM data model (products + attribute values + hierarchy assignments + prices) requires multiple JOINs for a simple product list. The search index pre-joins all relevant data into a single flat table, enabling sub-50ms queries even with 100k+ products.

---

## Search API

### Primary Search Endpoint

```
POST /api/v1/products/search
```

### Request Body

```json
{
  "query": "Bohrmaschine",
  "search_mode": "fulltext",
  "filters": {
    "status": ["active"],
    "product_type": ["physical_product", "bundle"],
    "hierarchy_node_id": "uuid-node-123",
    "price_min": 100.00,
    "price_max": 500.00,
    "completeness_min": 50,
    "attributes": {
      "manufacturer_name": "Bosch",
      "voltage": { "min": 12, "max": 36 }
    }
  },
  "sort": "relevance",
  "order": "desc",
  "page": 1,
  "per_page": 25
}
```

### Search Modes

| Mode | Description | Performance Target |
|------|-------------|-------------------|
| `simple` | Exact match on SKU, EAN, or LIKE on name | < 50ms |
| `fulltext` | MySQL FULLTEXT search on name_de, name_en, description_de | < 80ms |
| `phonetic` | Phonetic similarity search (sounds-like matching) | < 100ms |
| `combined` | Fulltext + phonetic fallback if fulltext yields < 3 results | < 120ms |

### Response

```json
{
  "data": [
    {
      "id": "uuid-product-1",
      "sku": "BM-2024-001",
      "name": "Bohrmaschine ProMax 800",
      "product_type": "physical_product",
      "status": "active",
      "hierarchy_path": "Werkzeuge/Elektrowerkzeuge/Bohrmaschinen",
      "primary_image": "/media/bm-2024-001-thumb.jpg",
      "list_price": 299.99,
      "attribute_completeness": 85,
      "relevance_score": 12.45
    }
  ],
  "meta": {
    "total": 42,
    "page": 1,
    "per_page": 25,
    "search_mode": "fulltext",
    "query_time_ms": 65
  }
}
```

---

## Full-Text Search

Uses MySQL FULLTEXT indexes in **boolean mode** for precise control:

```sql
SELECT *, MATCH(name_de, name_en) AGAINST(:query IN BOOLEAN MODE) AS relevance
FROM products_search_index
WHERE MATCH(name_de, name_en) AGAINST(:query IN BOOLEAN MODE)
ORDER BY relevance DESC
LIMIT 25;
```

### Boolean Mode Operators

| Operator | Example | Meaning |
|----------|---------|---------|
| `+` | `+Bohrmaschine +Bosch` | Both words required |
| `-` | `Bohrmaschine -Makita` | Exclude Makita |
| `*` | `Bohr*` | Wildcard (prefix) |
| `""` | `"ProMax 800"` | Exact phrase |
| `>` | `>Profi Bohrmaschine` | Increase relevance |

---

## Phonetic Search

### Kolner Phonetik (Primary — German)

The Kolner Phonetik algorithm is optimized for German language. It converts words to phonetic codes, enabling "sounds-like" matching.

| Input | Phonetic Code |
|-------|---------------|
| Bohrmaschine | 178246 |
| Bormaschine | 178246 |
| Bohrmashine | 178246 |
| Schraubenzieher | 4712647 |

The phonetic code is pre-computed and stored in `products_search_index.phonetic_name_de`.

### Soundex (Fallback — English)

For English product names, MySQL's built-in `SOUNDEX()` function is used as a fallback.

```sql
-- Phonetic search query
SELECT * FROM products_search_index
WHERE phonetic_name_de = kolner_phonetik(:query)
   OR SOUNDEX(name_en) = SOUNDEX(:query)
ORDER BY name_de
LIMIT 25;
```

---

## Command Palette (Cmd+K)

The command palette provides a unified search across all entity types and actions. It is a frontend feature backed by a dedicated API endpoint.

```
GET /api/v1/command-palette?q=bohr&limit=10
```

### Response

```json
{
  "results": [
    {
      "type": "product",
      "id": "uuid-1",
      "title": "BM-2024-001 — Bohrmaschine ProMax 800",
      "subtitle": "Active · Physical Product",
      "url": "/products/uuid-1"
    },
    {
      "type": "attribute",
      "id": "uuid-2",
      "title": "bore_diameter",
      "subtitle": "Attribute · Number · Dimensions",
      "url": "/attributes/uuid-2"
    },
    {
      "type": "hierarchy_node",
      "id": "uuid-3",
      "title": "Bohrmaschinen",
      "subtitle": "Node · Werkzeuge/Elektrowerkzeuge/Bohrmaschinen",
      "url": "/hierarchies/uuid-h1/nodes/uuid-3"
    },
    {
      "type": "action",
      "key": "create_product",
      "title": "Create Product",
      "url": "/products/create"
    }
  ]
}
```

### Searchable Entity Types

| Type | Searched Fields |
|------|----------------|
| product | SKU, EAN, name_de, name_en |
| attribute | technical_name, name_de, name_en |
| hierarchy_node | name_de, name_en, path |
| action | Action labels (create product, start import, etc.) |

---

## Filter Bar

The filter bar uses a **chips-based UI** where each active filter is displayed as a removable chip.

### Available Filters

| Filter | Type | Values |
|--------|------|--------|
| status | Multi-select | draft, active, inactive, discontinued |
| product_type | Multi-select | All product types from `product_types` table |
| hierarchy_node_id | Tree-select | Any hierarchy node (includes children) |
| attribute values | Dynamic | Based on attribute data type (text, number range, select, flag) |
| completeness | Range slider | 0–100% |
| price range | Min/Max | Numeric |
| created_after | Date | Date picker |
| updated_after | Date | Date picker |
| has_media | Boolean | Yes/No |
| has_variants | Boolean | Yes/No |

### Filter Encoding in API

Filters are sent in the request body (POST) or as query parameters (GET):

```
GET /api/v1/products?filter[status]=active,draft&filter[product_type]=physical_product
GET /api/v1/products?filter[completeness_min]=50&filter[price_min]=100
```

---

## Search Profiles

Saved filter combinations that can be reused and shared.

### search_profiles

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | CHAR(36) PK | No | UUID |
| name | VARCHAR(255) | No | Profile name |
| description | TEXT | Yes | Description |
| filters | JSON | No | Saved filter configuration |
| is_shared | BOOLEAN | No | Visible to all users (default: false) |
| created_by | FK → users.id | No | Owner |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | |

### API Endpoints

```
GET    /api/v1/search-profiles                 List all profiles (own + shared)
POST   /api/v1/search-profiles                 Create profile
GET    /api/v1/search-profiles/{id}            Get profile
PUT    /api/v1/search-profiles/{id}            Update profile
DELETE /api/v1/search-profiles/{id}            Delete profile
POST   /api/v1/search-profiles/{id}/apply      Apply profile → returns search results
```

### Create Search Profile

```json
// POST /api/v1/search-profiles
{
  "name": "Active power tools under 500 EUR",
  "filters": {
    "status": ["active"],
    "product_type": ["physical_product"],
    "hierarchy_node_id": "uuid-power-tools",
    "price_max": 500.00,
    "completeness_min": 75
  },
  "is_shared": true
}
```

---

## Search Index Maintenance

### Automatic Updates (Model Observers)

The search index is updated automatically when products are created, updated, or deleted via Model Observers.

```php
// Triggered on Product save
class ProductSearchIndexObserver
{
    public function saved(Product $product): void
    {
        dispatch(new UpdateSearchIndexJob($product->id));
    }

    public function deleted(Product $product): void
    {
        ProductsSearchIndex::where('product_id', $product->id)->delete();
    }
}
```

### Manual Rebuild

```bash
# Full rebuild of the search index
php artisan pim:search-index --rebuild

# Rebuild for a specific product type
php artisan pim:search-index --rebuild --product-type=physical_product

# Update only stale entries (where product.updated_at > index.updated_at)
php artisan pim:search-index --update-stale

# Show index statistics
php artisan pim:search-index --stats
```

### Index Statistics Output

```
Products Search Index Statistics:
  Total indexed:      12,450
  Stale entries:      23
  Missing entries:    0
  Index size:         45 MB
  Last full rebuild:  2026-03-14 06:00:00
```

---

## Performance Targets

| Operation | Target | Index Used |
|-----------|--------|------------|
| Simple search (SKU/EAN exact) | < 50ms | `INDEX(sku)`, `INDEX(ean)` |
| Full-text search | < 80ms | `FULLTEXT(name_de, name_en)` |
| Phonetic search | < 100ms | `INDEX` on `phonetic_name_de` |
| Combined search | < 120ms | Multiple indexes |
| Filter-only (no text query) | < 50ms | Composite indexes |
| Command palette | < 100ms | Multiple tables, UNION query |

Performance is measured at the database query level, excluding network latency.

---

## Laravel Classes

| Type | Class | Path |
|------|-------|------|
| Model | `App\Models\ProductsSearchIndex` | `app/Models/ProductsSearchIndex.php` |
| Model | `App\Models\SearchProfile` | `app/Models/SearchProfile.php` |
| Controller | `App\Http\Controllers\Api\ProductSearchController` | `app/Http/Controllers/Api/` |
| Controller | `App\Http\Controllers\Api\CommandPaletteController` | `app/Http/Controllers/Api/` |
| Controller | `App\Http\Controllers\Api\SearchProfileController` | `app/Http/Controllers/Api/` |
| Service | `App\Services\Search\ProductSearchService` | `app/Services/Search/` |
| Service | `App\Services\Search\PhoneticService` | `app/Services/Search/` |
| Service | `App\Services\Search\SearchIndexService` | `app/Services/Search/` |
| Observer | `App\Observers\ProductSearchIndexObserver` | `app/Observers/` |
| Job | `App\Jobs\UpdateSearchIndexJob` | `app/Jobs/` |
| Job | `App\Jobs\RebuildSearchIndexJob` | `app/Jobs/` |
| Command | `App\Console\Commands\SearchIndexCommand` | `app/Console/Commands/` |
| FormRequest | `App\Http\Requests\ProductSearchRequest` | `app/Http/Requests/` |
