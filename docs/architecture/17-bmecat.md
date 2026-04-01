# anyPIM — BMEcat Import/Export

> **Purpose:** BMEcat XML catalog exchange (import and export). Use this skill when implementing BMEcat parsing, field mapping, XML generation, or BMEcat-specific API endpoints. This is an enterprise module requiring a valid license.

---

## BMEcat Standard Overview

BMEcat is an XML-based standard for exchanging product catalog data between suppliers and buyers, widely used in B2B commerce in Germany and Europe.

| Aspect | Details |
|--------|---------|
| Standard | BMEcat (Business-to-business Marketplace Electronic CATalog) |
| Versions | 1.2 (legacy), 2005 (current) |
| Format | XML |
| Schema | Defined by BME e.V. (Bundesverband Materialwirtschaft, Einkauf und Logistik) |
| Use case | Catalog exchange between ERP, PIM, and procurement systems |

### License Requirement

BMEcat import/export is an **enterprise module**. It requires an active anyPIM enterprise license. API endpoints return `403 Forbidden` with a license error if the module is not activated.

---

## BMEcat Field Mapping

### Standard Fields → PIM Attributes

| BMEcat Field | BMEcat Path | PIM Mapping | Direction |
|-------------|-------------|-------------|-----------|
| SUPPLIER_AID | `T_NEW_CATALOG/ARTICLE/SUPPLIER_AID` | `products.sku` | Import + Export |
| DESCRIPTION_SHORT | `ARTICLE_DETAILS/DESCRIPTION_SHORT` | `products.name` (language-specific) | Import + Export |
| DESCRIPTION_LONG | `ARTICLE_DETAILS/DESCRIPTION_LONG` | `attribute: description` | Import + Export |
| EAN | `ARTICLE_DETAILS/EAN` | `products.ean` | Import + Export |
| MANUFACTURER_NAME | `ARTICLE_DETAILS/MANUFACTURER_NAME` | `attribute: manufacturer_name` | Import + Export |
| MANUFACTURER_AID | `ARTICLE_DETAILS/MANUFACTURER_AID` | `attribute: manufacturer_aid` | Import + Export |
| ARTICLE_ORDER | `ARTICLE_ORDER_DETAILS/ORDER_UNIT` | `attribute: order_unit` | Import + Export |
| PRICE | `ARTICLE_PRICE_DETAILS/ARTICLE_PRICE` | `product_prices` | Import + Export |
| MIME | `ARTICLE/MIME_INFO/MIME` | `media + product_media_assignments` | Import + Export |
| FEATURE | `ARTICLE_FEATURES/FEATURE` | `product_attribute_values` | Import + Export |
| KEYWORD | `ARTICLE_DETAILS/KEYWORD` | `attribute: keywords` | Import + Export |
| ARTICLE_STATUS | `ARTICLE_DETAILS/ARTICLE_STATUS` | `products.status` | Import + Export |

### Price Mapping

| BMEcat Price Element | PIM Field |
|---------------------|-----------|
| `ARTICLE_PRICE/price_type` | `price_types.technical_name` |
| `ARTICLE_PRICE/PRICE_AMOUNT` | `product_prices.amount` |
| `ARTICLE_PRICE/PRICE_CURRENCY` | `product_prices.currency` |
| `ARTICLE_PRICE/LOWER_BOUND` | `product_prices.scale_from` |
| `ARTICLE_PRICE/TERRITORY` | `product_prices.country` |
| `ARTICLE_PRICE/DATETIME[@type='valid_start_date']` | `product_prices.valid_from` |
| `ARTICLE_PRICE/DATETIME[@type='valid_end_date']` | `product_prices.valid_to` |

### Feature (Attribute) Mapping

BMEcat features map to PIM attributes via `technical_name`:

```xml
<FEATURE>
  <FNAME>weight</FNAME>          <!-- → attribute.technical_name -->
  <FVALUE>2.5</FVALUE>           <!-- → product_attribute_values.value_number -->
  <FUNIT>KGM</FUNIT>             <!-- → ISO unit code → units.technical_name -->
</FEATURE>
```

---

## Import Flow

```
1. Upload XML    →  POST /api/v1/bmecat-import (multipart/form-data)
2. Parse         →  XML is parsed, structure validated against BMEcat schema
3. Map           →  BMEcat fields mapped to PIM attributes (using mapping config)
4. Validate      →  Data validation (required fields, data types, references)
5. Preview       →  GET /api/v1/bmecat-import/{id} returns preview (products found, mapping status)
6. Execute       →  POST /api/v1/bmecat-import/{id}/execute — commits data to PIM
```

### Mapping Configuration

Custom field mappings can be defined to override defaults:

```json
{
  "mappings": [
    {
      "bmecat_field": "DESCRIPTION_SHORT",
      "pim_attribute": "short_description",
      "language": "de"
    },
    {
      "bmecat_field": "FEATURE:voltage",
      "pim_attribute": "operating_voltage",
      "unit_mapping": { "VLT": "volt" }
    }
  ],
  "defaults": {
    "product_type": "physical_product",
    "status": "draft"
  },
  "options": {
    "update_existing": true,
    "skip_unknown_features": true,
    "version": "2005"
  }
}
```

