# anyPIM

Product Information Management System — eine Laravel 11 + Vue 3 Anwendung zur zentralen Verwaltung von Produktdaten, Medien, Hierarchien und Exporten.

## Features

- **Produkte & Varianten** — Stammdaten, konfigurierbare Attribute, Vererbung
- **Hierarchien** — Master-/Output-Hierarchien mit Materialized Path, Attribut-Vererbung ueber alle Ebenen
- **Medien** — Upload, Thumbnails, Asset-Katalog, Medien-Attribute
- **Attribute** — Typen (String, Number, Float, Date, Flag, Selection, Collection, Composite), Einheiten, Wertelisten
- **PQL** — Product Query Language fuer flexible Produktsuche
- **Import/Export** — Excel-Import, JSON-Export, Publixx-Integration (PXF-Templates)
- **Preise & Relationen** — Preistypen, Produktbeziehungen mit Attributen
- **Versionierung** — Produktversionen mit Scheduling und Rollback
- **Rollen & Rechte** — Benutzerverwaltung mit Sanctum-Authentifizierung
- **Frontend** — Vue 3 SPA mit Tailwind CSS

## Tech Stack

| Komponente | Technologie |
|---|---|
| Backend | PHP 8.2+ / Laravel 11 |
| Frontend | Vue 3, Vite, Tailwind CSS |
| Datenbank | MySQL 8+ |
| Cache/Queue | Redis |
| Queue Worker | Laravel Horizon + Supervisor |
| Webserver | Apache 2.4 |

## Schnellstart

```bash
# 1. Repository klonen
git clone <repository-url> /var/www/publixx-pim
cd /var/www/publixx-pim

# 2. Setup ausfuehren (interaktiv)
sudo bash setup.sh

# 3. Healthcheck
bash healthcheck.sh
```

Das Setup-Script installiert alle Abhaengigkeiten, konfiguriert die Datenbank, baut das Frontend und richtet Apache ein — sowohl als **Root-Installation** als auch als **Subdirectory** (z.B. `/web`).

## Dokumentation

Die vollstaendige Dokumentation ist als VitePress-Site verfuegbar:

- **Deutsch**: [https://smartentities.de/web/help/de/](https://smartentities.de/web/help/de/)
- **English**: [https://smartentities.de/web/help/en/](https://smartentities.de/web/help/en/)

| Kapitel | Inhalt |
|---|---|
| [Schnellstart](https://smartentities.de/web/help/de/installation/schnellstart) | In 10 Minuten zum laufenden PIM |
| [Bedienung](https://smartentities.de/web/help/de/bedienung/) | Benutzerhandbuch fuer alle Module |
| [Architektur](https://smartentities.de/web/help/de/architektur/) | EAV-Datenmodell, Services, Vererbung |
| [API-Referenz](https://smartentities.de/web/help/de/api/) | 90+ REST-Endpoints mit Beispielen |
| [Import](https://smartentities.de/web/help/de/import/) | Excel-Import mit 14-Tab-Struktur |
| [Export](https://smartentities.de/web/help/de/export/) | JSON-Export und Publixx-Integration |
| [FAQ](https://smartentities.de/web/help/de/faq/) | Haeufig gestellte Fragen |

Lokale Dokumentation:

| Dokument | Inhalt |
|---|---|
| [INSTALL.md](INSTALL.md) | Installations-Anleitung (setup.sh) |
| [UPDATE.md](UPDATE.md) | Update-Anleitung (update.sh) |
| [API.md](API.md) | REST API Referenz (180+ Endpoints) |
| [static-content/](static-content/) | VitePress-Quelldateien |

## Scripts

| Script | Zweck |
|---|---|
| `setup.sh` | Erstinstallation (interaktiv, als root) |
| `update.sh` | Update von GitHub + Rebuild (als root) |
| `healthcheck.sh` | Service-Status pruefen (lokal oder per HTTP) |

## Verzeichnisstruktur

```
publixx-pim/
  app/                  Laravel-Anwendung
    Http/Controllers/   API-Controller (V1)
    Models/             Eloquent-Models
    Services/           Business-Logik (Inheritance, PQL, Export)
  pim-frontend/         Vue 3 SPA (eigenes npm-Projekt)
  database/
    migrations/         Datenbankschema
    seeders/            Demo-Daten
  routes/api.php        API-Routen (/api/v1/*)
  static-content/       VitePress-Dokumentation + Marketing
```

## Deployment-Modi

### Root-Modus
PIM ist die einzige Anwendung auf der Domain. Apache VHost auf Port 80/443.

### Subdirectory-Modus
PIM laeuft unter einem Pfad (z.B. `https://example.com/web`). Apache-Alias wird in den bestehenden VHost eingebunden. SSL wird vom bestehenden VHost gehandhabt.

## API

Alle Endpoints unter `/api/v1/`. Authentifizierung via Laravel Sanctum (Bearer Token).

```bash
# Login
curl -X POST https://example.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Produkte abrufen
curl https://example.com/api/v1/products \
  -H "Authorization: Bearer <token>"

# Healthcheck (ohne Auth)
curl https://example.com/api/v1/health
```

Siehe [API.md](API.md) fuer die vollstaendige Referenz.

## Lizenz

AGPL-3.0-only — siehe [LICENSE](LICENSE).
