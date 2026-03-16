---
title: Typesense
---

# Typesense

anyPIM uses [Typesense](https://typesense.org/) as the search engine for full-text search across PDF documents. When a PDF is uploaded, anyPIM extracts the text from each page and indexes it in Typesense. Users can then search across all PDFs with page-level precision.

::: info Typesense is optional
PDF processing (page previews, text extraction) works without Typesense. Only the cross-document full-text search for PDFs requires Typesense.
:::

## Prerequisites

| Component | Description |
|---|---|
| **Typesense Server** | Version 27.x or newer |
| **poppler-utils** | System package providing `pdftotext`, `pdftoppm`, and `pdfinfo` |
| **typesense/typesense-php** | PHP client (installed via Composer) |

## Installation

### 1. Typesense Server

```bash
# Debian/Ubuntu (.deb package)
curl -O https://dl.typesense.org/releases/27.1/typesense-server-27.1-amd64.deb
sudo dpkg -i typesense-server-27.1-amd64.deb
```

After installation, Typesense runs automatically as a systemd service on port **8108**. The generated API key is stored in:

```bash
cat /etc/typesense/typesense-server.ini
```

### 2. poppler-utils

```bash
sudo apt install poppler-utils
```

Provides the following CLI tools:

| Tool | Purpose |
|---|---|
| `pdfinfo` | Determine page count |
| `pdftoppm` | Render pages as PNG |
| `pdftotext` | Extract text per page |

### 3. PHP Client

```bash
composer require typesense/typesense-php
```

## Configuration

The Typesense connection is configured via environment variables in `.env`:

```env
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=your-api-key-here
```

You can find the API key in `/etc/typesense/typesense-server.ini` (field `api-key`).

::: warning Production
In production environments, the Typesense API key should be kept confidential. Never use the default key in publicly accessible configurations.
:::

## Setting Up the Collection

anyPIM creates a Typesense collection called `pdf_pages`. Create it with the following Artisan command:

```bash
php artisan typesense:setup
```

To drop and recreate an existing collection:

```bash
php artisan typesense:setup --force
```

### Collection Schema

| Field | Type | Description |
|---|---|---|
| `pdf_document_id` | string | Reference to the PdfDocument |
| `media_id` | string | Reference to the Media object |
| `page_number` | int32 | Page number |
| `text` | string | Extracted page text |
| `title` | string | Document title |
| `language` | string | Language (optional, faceted) |

## PDF Processing Pipeline

When a PDF is uploaded via the media manager, it automatically goes through the following steps:

1. **Download** — PDF is downloaded and stored temporarily
2. **Page count** — `pdfinfo` determines the total number of pages
3. **Rendering** — `pdftoppm` renders each page as PNG (150 DPI)
4. **Conversion** — PHP GD converts PNG to WebP (quality 85%)
5. **Text extraction** — `pdftotext` extracts the text of each page
6. **Indexing** — All pages are indexed in Typesense

Processing runs asynchronously on the `pdf` queue, managed by Laravel Horizon.

## Service Management

```bash
# Check status
sudo systemctl status typesense-server

# Restart
sudo systemctl restart typesense-server

# View logs
sudo journalctl -u typesense-server -f
```

## Troubleshooting

### Typesense not reachable

```bash
# Check if the service is running
sudo systemctl status typesense-server

# Check port
curl http://localhost:8108/health
```

### "Class Typesense\Client not found"

The PHP client is not installed:

```bash
composer require typesense/typesense-php
```

### PDF pages without text

If the extracted text is empty, the PDF may contain only images (scanned document). Text extraction only works for PDFs with embedded text.

### Collection already exists

Running `typesense:setup` without `--force` will fail if the collection already exists. Use `--force` to recreate it (deletes existing data).
