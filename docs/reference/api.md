# anyPIM — API Reference

Base URL: `/api/v1`
Authentication: Laravel Sanctum (Bearer Token)

## Authentication

All endpoints (except Auth/Login, Catalog, Health, and Debug) require a Bearer Token:

```
Authorization: Bearer <token>
```

### Auth

| Method | Path | Description |
|---|---|---|
| POST | `/auth/login` | Login (public) |
| POST | `/auth/logout` | Logout |
| POST | `/auth/refresh` | Refresh token |
| GET | `/auth/me` | Current user |

**Login Request:**
```json
{ "email": "admin@example.com", "password": "password" }
```

**Login Response:**
```json
{ "token": "1|abc123...", "user": { "id": "...", "email": "..." } }
```

### SSO (Enterprise: sso module)

| Method | Path | Description |
|---|---|---|
| GET | `/auth/sso/config` | SSO configuration |
| GET | `/auth/sso/redirect` | Redirect to identity provider |
| GET | `/auth/sso/callback` | Handle SSO callback |

---

## Health (public)

| Method | Path | Description |
|---|---|---|
| GET | `/health` | System status of all services |

**Response (200 = healthy, 503 = degraded):**
```json
{
  "status": "healthy",
  "timestamp": "2025-01-15T10:30:00+00:00",
  "checks": {
    "app": { "status": "ok", "environment": "production" },
    "database": { "status": "ok", "response_ms": 1.2 },
    "cache": { "status": "ok", "driver": "redis", "response_ms": 0.8 },
    "storage": { "status": "ok" },
    "queue": { "status": "ok", "horizon": "running" },
    "disk": { "status": "ok", "free_gb": 120.5, "used_percent": 25 }
  }
}
```

---

## Products

| Method | Path | Description |
|---|---|---|
| GET | `/products` | Product list (paginated) |
| POST | `/products` | Create product |
| GET | `/products/{id}` | Show product |
| PUT | `/products/{id}` | Update product |
| DELETE | `/products/{id}` | Delete product |
| POST | `/products/{id}/duplicate` | Duplicate product |
| GET | `/products/{id}/preview` | Product preview (all data) |
| GET | `/products/{id}/completeness` | Completeness analysis |
| GET | `/products/{id}/preview/export.xlsx` | Excel export |
| GET | `/products/{id}/preview/export.pdf` | PDF export |

### Product Search

| Method | Path | Description |
|---|---|---|
| POST | `/products/search` | Search products (full-text + filters) |
| GET | `/products/compare` | Compare products side by side |
| POST | `/products/export/excel` | Bulk Excel export |

### Product Attribute Values

| Method | Path | Description |
|---|---|---|
| GET | `/products/{id}/attribute-values` | Read attribute values |
| GET | `/products/{id}/resolved-attributes` | Attributes with hierarchy inheritance |
| PUT | `/products/{id}/attribute-values` | Save attribute values (bulk) |

`resolved-attributes` accepts optional query parameter `?hierarchy_node_id=UUID` for live preview.

**Save attribute values:**
```json
{
  "values": [
    { "attribute_id": "uuid", "value": "Value", "language": "en" },
    { "attribute_id": "uuid", "value": 18.0 }
  ]
}
```

### Output Hierarchy Assignments

| Method | Path | Description |
|---|---|---|
| GET | `/products/{id}/output-hierarchy-assignments` | Read assignments |
| POST | `/products/{id}/output-hierarchy-assignments` | Assign to output hierarchy node |
| DELETE | `/output-hierarchy-assignments/{id}` | Remove assignment |
| GET | `/products/{id}/output-hierarchy-attribute-values` | Read output hierarchy attribute values |
| PUT | `/products/{id}/output-hierarchy-attribute-values` | Save output hierarchy attribute values |

### Variants

| Method | Path | Description |
|---|---|---|
| GET | `/products/{id}/variants` | List variants |
| POST | `/products/{id}/variants` | Create variant |
| POST | `/products/{id}/variants/generate` | Generate variants from rules |
| GET | `/products/{id}/variant-rules` | Read inheritance rules |
| PUT | `/products/{id}/variant-rules` | Set inheritance rules |

### Versions

