# anyPIM — Dokumentation

## Architektur & Spezifikationen

Nummerierte technische Spec-Dateien, die das System im Detail beschreiben:

| # | Datei | Thema |
|---|-------|-------|
| 01 | [Datenmodell](architecture/01-datenmodell.md) | Entity-Relationship-Modell, 58 Tabellen |
| 02 | [API](architecture/02-api.md) | RESTful API-Konventionen, ~285 Endpoints |
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

## Betrieb

| Datei | Beschreibung |
|-------|-------------|
| [Installation](operations/install.md) | Voraussetzungen & `setup.sh` |
| [Deployment](operations/deployment.md) | Manuelles Server-Deployment |
| [Production](operations/production.md) | Produktionsbetrieb |
| [Updates](operations/update.md) | Update-Prozeduren & `update.sh` |

## Referenz

| Datei | Beschreibung |
|-------|-------------|
| [API Reference](reference/api.md) | REST API-Dokumentation |
| [Database Schema](reference/database.md) | Datenbankschema |
| [Testing](reference/testing.md) | Testing-Strategie |

## Anleitungen

| Datei | Beschreibung |
|-------|-------------|
| [Von 0 auf 100](guides/guide-von-0-auf-100.md) | Komplettes Onboarding |
| [Offline-App](guides/offline-app.md) | Offline-Funktionalität |
| [Offline-Datenpaket](guides/offline-datenpaket.md) | Datenpaket-Erstellung |
| [PDF-Suche (Typesense)](guides/pdf-suche-typesense.md) | Typesense-Integration |
| [Tastatur-Shortcuts](guides/tastatur-shortcuts.md) | Keyboard-Referenz |

## Features & Planung

| Datei | Beschreibung |
|-------|-------------|
| [Features](features/features.md) | Feature-Übersicht |
| [Integration](features/integration.md) | Integrationsbericht |
| [Plan: Hyperlink-Datentypen](features/plan-hyperlink-datatypes.md) | Feature-Plan |

## Vertrieb

| Datei | Beschreibung |
|-------|-------------|
| [Verkaufsmappe](sales/verkaufsmappe.md) | Sales-Dokumentation |
| [TMS-Validation](sales/tms-plan-validation.md) | TMS Plan Validation |
