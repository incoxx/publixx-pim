# anyPIM — Reports & PDF Templates

> **Purpose:** Report generation and PDF template engine. Use this skill when building report configurations, PDF layouts, template placeholders, or output generation endpoints.

---

## Data Model

### report_templates

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | CHAR(36) PK | No | UUID |
| name | VARCHAR(255) | No | Report name |
| description | TEXT | Yes | What this report contains |
| type | ENUM('table','chart','summary') | No | Report visualization type |
| config | JSON | No | Report configuration (fields, filters, grouping, aggregation) |
| output_format | ENUM('pdf','docx','csv','xlsx') | No | Default output format |
| schedule | VARCHAR(100) | Yes | Cron expression for scheduled execution (e.g. `0 6 * * 1`) |
| is_active | BOOLEAN | No | Default: true |
| created_by | FK → users.id | No | Owner |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | |

```sql
CREATE TABLE report_templates (
  id CHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  type ENUM('table','chart','summary') NOT NULL DEFAULT 'table',
  config JSON NOT NULL,
  output_format ENUM('pdf','docx','csv','xlsx') NOT NULL DEFAULT 'pdf',
  schedule VARCHAR(100) NULL,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_by CHAR(36) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### pdf_templates

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | CHAR(36) PK | No | UUID |
| name | VARCHAR(255) | No | Template name |
| description | TEXT | Yes | Description |
| layout | JSON | No | Page layout definition (sections, columns, positioning) |
| page_size | ENUM('A4','A3','Letter','Legal') | No | Default: A4 |
| orientation | ENUM('portrait','landscape') | No | Default: portrait |
| header_config | JSON | Yes | Header layout (logo, title, page numbers) |
| footer_config | JSON | Yes | Footer layout (copyright, date, page count) |
| margins | JSON | Yes | `{"top": 20, "right": 15, "bottom": 20, "left": 15}` mm |
| product_type_id | FK → product_types.id | Yes | Restrict to specific product type |
| is_default | BOOLEAN | No | Default: false |
| is_active | BOOLEAN | No | Default: true |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | |

---

## Report Config Structure

The `config` JSON field defines what data the report includes:

```json
{
  "fields": [
    { "source": "product.sku", "label": "Article No.", "width": 15 },
    { "source": "product.name", "label": "Product Name", "width": 30 },
    { "source": "attribute.weight", "label": "Weight (kg)", "width": 10 },
    { "source": "price.retail", "label": "Retail Price", "width": 12, "format": "currency" }
  ],
  "filters": {
    "status": "active",
    "product_type": "physical_product",
    "hierarchy_node_id": "uuid-node-123"
  },
  "grouping": {
    "field": "product_type",
    "show_subtotals": true
  },
  "aggregation": [
    { "field": "price.retail", "function": "avg", "label": "Average Price" },
    { "field": "product.id", "function": "count", "label": "Total Products" }
  ],
  "sorting": { "field": "product.sku", "direction": "asc" }
}
```

---

## PDF Template Placeholders

Templates use double-brace placeholders resolved at render time:

### Product Placeholders

| Placeholder | Description |
|-------------|-------------|
| `{{product.sku}}` | SKU / article number |
| `{{product.name}}` | Product name (current language) |
| `{{product.name_de}}` | German product name |
| `{{product.name_en}}` | English product name |
| `{{product.ean}}` | EAN / GTIN |
| `{{product.status}}` | Product status |
| `{{product.product_type}}` | Product type name |

### Attribute Placeholders

| Placeholder | Description |
|-------------|-------------|
| `{{attribute.<technical_name>}}` | Attribute value |
| `{{attribute.<technical_name>.unit}}` | Unit abbreviation |
| `{{attribute.<technical_name>.label}}` | Attribute display name |

### Price Placeholders

| Placeholder | Description |
|-------------|-------------|
| `{{price.<price_type>}}` | Price amount |
| `{{price.<price_type>.currency}}` | Currency code |
| `{{price.<price_type>.formatted}}` | Formatted price (e.g. "299,99 EUR") |

### Loop Constructs

```
{{#each attribute_group as group}}
  <h3>{{group.name}}</h3>
  {{#each group.attributes as attr}}
    <tr><td>{{attr.label}}</td><td>{{attr.value}} {{attr.unit}}</td></tr>
  {{/each}}
{{/each}}

{{#each product.variants as variant}}
  <tr><td>{{variant.sku}}</td><td>{{variant.name}}</td></tr>
{{/each}}
```

### System Placeholders

| Placeholder | Description |
|-------------|-------------|
| `{{system.date}}` | Current date |
| `{{system.datetime}}` | Current date and time |
| `{{system.page}}` | Current page number |
| `{{system.pages}}` | Total page count |
| `{{system.user}}` | Generating user's name |

---

## API Endpoints

### Report Templates

```
GET    /api/v1/report-templates                List all report templates
POST   /api/v1/report-templates                Create template
GET    /api/v1/report-templates/{id}           Get template details
PUT    /api/v1/report-templates/{id}           Update template
DELETE /api/v1/report-templates/{id}           Delete template
POST   /api/v1/report-templates/{id}/execute   Execute report → generate output
GET    /api/v1/report-templates/{id}/preview   Preview report (first 20 rows)
GET    /api/v1/report-templates/{id}/download  Download last generated report
```

### PDF Templates

```
GET    /api/v1/pdf-templates                   List all PDF templates
POST   /api/v1/pdf-templates                   Create template
GET    /api/v1/pdf-templates/{id}              Get template details
PUT    /api/v1/pdf-templates/{id}              Update template
DELETE /api/v1/pdf-templates/{id}              Delete template
POST   /api/v1/pdf-templates/{id}/render       Render PDF for a product
POST   /api/v1/pdf-templates/{id}/preview      Preview with sample data
POST   /api/v1/pdf-templates/{id}/bulk-render  Render PDF for multiple products
```

### Render Request

```json
// POST /api/v1/pdf-templates/{id}/render
{
  "product_id": "uuid-product-123",
  "language": "de",
  "include_variants": true,
  "include_prices": true,
  "price_region": "DACH"
}
```

### Bulk Render

```json
// POST /api/v1/pdf-templates/{id}/bulk-render
{
  "product_ids": ["uuid-1", "uuid-2", "uuid-3"],
  "language": "de",
  "merge": true
}
```

- `merge: true` — single PDF with all products
- `merge: false` — ZIP archive with one PDF per product

---

## Output Formats

| Format | Engine | Use Case |
|--------|--------|----------|
| PDF | DomPDF / wkhtmltopdf | Product data sheets, catalogs |
| DOCX | PHPWord | Editable documents |
| CSV | League\Csv | Data exchange, Excel import |
| XLSX | PhpSpreadsheet | Formatted spreadsheets with styles |

---

## Laravel Classes

| Type | Class | Path |
|------|-------|------|
| Model | `App\Models\ReportTemplate` | `app/Models/ReportTemplate.php` |
| Model | `App\Models\PdfTemplate` | `app/Models/PdfTemplate.php` |
| Controller | `App\Http\Controllers\Api\ReportTemplateController` | `app/Http/Controllers/Api/` |
| Controller | `App\Http\Controllers\Api\PdfTemplateController` | `app/Http/Controllers/Api/` |
| Service | `App\Services\ReportEngineService` | `app/Services/` |
| Service | `App\Services\PdfRenderService` | `app/Services/` |
| Service | `App\Services\PlaceholderResolver` | `app/Services/` |
| Job | `App\Jobs\GenerateReportJob` | `app/Jobs/` |
| Job | `App\Jobs\BulkRenderPdfJob` | `app/Jobs/` |
| Resource | `App\Http\Resources\ReportTemplateResource` | `app/Http/Resources/` |
| Resource | `App\Http\Resources\PdfTemplateResource` | `app/Http/Resources/` |

### Artisan Commands

```bash
# Run all scheduled reports
php artisan pim:run-scheduled-reports

# Render a PDF for a product
php artisan pim:render-pdf --template={id} --product={id} --output=/tmp/sheet.pdf

# Clean up generated report files older than N days
php artisan pim:cleanup-reports --older-than=30
```