| Method | Path | Description |
|---|---|---|
| GET | `/products/{id}/versions` | List versions |
| POST | `/products/{id}/versions` | Create version |
| GET | `/products/{id}/versions/{v}` | Show version |
| POST | `/products/{id}/versions/{v}/activate` | Activate version |
| POST | `/products/{id}/versions/{v}/schedule` | Schedule version |
| POST | `/products/{id}/versions/{v}/cancel-schedule` | Cancel schedule |
| POST | `/products/{id}/versions/{v}/revert` | Revert to version |
| GET | `/products/{id}/versions/compare` | Compare versions |

### Translation Export/Import

| Method | Path | Description |
|---|---|---|
| POST | `/products/{id}/translations/xliff/export` | Export translations as XLIFF |
| POST | `/products/{id}/translations/xliff/import` | Import translations from XLIFF |

---

## Quick Search & Semantic Search

| Method | Path | Description |
|---|---|---|
| GET | `/quick-search` | Google-like search across all entities |
| POST | `/semantic-search` | Hybrid search (Meilisearch: keyword + vector + hard filters) |
| GET | `/semantic-search/health` | Semantic search index health |

---

## Bulk Operations

### Bulk Editor

| Method | Path | Description |
|---|---|---|
| POST | `/products/bulk-edit` | Get products for bulk editing |
| PUT | `/products/bulk-edit` | Save bulk-edited attribute values |

### Bulk Update (Mass Data Maintenance)

| Method | Path | Description |
|---|---|---|
| POST | `/products/bulk-update/preview` | Preview bulk update changes |
| POST | `/products/bulk-update/execute` | Execute bulk update |

Supported operations: attribute values (replace/append/remove), relations, output hierarchy assignments, master hierarchy, manufacturer, media, status changes.

---

## Hierarchies

| Method | Path | Description |
|---|---|---|
| GET | `/hierarchies` | All hierarchies |
| POST | `/hierarchies` | Create hierarchy |
| GET | `/hierarchies/{id}` | Show hierarchy |
| PUT | `/hierarchies/{id}` | Update hierarchy |
| DELETE | `/hierarchies/{id}` | Delete hierarchy |
| GET | `/hierarchies/{id}/tree` | Tree structure |

### Hierarchy Nodes

| Method | Path | Description |
|---|---|---|
| GET | `/hierarchies/{id}/nodes` | List nodes |
| POST | `/hierarchies/{id}/nodes` | Create node |
| GET | `/hierarchy-nodes/{id}` | Show node |
| PUT | `/hierarchy-nodes/{id}` | Update node |
| DELETE | `/hierarchy-nodes/{id}` | Delete node |
| PUT | `/hierarchy-nodes/{id}/move` | Move node |
| POST | `/hierarchy-nodes/{id}/duplicate` | Duplicate node |

### Hierarchy Attribute Assignments

| Method | Path | Description |
|---|---|---|
| GET | `/hierarchies/{hierarchy}/attributes` | Read hierarchy-level attribute assignments |
| POST | `/hierarchies/{hierarchy}/attributes` | Create assignment |
| PATCH | `/hierarchy-attribute-assignments/{id}` | Update assignment |
| DELETE | `/hierarchy-attribute-assignments/{id}` | Remove assignment |

### Node Attribute Assignments

| Method | Path | Description |
|---|---|---|
| GET | `/hierarchy-nodes/{id}/attributes` | Assigned attributes (+ `?inherited=true`) |
| POST | `/hierarchy-nodes/{id}/attributes` | Assign attribute |
| PUT | `/node-attribute-assignments/{id}` | Update assignment |
| DELETE | `/node-attribute-assignments/{id}` | Remove assignment |
| PUT | `/node-attribute-assignments/bulk-sort` | Update sort order |

### Node Attribute Values

| Method | Path | Description |
|---|---|---|
| GET | `/hierarchy-nodes/{id}/attribute-values` | Read values |
| PUT | `/hierarchy-nodes/{id}/attribute-values` | Save values (bulk) |

---

## Attributes

