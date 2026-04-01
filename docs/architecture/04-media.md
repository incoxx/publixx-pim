# anyPIM — Media Management

> **Purpose:** Media upload, storage, assignment and delivery. Use this skill when implementing media upload, product media assignments, CDN delivery, thumbnails, and media library features.

---

## Stack Context

- **Storage:** Local disk or S3-compatible (configurable)
- **Thumbnails:** Server-side generation (GD/Imagick)
- **CDN:** Optional CloudFront / Bunny CDN
- **Backend:** PHP 8.3, Laravel 11

---

## Data Model

### media

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | CHAR(36) PK | No | UUID |
| file_name | VARCHAR(255) | No | Original filename |
| file_path | VARCHAR(500) | No | Storage path |
| mime_type | VARCHAR(100) | No | e.g. `image/jpeg` |
| file_size | BIGINT | No | Size in bytes |
| media_type | ENUM('image','document','video','other') | No | |
| title_de | VARCHAR(255) | Yes | German title |
| title_en | VARCHAR(255) | Yes | English title |
| description_de | TEXT | Yes | |
| description_en | TEXT | Yes | |
| alt_text_de | VARCHAR(255) | Yes | |
| alt_text_en | VARCHAR(255) | Yes | |
| width | INT | Yes | Image width in px |
| height | INT | Yes | Image height in px |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | |

### product_media_assignments

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| product_id | FK → products.id | |
| media_id | FK → media.id | |
| usage_type | ENUM('teaser','gallery','document','technical_drawing') | Purpose of the media |
| sort_order | INT | Display order |
| is_primary | BOOLEAN DEFAULT false | Primary image flag |

### media_usage_types

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | e.g. `teaser`, `gallery` |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) | |
| allowed_mime_types | JSON | e.g. `["image/jpeg","image/png"]` |
| max_file_size_mb | INT | |

---

## API Endpoints

```
GET    /media                              All media (paginated, filterable)
POST   /media                              Upload (multipart/form-data)
GET    /media/{id}                         Single media details
PUT    /media/{id}                         Update metadata
DELETE /media/{id}                         Delete media
GET    /media/file/{filename}              Serve file directly
GET    /media/thumb/{id}                   Serve thumbnail
GET    /media/diagnostics                  Storage diagnostics
POST   /media/bulk-move                    Bulk move media
POST   /media/import-urls                  Import media from URLs
POST   /media/auto-match                   Auto-match media to products by filename
```

### Product Media

```
GET    /products/{id}/media                Assigned media
POST   /products/{id}/media                Assign media to product
DELETE /product-media/{id}                 Remove assignment
```

### Media Attribute Values

```
GET    /media/{id}/attribute-values        Read attribute values
PUT    /media/{id}/attribute-values        Save attribute values
```

---

## Upload Flow

1. Frontend sends `POST /media` with `multipart/form-data`
2. Backend validates file type, size, dimensions
3. File stored to configured disk (local/S3)
4. Thumbnail generated for images (server-side)
5. Media record created with metadata extracted from file
6. Response returns media object with URLs

---

## Media in Exports

When `include_media: true` in export mapping:
- Teaser image → `productImage` field (single URL)
- Gallery images → `gallery` field (array of URLs)
- Documents → `documents` field (array with title + URL)
- URLs point to `/api/v1/media/file/{filename}` or CDN URL

---

## Auto-Matching

`POST /media/auto-match` matches uploaded media to products based on filename patterns:
- `{SKU}-front.jpg` → product with matching SKU, usage_type: gallery
- `{SKU}-teaser.jpg` → product with matching SKU, usage_type: teaser
- `{SKU}-datasheet.pdf` → product with matching SKU, usage_type: document

---

## Laravel Classes

```php
App\Services\Media\MediaService           // Upload, delete, serve
App\Services\Media\ThumbnailService       // Thumbnail generation
App\Services\Media\AutoMatchService       // Filename → product matching
App\Http\Controllers\Api\V1\MediaController
App\Http\Controllers\Api\V1\ProductMediaController
```
