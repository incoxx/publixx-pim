# PDF-Suche mit Typesense

Volltextsuche über PDF-Dokumente im Asset-Katalog. PDFs werden automatisch verarbeitet,
der Text extrahiert und in Typesense indexiert. Die Suche liefert relevanzgewichtete,
nach Dokumenten gruppierte Ergebnisse.

---

## Inhaltsverzeichnis

1. [Architektur-Überblick](#1-architektur-überblick)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation und Einrichtung](#3-installation-und-einrichtung)
4. [Wie PDFs verarbeitet werden](#4-wie-pdfs-verarbeitet-werden)
5. [Volltextsuche](#5-volltextsuche)
6. [API-Endpunkte](#6-api-endpunkte)
7. [Artisan-Commands](#7-artisan-commands)
8. [Frontend-Komponenten](#8-frontend-komponenten)
9. [Relevanz-Gewichtung und Gruppierung](#9-relevanz-gewichtung-und-gruppierung)
10. [Skalierung](#10-skalierung)
11. [Fehlerbehebung](#11-fehlerbehebung)
12. [Dateiübersicht](#12-dateiübersicht)

---

## 1. Architektur-Überblick

```
┌──────────────────────────────────────────────────────────────────┐
│  Media-Upload (PDF)                                              │
│  → MediaObserver erkennt PDF                                     │
└────────────────────┬─────────────────────────────────────────────┘
                     │ dispatch()
                     ▼
┌──────────────────────────────────────────────────────────────────┐
│  ProcessPdfDocument (Queue-Job, queue: "pdf")                    │
│                                                                  │
│  1. PDF herunterladen (Storage oder HTTP)                         │
│  2. pdfinfo → Seitenanzahl                                       │
│  3. pdftoppm → PNG pro Seite (150 DPI)                           │
│  4. GD → PNG zu WebP konvertieren (Qualität 85%)                 │
│  5. pdftotext → Text pro Seite extrahieren                       │
│  6. PdfPage-Records in DB speichern (Bild + Text)                │
│  7. TypesenseService::upsertPages() → Volltextindex              │
└────────────────────┬─────────────────────────────────────────────┘
                     │
          ┌──────────┼──────────┐
          ▼                     ▼
┌──────────────────┐  ┌──────────────────────────────────┐
│  Storage (public) │  │  Typesense (Collection: pdf_pages) │
│  pdf-pages/{id}/  │  │                                    │
│  page-1.webp      │  │  Felder:                           │
│  page-2.webp      │  │  ├─ title (Gewicht 3x)             │
│  ...              │  │  ├─ file_name (Gewicht 2x)          │
│                   │  │  ├─ text (Gewicht 1x)               │
│                   │  │  ├─ description                     │
│                   │  │  ├─ folder_name                     │
│                   │  │  ├─ page_number                     │
│                   │  │  ├─ page_count                      │
│                   │  │  └─ language (Facette)               │
└──────────────────┘  └──────────────────────────────────┘
          │                     │
          └──────────┬──────────┘
                     ▼
┌──────────────────────────────────────────────────────────────────┐
│  Frontend: /assetpreview                                         │
│                                                                  │
│  Metadaten-Suche          │  Inhaltssuche (Typesense)            │
│  (MySQL LIKE + Phonetik)  │  → Gruppiert nach Dokument           │
│  → Asset-Grid             │  → Aufklappbare Snippets pro Seite   │
│                           │  → PDF-Viewer mit Seitennavigation   │
└──────────────────────────────────────────────────────────────────┘
```

---

## 2. Voraussetzungen

### System-Pakete

```bash
# poppler-utils — für pdfinfo, pdftoppm, pdftotext
sudo apt install poppler-utils

# Prüfen ob installiert:
pdfinfo --version
pdftoppm --version
pdftotext --version
```

### Typesense Server

Typesense ist **optional**. Ohne Typesense funktionieren PDF-Vorschau und Seitenbilder weiterhin —
nur die dokumentenübergreifende Volltextsuche ist dann nicht verfügbar.

```bash
# Installation (Ubuntu/Debian)
curl -O https://dl.typesense.org/releases/27.1/typesense-server-27.1-amd64.deb
sudo dpkg -i typesense-server-27.1-amd64.deb

# Service starten
sudo systemctl enable typesense-server
sudo systemctl start typesense-server

# Standard: Port 8108, API-Key in /etc/typesense/typesense-server.ini
```

### PHP-Paket

```bash
composer require typesense/typesense-php
```

---

## 3. Installation und Einrichtung

### Umgebungsvariablen (.env)

```env
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=dein-api-key-hier
```

Der API-Key steht in der Typesense-Konfiguration unter `/etc/typesense/typesense-server.ini`
(Feld `api-key`).

### Collection anlegen

```bash
# Collection erstellen (einmalig)
php artisan typesense:setup

# Collection löschen und neu erstellen (bei Schema-Änderungen)
php artisan typesense:setup --force
```

### Bestehende PDFs indexieren

```bash
# Alle verarbeiteten PDFs in Typesense indexieren
php artisan typesense:reindex

# Collection neu erstellen UND alle PDFs indexieren
php artisan typesense:reindex --fresh
```

### Queue-Worker starten

PDF-Verarbeitung läuft asynchron auf der Queue `pdf`:

```bash
# Einzelner Worker
php artisan queue:work --queue=pdf

# Mehrere Worker für schnellere Verarbeitung (empfohlen bei vielen PDFs)
php artisan queue:work --queue=pdf &
php artisan queue:work --queue=pdf &
php artisan queue:work --queue=pdf &

# Oder via Laravel Horizon (falls konfiguriert)
php artisan horizon
```

---

## 4. Wie PDFs verarbeitet werden

### Automatische Erkennung

Der `MediaObserver` reagiert auf jedes neue oder geänderte Media-Asset. Ein PDF wird erkannt wenn:

- `mime_type` = `application/pdf`, oder
- `file_path` endet auf `.pdf`, oder
- `file_name` endet auf `.pdf`

### Verarbeitungs-Pipeline (ProcessPdfDocument Job)

| Schritt | Tool | Output |
|---------|------|--------|
| PDF herunterladen | Storage / HTTP | `tmp/pdf-{id}/source.pdf` |
| Seitenanzahl | `pdfinfo` | `page_count` in DB |
| Seiten rendern | `pdftoppm` (150 DPI) | PNG pro Seite |
| Format-Konvertierung | PHP GD | WebP (85% Qualität) → `storage/pdf-pages/{id}/page-N.webp` |
| Text extrahieren | `pdftotext -layout` | `extracted_text` in DB |
| Indexieren | TypesenseService | `pdf_pages` Collection |

### Status-Lifecycle

```
pending → processing → ready
                    └→ error (mit error_message)
```

- **pending** — Job steht in der Queue
- **processing** — Job wird gerade ausgeführt
- **ready** — Alle Seiten verarbeitet, Text indexiert
- **error** — Verarbeitung fehlgeschlagen (max 3 Retries mit Backoff 10s/30s/120s)

### Datenmodell

```
Media (1) ──── (1) PdfDocument ──── (n) PdfPage
                   ├─ status              ├─ page_number
                   ├─ page_count          ├─ image_path (WebP)
                   ├─ original_url        └─ extracted_text
                   └─ error_message
```

---

## 5. Volltextsuche

### Wie die Suche funktioniert

Die Suche durchsucht den extrahierten Text aller PDF-Seiten. Typesense nutzt den BM25-Algorithmus
für die Relevanzberechnung — derselbe Algorithmus den auch Elasticsearch und Lucene verwenden.

### Relevanz-Gewichtung

Nicht alle Treffer sind gleich wichtig. Felder werden unterschiedlich gewichtet:

| Feld | Gewicht | Bedeutung |
|------|---------|-----------|
| `title` | **3x** | Treffer im Dokumenttitel sind am relevantesten |
| `file_name` | **2x** | Treffer im Dateinamen sind wichtig |
| `text` | **1x** | Treffer im Seitentext (Standard) |

Ein Dokument mit dem Titel "Hydraulikpumpe Datenblatt" erscheint bei der Suche nach "Hydraulikpumpe"
vor einem Dokument, das den Begriff nur einmal im Fließtext auf Seite 47 enthält.

### Dokument-Gruppierung

Treffer werden nach Dokument gruppiert statt einzelne Seiten aufzulisten:

```
Suche: "Hydraulikpumpe"

┌─ Hydraulikpumpe HP-3000 Datenblatt.pdf          5 Treffer, 12 Seiten
│  ├─ Seite 1: ...die <mark>Hydraulikpumpe</mark> HP-3000 ist für...
│  ├─ Seite 3: ...Leistungsdaten der <mark>Hydraulikpumpe</mark>...
│  └─ Seite 7: ...Wartung der <mark>Hydraulikpumpe</mark>...
│  + 2 weitere Treffer in diesem Dokument
│
├─ Katalog-2025.pdf                                2 Treffer, 48 Seiten
│  ├─ Seite 23: ...<mark>Hydraulikpumpe</mark>n im Überblick...
│  └─ Seite 24: ...Bestellung <mark>Hydraulikpumpe</mark>...
│
└─ Montageanleitung-Zubehör.pdf                    1 Treffer, 8 Seiten
   └─ Seite 5: ...kompatibel mit <mark>Hydraulikpumpe</mark> HP-Serie...
```

Pro Dokument werden maximal 3 Seiten-Snippets angezeigt. Bei mehr Treffern erscheint ein Hinweis
"+ N weitere Treffer in diesem Dokument".

---

## 6. API-Endpunkte

Alle Endpunkte unter `/api/v1/pdf/`. Middleware: `throttle.pim`, `catalog.access`.

### Volltextsuche

```
GET /api/v1/pdf/search?q=suchbegriff&lang=de&per_page=20&page=1
```

**Parameter:**

| Parameter | Typ | Pflicht | Beschreibung |
|-----------|-----|---------|-------------|
| `q` | string | Ja | Suchbegriff (1-200 Zeichen) |
| `lang` | string | Nein | Sprachfilter (z.B. `de`, `en`) |
| `per_page` | integer | Nein | Ergebnisse pro Seite (1-100, Standard: 20) |
| `page` | integer | Nein | Seitennummer (Standard: 1) |

**Response:**

```json
{
  "data": {
    "groups": [
      {
        "pdf_document_id": "uuid-1",
        "media_id": "uuid-media",
        "title": "Hydraulikpumpe Datenblatt",
        "file_name": "hydraulikpumpe-hp3000.pdf",
        "page_count": 12,
        "total_hits": 5,
        "hits": [
          {
            "page_number": 1,
            "snippet": "Die <mark>Hydraulikpumpe</mark> HP-3000 ist...",
            "score": 18293445
          },
          {
            "page_number": 3,
            "snippet": "Leistungsdaten der <mark>Hydraulikpumpe</mark>...",
            "score": 14528930
          }
        ]
      }
    ],
    "found": 42,
    "found_docs": 8,
    "page": 1,
    "per_page": 20
  }
}
```

### PDF-Metadaten

```
GET /api/v1/pdf/{pdfDocument}
```

Liefert: `id`, `media_id`, `status`, `page_count`, `original_url`, `title`, `error_message`, Timestamps.

### Seitenbilder

```
GET /api/v1/pdf/{pdfDocument}/pages         → Alle Seiten als Array mit image_url
GET /api/v1/pdf/{pdfDocument}/page/{n}      → Redirect auf WebP-Bild der Seite
```

### Status (Polling)

```
GET /api/v1/pdf/{pdfDocument}/status
```

Liefert: `id`, `status`, `page_count`, `error_message`. Für Polling während der Verarbeitung.

### PDF über Media-ID finden

```
GET /api/v1/pdf/by-media/{mediaId}
```

### Erneut verarbeiten (Auth erforderlich)

```
POST /api/v1/pdf/{pdfDocument}/reprocess
```

Setzt Status auf `pending` und dispatcht den Job erneut.

### Batch-Verarbeitung (nur Admin)

```
POST /admin/pdf/batch-process?mode=missing|failed|all
```

| Mode | Beschreibung |
|------|-------------|
| `missing` | Nur PDFs ohne PdfDocument-Eintrag |
| `failed` | PDFs mit Status `error` oder `pending` |
| `all` | Alle PDFs neu verarbeiten |

---

## 7. Artisan-Commands

### typesense:setup

```bash
# Collection anlegen (idempotent)
php artisan typesense:setup

# Collection löschen und neu erstellen
php artisan typesense:setup --force
```

### typesense:reindex

```bash
# Alle PDFs mit Status "ready" neu indexieren
php artisan typesense:reindex

# Collection vorher löschen und neu erstellen, dann indexieren
php artisan typesense:reindex --fresh
```

Zeigt einen Fortschrittsbalken und verarbeitet PDFs in 50er-Batches. Am Ende wird die Anzahl
indexierter Dokumente und eventuelle Fehler angezeigt.

---

## 8. Frontend-Komponenten

### Zugang

Die PDF-Suche ist Teil des Asset-Katalogs unter `/assetpreview`. Der Zugang ist öffentlich
(konfigurierbar über `catalog_access_mode`).

### Suchmodi

In der Toolbar kann zwischen zwei Modi gewechselt werden:

- **Metadaten** — Sucht in Dateiname, Titel, Beschreibung, Attributen (MySQL, mit Phonetik)
- **Inhalte** — Volltextsuche im PDF-Text via Typesense

### Komponenten

| Komponente | Datei | Beschreibung |
|------------|-------|-------------|
| PdfSearch | `pim-frontend/src/components/assetCatalog/PdfSearch.vue` | Suchfeld + gruppierte Ergebnisliste |
| PdfViewer | `pim-frontend/src/components/assetCatalog/PdfViewer.vue` | Seitenbilder mit Lazy Loading + Navigation |
| PdfStatusBadge | `pim-frontend/src/components/assetCatalog/PdfStatusBadge.vue` | Status-Anzeige mit Auto-Polling |

### Ablauf im Frontend

1. Nutzer wechselt auf "Inhalte"-Modus
2. Suchbegriff eingeben (Debounce 300ms)
3. Ergebnisse erscheinen als aufklappbare Dokument-Karten
4. Klick auf ein Snippet öffnet den PDF-Viewer auf der entsprechenden Seite
5. Im Viewer: Seiten durchblättern, PDF herunterladen

---

## 9. Relevanz-Gewichtung und Gruppierung

### Typesense Collection-Schema

```json
{
  "name": "pdf_pages",
  "fields": [
    { "name": "pdf_document_id", "type": "string", "facet": true },
    { "name": "media_id",        "type": "string" },
    { "name": "page_number",     "type": "int32" },
    { "name": "text",            "type": "string" },
    { "name": "title",           "type": "string" },
    { "name": "file_name",       "type": "string", "optional": true },
    { "name": "description",     "type": "string", "optional": true },
    { "name": "folder_name",     "type": "string", "optional": true },
    { "name": "page_count",      "type": "int32",  "optional": true },
    { "name": "language",        "type": "string", "facet": true, "optional": true }
  ]
}
```

### Such-Parameter

```
query_by:          title, file_name, text
query_by_weights:  3, 2, 1
group_by:          pdf_document_id
group_limit:       3
```

- **query_by_weights** — Bestimmt die Gewichtung. Ein Titel-Treffer zählt 3x so viel wie ein Text-Treffer.
- **group_by** — Fasst Treffer desselben Dokuments zusammen.
- **group_limit** — Maximal 3 Seiten-Snippets pro Dokument in der Antwort.

### Indexierte Metadaten pro Seite

Jede Seite wird als eigenes Typesense-Dokument gespeichert. Zusätzlich zum Seitentext werden
Metadaten des übergeordneten PDF-Dokuments mitgespeichert:

| Feld | Quelle | Zweck |
|------|--------|-------|
| `title` | `Media.title_de / title_en / file_name` | Gewichteter Suchtreffer, Anzeige |
| `file_name` | `Media.file_name` | Gewichteter Suchtreffer |
| `description` | `Media.description_de / description_en` | Kontext |
| `folder_name` | `AssetFolder.name_de` | Kontext |
| `page_count` | `PdfDocument.page_count` | Anzeige im Frontend |

---

## 10. Skalierung

### Kapazitäten

| Metrik | 100 PDFs | 1.000 PDFs | 20.000 PDFs |
|--------|----------|------------|-------------|
| Seiten im Index (Ø 10 S./PDF) | 1.000 | 10.000 | 200.000 |
| Typesense RAM | ~50 MB | ~100 MB | ~400 MB |
| Such-Latenz | <10 ms | <20 ms | <50 ms |
| WebP-Speicher (Ø 100 KB/S.) | ~100 MB | ~1 GB | ~20 GB |
| Initiales Indexieren | ~1 Min | ~10 Min | ~3 Std |

### Empfehlungen bei >5.000 PDFs

- **Typesense RAM**: Mindestens 2 GB für den Typesense-Prozess reservieren
- **Queue-Worker**: 3-4 parallele Worker auf der `pdf`-Queue
- **Disk**: SSD empfohlen für WebP-Dateien
- **Reindex**: `typesense:reindex` läuft in 50er-Batches, kann bei Unterbrechung erneut gestartet werden

### Limits

| Aspekt | Aktuelles Limit | Grund |
|--------|----------------|-------|
| Seiten pro PDF | ~150 | Job-Timeout 300s |
| Suchbegriff-Länge | 200 Zeichen | Validierung im Controller |
| Ergebnisse pro Seite | 100 | Typesense-Konfiguration |

---

## 11. Fehlerbehebung

### Typesense nicht erreichbar

```
TypesenseService: Search failed - Connection refused
```

**Lösung:** Typesense-Service prüfen:

```bash
sudo systemctl status typesense-server
curl http://localhost:8108/health
```

Die PDF-Verarbeitung (Bilder + Text) funktioniert auch ohne Typesense weiter. Nur die Volltextsuche
ist betroffen. Sobald Typesense wieder läuft, `php artisan typesense:reindex` ausführen.

### pdftotext liefert keinen Text

Gescannte PDFs (Bilder statt Text) liefern bei `pdftotext` leeren Text. Das ist kein Fehler —
der Seitentext existiert schlicht nicht als Text-Layer im PDF.

**Workaround:** OCR-Software (z.B. Tesseract) auf das PDF anwenden bevor es hochgeladen wird.

### PDF-Verarbeitung schlägt fehl

```bash
# Status aller fehlgeschlagenen PDFs prüfen
php artisan tinker --execute="
  App\Models\PdfDocument::where('status', 'error')
    ->get(['id', 'media_id', 'error_message'])
    ->each(fn(\$d) => dump(\$d->toArray()));
"

# Fehlgeschlagene PDFs erneut verarbeiten
curl -X POST /admin/pdf/batch-process?mode=failed
```

### Collection-Schema geändert

Nach Schema-Änderungen muss die Collection neu erstellt und alle Dokumente re-indexiert werden:

```bash
php artisan typesense:reindex --fresh
```

### Queue-Jobs stauen sich

```bash
# Wartende Jobs anzeigen
php artisan queue:monitor pdf

# Mehr Worker starten
php artisan queue:work --queue=pdf --timeout=300
```

---

## 12. Dateiübersicht

### Backend

| Datei | Zeilen | Beschreibung |
|-------|--------|-------------|
| `config/typesense.php` | 10 | Umgebungsvariablen: Host, Port, Protocol, API-Key |
| `app/Services/TypesenseService.php` | 200 | Collection-Schema, Indexierung, Volltextsuche mit Gewichtung |
| `app/Jobs/ProcessPdfDocument.php` | 295 | Queue-Job: Download, Rendering, Text-Extraktion, Indexierung |
| `app/Models/PdfDocument.php` | 43 | Model: status, page_count, original_url, error_message |
| `app/Models/PdfPage.php` | 36 | Model: page_number, image_path, extracted_text |
| `app/Observers/MediaObserver.php` | 125 | Automatische PDF-Erkennung und Job-Dispatch |
| `app/Http/Controllers/Api/V1/PdfController.php` | 245 | API: Suche, Metadaten, Seitenbilder, Status, Batch |
| `app/Http/Controllers/Api/V1/AssetCatalogController.php` | 457 | Asset-Katalog mit Metadaten-Suche |
| `app/Console/Commands/SetupTypesenseCommand.php` | 35 | `typesense:setup` — Collection anlegen |
| `app/Console/Commands/TypesenseReindexCommand.php` | 65 | `typesense:reindex` — Alle PDFs neu indexieren |

### Frontend

| Datei | Beschreibung |
|-------|-------------|
| `pim-frontend/src/api/pdf.js` | API-Client: search, getDocument, getPages, reprocess |
| `pim-frontend/src/components/assetCatalog/PdfSearch.vue` | Suchfeld + gruppierte Dokumenten-Ergebnisse |
| `pim-frontend/src/components/assetCatalog/PdfViewer.vue` | Seitenbilder mit Lazy Loading + Navigation |
| `pim-frontend/src/components/assetCatalog/PdfStatusBadge.vue` | Verarbeitungsstatus mit Auto-Polling |
| `pim-frontend/src/views/assetCatalog/AssetCatalogView.vue` | Hauptansicht mit Metadaten/Inhalte-Toggle |

### Datenbank-Migrationen

| Datei | Tabelle |
|-------|---------|
| `database/migrations/2026_03_16_200001_create_pdf_documents_table.php` | `pdf_documents` |
| `database/migrations/2026_03_16_200002_create_pdf_pages_table.php` | `pdf_pages` |