| Method | Path | Description |
|---|---|---|
| GET | `/attributes` | All attributes |
| POST | `/attributes` | Create attribute |
| GET | `/attributes/{id}` | Show attribute |
| PUT | `/attributes/{id}` | Update attribute |
| DELETE | `/attributes/{id}` | Delete attribute |
| GET | `/attributes/{id}/dependencies` | Show dependencies |
| POST | `/attributes/bulk-update` | Bulk update attributes |
| POST | `/attributes/{id}/copy` | Copy attribute |

**Data types:** `String`, `Number`, `Float`, `Date`, `Flag`, `Selection`, `Dictionary`, `Composite`, `RichText`, `Hyperlink`, `ImageLink`, `PdfLink`, `VideoLink`

### Attribute Types (Groups)

| Method | Path | Description |
|---|---|---|
| GET | `/attribute-types` | All types |
| POST | `/attribute-types` | Create type |
| GET/PUT/DELETE | `/attribute-types/{id}` | CRUD |
| GET | `/attribute-types/{id}/dependencies` | Show dependencies |

### Attribute Views

| Method | Path | Description |
|---|---|---|
| GET | `/attribute-views` | All views |
| POST | `/attribute-views` | Create view |
| GET/PUT/DELETE | `/attribute-views/{id}` | CRUD |
| GET | `/attribute-views/{id}/dependencies` | Show dependencies |
| POST | `/attribute-views/{id}/attributes` | Assign attribute |
| DELETE | `/attribute-views/{id}/attributes/{attr_id}` | Remove attribute |

---

## Units

| Method | Path | Description |
|---|---|---|
| GET | `/unit-groups` | Unit groups |
| POST | `/unit-groups` | Create group |
| GET/PUT/DELETE | `/unit-groups/{id}` | CRUD |
| GET | `/unit-groups/{id}/dependencies` | Show dependencies |
| GET | `/unit-groups/{id}/units` | Units in group |
| POST | `/unit-groups/{id}/units` | Create unit |
| GET/PUT/DELETE | `/units/{id}` | CRUD |

---

## Value Lists

| Method | Path | Description |
|---|---|---|
| GET | `/value-lists` | All value lists |
| POST | `/value-lists` | Create list |
| GET/PUT/DELETE | `/value-lists/{id}` | CRUD |
| GET | `/value-lists/{id}/dependencies` | Show dependencies |
| GET | `/value-lists/{id}/entries` | Entries |
| POST | `/value-lists/{id}/entries` | Create entry |
| GET/PUT/DELETE | `/value-list-entries/{id}` | CRUD |

---

## Dictionary

| Method | Path | Description |
|---|---|---|
| GET | `/dictionary-entries` | All dictionary entries |
| POST | `/dictionary-entries` | Create entry |
| GET/PUT/DELETE | `/dictionary-entries/{id}` | CRUD |
| GET | `/dictionary-entries/{id}/dependencies` | Show dependencies |

---

## Manufacturers

| Method | Path | Description |
|---|---|---|
| GET | `/manufacturers` | All manufacturers |
| POST | `/manufacturers` | Create manufacturer |
| GET/PUT/DELETE | `/manufacturers/{id}` | CRUD |
| GET | `/manufacturers/{id}/dependencies` | Show dependencies |

---

## Media

| Method | Path | Description |
|---|---|---|
| GET | `/media` | Media list |
| POST | `/media` | Upload media (multipart/form-data) |
| GET | `/media/{id}` | Show media |
| PUT | `/media/{id}` | Update media |
| DELETE | `/media/{id}` | Delete media |
| GET | `/media/file/{filename}` | Serve file (public) |
| GET | `/media/thumb/{id}` | Thumbnail (public) |
| GET | `/media/diagnostics` | Storage diagnostics |
| POST | `/media/bulk-move` | Move media in bulk |
| POST | `/media/import-url` | Import single media file from URL |
| POST | `/media/import-video-url` | Import single video from URL |
| POST | `/media/bulk-import-urls` | Import multiple media files from URLs |
| POST | `/media/auto-match` | Auto-match media to products |

### Media Attribute Values

| Method | Path | Description |
|---|---|---|
| GET | `/media/{id}/attribute-values` | Attribute values |
| PUT | `/media/{id}/attribute-values` | Save attribute values |

### Product Media

| Method | Path | Description |
|---|---|---|
| GET | `/products/{id}/media` | Assigned media |
| POST | `/products/{id}/media` | Assign media |
| DELETE | `/product-media/{id}` | Remove assignment |

