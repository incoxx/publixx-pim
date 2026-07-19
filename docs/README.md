# anyPIM — Dokumentation

## Architektur & Spezifikationen

Nummerierte technische Spec-Dateien, die das System im Detail beschreiben:

| # | Datei | Thema |
|---|-------|-------|
| 01 | [Datenmodell](architecture/01-datenmodell.md) | Entity-Relationship-Modell, ~134 Tabellen |
| 02 | [API](architecture/02-api.md) | RESTful API-Konventionen, ~870 Endpoints |
| 03 | [PQL Engine](architecture/03-pql-engine.md) | Publixx Query Language |
| 04 | [Media](architecture/04-media.md) | Medien-Upload, Storage, Thumbnails |
| 05 | [i18n](architecture/05-i18n.md) | Übersetzungsarchitektur |
| 06 | [Import](architecture/06-import.md) | Excel-Import (14-Tab-Template) |
| 07 | [Frontend](architecture/07-frontend.md) | Vue.js 3 Architektur |
| 08 | [Performance](architecture/08-performance.md) | Latenz-Budgets, Caching |
| 09 | [Export](architecture/09-export-publixx.md) | JSON-Export & Publixx-Integration |
| 10 | [Vererbung](architecture/10-vererbung.md) | Hierarchie- & Varianten-Vererbung |
| 11 | [Auth](architecture/11-auth.md) | Authentifizierung & Berechtigungen |
| 12 | [JSON Export/Import](architecture/12-json-export-import.md) | Strukturierter JSON-Datenaustausch |
| 13 | [Workflow](architecture/13-workflow.md) | Task-Management |
| 14 | [Reports & PDF](architecture/14-reports-pdf.md) | Report-Templates & PDF-Generierung |
| 15 | [Preise](architecture/15-price-management.md) | Preismanagement & Regionen |
| 16 | [Dashboard](architecture/16-dashboard.md) | KPI-Widgets & Quick Actions |
| 17 | [BMEcat](architecture/17-bmecat.md) | BMEcat XML Import/Export |
| 18 | [Audit](architecture/18-audit-journal.md) | Audit-Trail & System-Journal |
| 19 | [Suche](architecture/19-search.md) | Suchindex & Filterung |
| 20 | [Referenzprofile](architecture/20-reference-profiles.md) | Virtuelle Referenzprodukte: Prüfregeln für Attributwerte |
| 21 | [Semantische Suche](architecture/21-semantic-search.md) | Meilisearch Hybrid-Suche (Keyword + Vektor) |
| 22 | [Strukturierter Content](architecture/22-structured-content.md) | CMS-Modul: Web- & Print-Publishing |
| 23 | [Content Caching](architecture/23-content-caching.md) | Caching gerenderter Content-Ausgaben (Sitemap, Seiten, Produktdetail) |

## Betrieb

| Datei | Beschreibung |
|-------|-------------|
| [Installation](operations/install.md) | Voraussetzungen & `setup.sh` |
| [Deployment](operations/deployment.md) | Manuelles Server-Deployment |
| [Production](operations/production.md) | Produktionsbetrieb |
| [Updates](operations/update.md) | Update-Prozeduren & `update.sh` |
| [Docker-Klon](operations/docker-clone.md) | Laufende Instanz per `docker_clone.sh` in Docker-Compose-Stack klonen |

## Referenz

| Datei | Beschreibung |
|-------|-------------|
| [API Reference](reference/api.md) | REST API-Dokumentation |
| [Database Schema](reference/database.md) | Datenbankschema |
| [Umgebungsvariablen](reference/environment-variables.md) | Vollständige `.env`-Referenz |
| [Testing](reference/testing.md) | Testing-Strategie |

## Anleitungen

| Datei | Beschreibung |
|-------|-------------|
| [Von 0 auf 100](guides/guide-von-0-auf-100.md) | Komplettes Onboarding |
| [Offline-App](guides/offline-app.md) | Offline-Funktionalität |
| [Offline-Datenpaket](guides/offline-datenpaket.md) | Datenpaket-Erstellung |
| [PDF-Suche (Typesense)](guides/pdf-suche-typesense.md) | Typesense-Integration |
| [Tastatur-Shortcuts](guides/tastatur-shortcuts.md) | Keyboard-Referenz |
| [MCP-Connector](guides/mcp-connector.md) | Model-Context-Protocol-Anbindung |
| [Copilot](guides/copilot.md) | KI-Assistenz im PIM |

## Features & Planung

| Datei | Beschreibung |
|-------|-------------|
| [Features](features/features.md) | Feature-Übersicht |
| [Integration](features/integration.md) | Integrationsbericht |
| [Video-Engine](features/video-engine.md) | Internes Tooling zur Demo-Video-Erzeugung |
| [Cockpit-Rollenportale](features/cockpit-rollenportale-konzept.md) | Konzept für rollenspezifische Portale |
| [Plan: Hyperlink-Datentypen](features/plan-hyperlink-datatypes.md) | Feature-Plan |
| [Plan: Neue Attributtypen (Referenzen & freie Selects)](features/plan-4-neue-attributtypen-referenzen-selects.md) | Feature-Plan |
| [Plan: Social Video](features/plan-social-video.md) | Feature-Plan |

## Vertrieb

| Datei | Beschreibung |
|-------|-------------|
| [Verkaufsmappe](sales/verkaufsmappe.md) | Sales-Dokumentation |
| [TMS-Validation](sales/tms-plan-validation.md) | TMS Plan Validation |