---

## Export Flow

```
1. Configure     →  Select products, mapping, and options
2. Map           →  PIM attributes mapped to BMEcat fields
3. Generate      →  XML document generated according to BMEcat schema
4. Download      →  Resulting XML file available for download
```

### Export Configuration

```json
{
  "product_filter": {
    "status": "active",
    "product_type": "physical_product",
    "hierarchy_node_id": "uuid-node-123"
  },
  "version": "2005",
  "language": "de",
  "include_prices": true,
  "include_media": true,
  "include_features": true,
  "supplier_name": "Mustermann GmbH",
  "catalog_id": "CAT-2026-Q1",
  "catalog_name": "Product Catalog Q1 2026",
  "currency": "EUR",
  "territory": ["DE", "AT", "CH"]
}
```

---

## API Endpoints

### Import

```
POST   /api/v1/bmecat-import                   Upload BMEcat XML file
GET    /api/v1/bmecat-import/{id}               Get import status and preview
POST   /api/v1/bmecat-import/{id}/execute       Execute the import
DELETE /api/v1/bmecat-import/{id}               Cancel and delete import
```

### Export

```
POST   /api/v1/bmecat-export                    Start BMEcat export
GET    /api/v1/bmecat-export/{id}               Get export status
GET    /api/v1/bmecat-export/{id}/download       Download generated XML
```

### Upload Example

```bash
curl -X POST /api/v1/bmecat-import \
  -H "Authorization: Bearer {token}" \
  -F "file=@catalog.xml" \
  -F "version=2005" \
  -F "mappings={\"defaults\":{\"product_type\":\"physical_product\"}}"
```

### Import Response

```json
{
  "data": {
    "id": "uuid-import-1",
    "status": "validated",
    "file_name": "catalog.xml",
    "version": "2005",
    "summary": {
      "articles_found": 250,
      "new_products": 45,
      "existing_products": 205,
      "unmapped_features": 3,
      "validation_errors": 1
    },
    "unmapped_features": ["custom_field_xyz", "internal_code", "legacy_weight"],
    "errors": [
      { "article": "ART-999", "field": "EAN", "error": "Invalid EAN checksum" }
    ]
  }
}
```

---

## BMEcat XML Structure (2005)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<BMECAT version="2005" xmlns="http://www.bmecat.org/bmecat/2005">
  <HEADER>
    <CATALOG>
      <CATALOG_ID>CAT-2026-Q1</CATALOG_ID>
      <CATALOG_NAME>Product Catalog Q1 2026</CATALOG_NAME>
      <LANGUAGE>deu</LANGUAGE>
      <CURRENCY>EUR</CURRENCY>
    </CATALOG>
    <SUPPLIER><SUPPLIER_NAME>Mustermann GmbH</SUPPLIER_NAME></SUPPLIER>
  </HEADER>
  <T_NEW_CATALOG>
    <ARTICLE>
      <SUPPLIER_AID>BM-2024-001</SUPPLIER_AID>
      <ARTICLE_DETAILS>
        <DESCRIPTION_SHORT>Bohrmaschine ProMax 800</DESCRIPTION_SHORT>
        <EAN>4006209001234</EAN>
      </ARTICLE_DETAILS>
      <ARTICLE_PRICE_DETAILS>
        <ARTICLE_PRICE price_type="net_list">
          <PRICE_AMOUNT>299.99</PRICE_AMOUNT>
          <PRICE_CURRENCY>EUR</PRICE_CURRENCY>
          <TERRITORY>DE</TERRITORY>
        </ARTICLE_PRICE>
      </ARTICLE_PRICE_DETAILS>
    </ARTICLE>
  </T_NEW_CATALOG>
</BMECAT>
```

---

## Laravel Classes

| Type | Class | Path |
|------|-------|------|
| Controller | `App\Http\Controllers\Api\BmecatImportController` | `app/Http/Controllers/Api/` |
| Controller | `App\Http\Controllers\Api\BmecatExportController` | `app/Http/Controllers/Api/` |
| Service | `App\Services\Bmecat\BmecatParserService` | `app/Services/Bmecat/` |
| Service | `App\Services\Bmecat\BmecatMapperService` | `app/Services/Bmecat/` |
| Service | `App\Services\Bmecat\BmecatExportService` | `app/Services/Bmecat/` |
| Service | `App\Services\Bmecat\BmecatValidatorService` | `app/Services/Bmecat/` |
| Job | `App\Jobs\ProcessBmecatImportJob` | `app/Jobs/` |
| Job | `App\Jobs\GenerateBmecatExportJob` | `app/Jobs/` |
| Middleware | `App\Http\Middleware\CheckBmecatLicense` | `app/Http/Middleware/` |

### Artisan Commands

```bash
# Import a BMEcat XML file
php artisan pim:bmecat-import /path/to/catalog.xml --version=2005

# Export to BMEcat XML
php artisan pim:bmecat-export --output=/tmp/catalog.xml --status=active --version=2005

# Validate a BMEcat file without importing
php artisan pim:bmecat-validate /path/to/catalog.xml
```