### Media Usage Types

| Method | Path | Description |
|---|---|---|
| GET | `/media-usage-types` | All usage types |
| POST/GET/PUT/DELETE | `/media-usage-types/{id}` | CRUD |

---

## Prices

| Method | Path | Description |
|---|---|---|
| GET | `/price-types` | Price types |
| POST/GET/PUT/DELETE | `/price-types/{id}` | CRUD |
| GET | `/price-regions` | Price regions |
| POST/GET/PUT/DELETE | `/price-regions/{id}` | CRUD |
| GET | `/products/{id}/prices` | Product prices |
| POST | `/products/{id}/prices` | Create price |
| PUT | `/product-prices/{id}` | Update price |
| DELETE | `/product-prices/{id}` | Delete price |

---

## Relations

| Method | Path | Description |
|---|---|---|
| GET | `/relation-types` | Relation types |
| POST/GET/PUT/DELETE | `/relation-types/{id}` | CRUD |
| GET | `/relation-types/{id}/default-attributes` | Default attributes for type |
| GET | `/products/{id}/relations` | Product relations |
| POST | `/products/{id}/relations` | Create relation |
| DELETE | `/product-relations/{id}` | Delete relation |

### Relation Attribute Values

| Method | Path | Description |
|---|---|---|
| GET | `/product-relations/{id}/attribute-values` | Read values |
| PUT | `/product-relations/{id}/attribute-values` | Save values |

---

## Product Types

| Method | Path | Description |
|---|---|---|
| GET | `/product-types` | All product types |
| POST/GET/PUT/DELETE | `/product-types/{id}` | CRUD |
| GET | `/product-types/{id}/schema` | Attribute schema |

---

## PQL (Product Query Language)

| Method | Path | Description |
|---|---|---|
| POST | `/pql/query` | Execute PQL query |
| POST | `/pql/query/count` | Count results |
| POST | `/pql/query/validate` | Validate syntax |
| POST | `/pql/query/explain` | Show query plan |

**Example:**
```json
{ "query": "sku = 'PD-18V-001' AND status = 'active'" }
```

---

## Import

| Method | Path | Description |
|---|---|---|
| GET | `/imports/templates/{type}` | Download import template |
| GET | `/imports/export-format` | Format description |
| POST | `/imports` | Start import (multipart) |
| GET | `/imports/{id}` | Import status |
| GET | `/imports/{id}/preview` | Preview |
| POST | `/imports/{id}/execute` | Execute import |
| GET | `/imports/{id}/result` | Result |
| GET | `/imports/{id}/logs` | Import logs |
| DELETE | `/imports/{id}` | Delete import |

### Import Profiles

| Method | Path | Description |
|---|---|---|
| GET | `/import-profiles` | All import profiles |
| POST/GET/PUT/DELETE | `/import-profiles/{id}` | CRUD |

### Search Profiles

| Method | Path | Description |
|---|---|---|
| GET | `/search-profiles` | All search profiles |
| POST/GET/PUT/DELETE | `/search-profiles/{id}` | CRUD |

### Export Profiles

| Method | Path | Description |
|---|---|---|
| GET | `/export-profiles` | All export profiles |
| POST/GET/PUT/DELETE | `/export-profiles/{id}` | CRUD |

---

## Export

| Method | Path | Description |
|---|---|---|
| GET | `/export/products` | Exportable products |
| GET | `/export/products/{id}` | Single product export |
| POST | `/export/products/bulk` | Bulk export |
| GET | `/export/products/{id}/publixx` | Publixx format |
| POST | `/export/query` | Export via PQL query |

---

## JSON Export/Import

| Method | Path | Description |
|---|---|---|
| POST | `/json-export` | Export all data as JSON (18 sections) |
| POST | `/json-import` | Import data from JSON |

---

## BMEcat (Enterprise: bmecat module)

| Method | Path | Description |
|---|---|---|
| POST | `/bmecat-import` | Import BMEcat file |
| POST | `/bmecat-import/validate` | Validate BMEcat file |
| POST | `/bmecat-import/cancel` | Cancel running import |
| POST | `/bmecat-import/upload-init` | Init chunked upload |
| POST | `/bmecat-import/upload-chunk` | Upload chunk |
| POST | `/bmecat-import/upload-complete` | Complete chunked upload |
| POST | `/bmecat-export` | Export as BMEcat |

