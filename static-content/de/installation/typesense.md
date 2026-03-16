---
title: Typesense
---

# Typesense

anyPIM nutzt [Typesense](https://typesense.org/) als Suchmaschine fuer die Volltextsuche in PDF-Dokumenten. Wenn ein PDF hochgeladen wird, extrahiert anyPIM den Text jeder Seite und indexiert ihn in Typesense. Benutzer koennen anschliessend seitengenau in allen PDFs suchen.

::: info Typesense ist optional
Die PDF-Verarbeitung (Seitenvorschau, Text-Extraktion) funktioniert auch ohne Typesense. Nur die seitenuebergreifende Volltextsuche in PDFs benoetigt Typesense.
:::

## Voraussetzungen

| Komponente | Beschreibung |
|---|---|
| **Typesense Server** | Version 27.x oder neuer |
| **poppler-utils** | System-Paket fuer `pdftotext`, `pdftoppm` und `pdfinfo` |
| **typesense/typesense-php** | PHP-Client (wird via Composer installiert) |

## Installation

### 1. Typesense Server

```bash
# Debian/Ubuntu (.deb-Paket)
curl -O https://dl.typesense.org/releases/27.1/typesense-server-27.1-amd64.deb
sudo dpkg -i typesense-server-27.1-amd64.deb
```

Nach der Installation laeuft Typesense automatisch als systemd-Service auf Port **8108**. Der generierte API-Key steht in:

```bash
cat /etc/typesense/typesense-server.ini
```

### 2. poppler-utils

```bash
sudo apt install poppler-utils
```

Stellt die folgenden CLI-Tools bereit:

| Tool | Verwendung |
|---|---|
| `pdfinfo` | Seitenanzahl ermitteln |
| `pdftoppm` | Seiten als PNG rendern |
| `pdftotext` | Text pro Seite extrahieren |

### 3. PHP-Client

```bash
composer require typesense/typesense-php
```

## Konfiguration

Die Typesense-Verbindung wird ueber Umgebungsvariablen in der `.env` konfiguriert:

```env
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=dein-api-key-hier
```

Den API-Key finden Sie in `/etc/typesense/typesense-server.ini` (Feld `api-key`).

::: warning Produktion
In Produktionsumgebungen sollte der Typesense-API-Key vertraulich behandelt werden. Verwenden Sie niemals den Standard-Key in oeffentlich zugaenglichen Konfigurationen.
:::

## Collection einrichten

anyPIM legt eine Typesense-Collection namens `pdf_pages` an. Diese wird mit folgendem Artisan-Befehl erstellt:

```bash
php artisan typesense:setup
```

Um eine bestehende Collection zu loeschen und neu zu erstellen:

```bash
php artisan typesense:setup --force
```

### Collection-Schema

| Feld | Typ | Beschreibung |
|---|---|---|
| `pdf_document_id` | string | Referenz auf das PdfDocument |
| `media_id` | string | Referenz auf das Media-Objekt |
| `page_number` | int32 | Seitennummer |
| `text` | string | Extrahierter Text der Seite |
| `title` | string | Titel des Dokuments |
| `language` | string | Sprache (optional, facettierbar) |

## PDF-Verarbeitungspipeline

Wenn ein PDF ueber die Medienverwaltung hochgeladen wird, durchlaeuft es automatisch folgende Schritte:

1. **Download** — PDF wird heruntergeladen und temporaer gespeichert
2. **Seitenanzahl** — `pdfinfo` ermittelt die Gesamtzahl der Seiten
3. **Rendering** — `pdftoppm` rendert jede Seite als PNG (150 DPI)
4. **Konvertierung** — PHP GD wandelt PNG in WebP um (Qualitaet 85%)
5. **Textextraktion** — `pdftotext` extrahiert den Text jeder Seite
6. **Indexierung** — Alle Seiten werden in Typesense indexiert

Die Verarbeitung laeuft asynchron ueber die Queue `pdf` und wird von Laravel Horizon verwaltet.

## Service-Verwaltung

```bash
# Status pruefen
sudo systemctl status typesense-server

# Neustarten
sudo systemctl restart typesense-server

# Logs anzeigen
sudo journalctl -u typesense-server -f
```

## Fehlerbehebung

### Typesense nicht erreichbar

```bash
# Pruefen ob der Service laeuft
sudo systemctl status typesense-server

# Port pruefen
curl http://localhost:8108/health
```

### "Class Typesense\Client not found"

Der PHP-Client ist nicht installiert:

```bash
composer require typesense/typesense-php
```

### PDF-Seiten ohne Text

Wenn der extrahierte Text leer ist, enthaelt das PDF moeglicherweise nur Bilder (gescanntes Dokument). Die Textextraktion funktioniert nur bei PDFs mit eingebettetem Text.

### Collection existiert bereits

Beim erneuten Ausfuehren von `typesense:setup` ohne `--force` wird ein Fehler ausgegeben, wenn die Collection bereits existiert. Verwenden Sie `--force`, um sie neu zu erstellen (loescht bestehende Daten).