---

## Export Jobs (Enterprise: advanced_export module)

| Method | Path | Description |
|---|---|---|
| GET | `/export-jobs` | All export jobs |
| POST | `/export-jobs` | Create job |
| GET/PUT/DELETE | `/export-jobs/{id}` | CRUD |
| POST | `/export-jobs/{id}/execute` | Execute job |
| GET | `/export-jobs/{id}/logs` | Job logs |
| GET | `/export-files` | List export files |
| GET | `/export-files/{path}` | Download export file |

---

## Publixx Live API (Enterprise: publixx module)

| Method | Path | Description |
|---|---|---|
| GET | `/publixx/datasets/{mapping}` | Datasets (by mapping) |
| GET | `/publixx/datasets/{mapping}/{product}` | Single dataset |
| POST | `/publixx/datasets/{mapping}/pql` | PQL query |
| POST | `/publixx/webhook` | Receive webhook |

---

## Reports (Enterprise: reports module)

| Method | Path | Description |
|---|---|---|
| GET | `/report-templates` | All report templates |
| POST | `/report-templates` | Create template |
| GET/PUT/DELETE | `/report-templates/{id}` | CRUD |
| POST | `/report-templates/{id}/execute` | Execute report |
| POST | `/report-templates/{id}/preview` | Preview report |
| GET | `/report-templates/{id}/status` | Job status |
| GET | `/report-templates/{id}/download` | Download result |

---

## PDF Templates (Enterprise: pdf_templates module)

| Method | Path | Description |
|---|---|---|
| GET | `/pdf-templates` | All PDF templates |
| POST | `/pdf-templates` | Create template |
| GET/PUT/DELETE | `/pdf-templates/{id}` | CRUD |
| POST | `/pdf-templates/{id}/preview` | Preview template |
| POST | `/pdf-templates/{id}/execute` | Generate PDF |

---

## Workflow Tasks

| Method | Path | Description |
|---|---|---|
| GET | `/workflow-tasks` | All tasks |
| POST | `/workflow-tasks` | Create task |
| GET/PUT/DELETE | `/workflow-tasks/{id}` | CRUD |

---

## Scheduled Actions & Calendar

| Method | Path | Description |
|---|---|---|
| GET | `/scheduled-actions` | All scheduled actions |
| POST | `/scheduled-actions` | Create action |
| GET/PUT/DELETE | `/scheduled-actions/{id}` | CRUD |
| GET | `/calendar` | Calendar events |

---

## Watchlist

| Method | Path | Description |
|---|---|---|
| GET | `/watchlist` | Watchlist items |
| POST | `/watchlist` | Add product |
| DELETE | `/watchlist/{id}` | Remove product |
| POST | `/watchlist/export/excel` | Export as Excel |
| POST | `/watchlist/export/pdf` | Export as PDF |
| POST | `/watchlist/export/pdf-zip` | Export as PDF zip |
| POST | `/watchlist/export/xliff` | Export as XLIFF |

---

## Dashboard

| Method | Path | Description |
|---|---|---|
| GET | `/dashboard` | Dashboard data (widgets) |

---

## Users & Roles

### Users

| Method | Path | Description |
|---|---|---|
| GET | `/users` | All users |
| POST | `/users` | Create user |
| GET/PUT/DELETE | `/users/{id}` | CRUD |
| GET | `/users/{id}/dependencies` | User dependencies |

### Roles

| Method | Path | Description |
|---|---|---|
| GET | `/roles` | All roles |
| POST | `/roles` | Create role |
| GET/PUT/DELETE | `/roles/{id}` | CRUD |
| PUT | `/roles/{id}/permissions` | Set permissions |
| GET | `/roles/{id}/dependencies` | Role dependencies |

### Permissions

| Method | Path | Description |
|---|---|---|
| GET | `/permissions` | All available permissions |

---

## Access Links

| Method | Path | Description |
|---|---|---|
| GET | `/access-links` | All access links |
| POST | `/access-links` | Create access link |
| DELETE | `/access-links/{id}` | Delete access link |
| GET | `/access-links/report` | Access link report |
| POST | `/access-links/redeem/{token}` | Redeem access link (public) |

---

## License

| Method | Path | Description |
|---|---|---|
| GET | `/license` | Current license info |
| PUT | `/license` | Update license |

---

## Audit Logs

| Method | Path | Description |
|---|---|---|
| GET | `/audit-logs` | System audit logs |
| POST | `/audit-logs/export` | Export audit logs |
| DELETE | `/audit-logs` | Delete audit logs |
| GET | `/user-audit-logs` | User audit trail |
| POST | `/user-audit-logs/export` | Export user audit logs |
| DELETE | `/user-audit-logs` | Delete user audit logs |

---

## TMS (Translation Management)

| Method | Path | Description |
|---|---|---|
| GET | `/tms/units` | Translation units |
| GET | `/tms/units/{id}` | Single translation unit |
| PUT | `/tms/units/{id}/translations/{lang}` | Update a translation |
| GET | `/tms/stats` | Translation statistics |
| GET | `/tms/missing` | Missing translations |
| POST | `/tms/retranslate` | Retranslate entries |
| POST | `/tms/ingest` | Ingest new terms |
| POST | `/tms/sync` | Sync with external TMS |
| DELETE | `/tms/translations` | Delete translations |
| DELETE | `/tms/units` | Purge units |

---

## Catalog (public)

Public endpoints — no authentication required (or access link token):

| Method | Path | Description |
|---|---|---|
| GET | `/catalog/settings` | Catalog settings & theme |
| GET | `/catalog/products` | Product list |
| GET | `/catalog/products/{id}` | Product detail |
| GET | `/catalog/products/{id}/json` | Product as JSON |
| GET | `/catalog/products/export.json` | All products as JSON |
| GET | `/catalog/products/{id}/pdf` | Product PDF |
| GET | `/catalog/categories` | Categories |
| GET | `/catalog/facets` | Faceted filters |
| GET | `/catalog/media/{filename}` | Serve media file |
| POST | `/catalog/products/compare` | Compare products |
| POST | `/catalog/wishlist/pdf` | Wishlist as PDF |
| POST | `/catalog/wishlist/excel` | Wishlist as Excel |

---

## Asset Catalog (public)

| Method | Path | Description |
|---|---|---|
| GET | `/asset-catalog/assets` | Browse assets |
| GET | `/asset-catalog/assets/{id}` | Single asset |
| GET | `/asset-catalog/folders` | Folder structure |
| POST | `/asset-catalog/download` | Create download package |

---

## Admin

| Method | Path | Description |
|---|---|---|
| GET | `/admin/reset-categories` | Reset category cache |
| POST | `/admin/reset-data` | Reset all data |
| POST | `/admin/load-demo-data` | Load demo data |
| POST | `/admin/search-reindex` | Rebuild search index |
| PUT | `/settings/catalog-theme` | Update catalog theme |
| GET | `/admin/deploy/status` | Deployment status |
| POST | `/admin/deploy` | Start deployment |
| POST | `/admin/deploy/rollback` | Rollback deployment |

### API Tester

| Method | Path | Description |
|---|---|---|
| GET | `/admin/api-routes` | List all available routes |

### Database Viewer

| Method | Path | Description |
|---|---|---|
| GET | `/admin/db/tables` | List all tables |
| GET | `/admin/db/tables/{table}/columns` | Table structure |
| GET | `/admin/db/tables/{table}/rows` | Browse table rows |

---

## Debug (test server only)

| Method | Path | Description |
|---|---|---|
| GET | `/debug/logs` | Show Laravel log (`?channel=laravel&lines=500`) |
| GET | `/debug/logs/clear` | Clear log |
| DELETE | `/debug/logs` | Delete log file |

---

## Common Query Parameters

Most list endpoints support:

| Parameter | Description | Example |
|---|---|---|
| `page` | Page number | `?page=2` |
| `perPage` | Items per page | `?perPage=50` |
| `sort` | Sort order | `?sort=-created_at` |
| `filter[field]` | Filter | `?filter[status]=active` |
| `include` | Include relations | `?include=attributes,media` |
| `lang` | Language filter | `?lang=de,en` |
