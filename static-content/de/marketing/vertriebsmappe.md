---
title: Vertriebsmappe — anyPIM
---

# anyPIM Vertriebsmappe

<div class="sales-folder">

## Auf einen Blick

**anyPIM** ist ein Open-Source Product Information Management System (PIM), das Enterprise-Funktionalitaet ohne Enterprise-Kosten liefert. Entwickelt von der incoxx GmbH in Ingolstadt — mit ueber 20 Jahren Erfahrung in Produktdatenmanagement.

| | |
|---|---|
| **Lizenz** | GPL-3.0 — Open Source, kostenfrei |
| **Technologie** | Laravel 11, Vue.js 3, Tailwind CSS, MySQL 8, Redis |
| **Setup-Zeit** | 10 Minuten (ein Script, ein Server) |
| **API** | 370+ REST-Endpoints + GraphQL, API-first-Architektur |
| **Sprachen** | Beliebig viele Sprachen pro Attribut, UI in Deutsch & Englisch |
| **Hosting** | Self-hosted auf eigenem Server (Ubuntu 24.04 LTS) |

---

## Das Problem

Unternehmen kaempfen taeglich mit Produktdaten:

- **Excel-Chaos** — Produktinformationen verteilt auf dutzende Tabellen, per E-Mail verschickt, manuell zusammengefuehrt
- **Vendor Lock-in** — Teure Lizenzen (50.000–500.000+ EUR/Jahr), intransparente Preismodelle, Abhaengigkeit von einem Anbieter
- **Starre Legacy-Systeme** — Neue Attribute? Sechs Wochen Vorlauf. API-Anbindung? Consulting-Projekt
- **Explodierende Kosten** — Lizenzgebuehren, Implementierungskosten, Wartungsvertraege

---

## Die Loesung: anyPIM

### Kernfunktionen

#### EAV-Architektur — Unbegrenzt flexible Attribute

Neue Produkteigenschaften werden in Sekunden angelegt — ohne Migration, ohne Deployment, ohne Warten. Das Entity-Attribute-Value-Modell macht Schemaanpassungen ueberfluessig.

**Unterstuetzte Attributtypen:**
Text, Nummer, Dezimal, Boolean, Datum, Auswahl (Select/Multiselect), Textbereich, Rich-Text (HTML), Medien, Referenz, JSON und mehr.

#### Intelligente Vererbung

Produktvarianten erben Attribute automatisch vom Elternprodukt. Aenderungen propagieren in Echtzeit durch die gesamte Hierarchie. Schluss mit Redundanz und Inkonsistenz.

- Hierarchie-Vererbung (Kategorie → Produkt)
- Varianten-Vererbung (Stammprodukt → Variante)
- Ueberschreibung auf jeder Ebene moeglich

#### PQL-Abfragesprache

Eigene Query Language zum Filtern und Suchen von Produkten ueber beliebige Attributkombinationen:

```
name CONTAINS "Schraube" AND material = "Edelstahl" AND preis > 10
name FUZZY "Schraube"
name SOUNDS_LIKE "Schraube"
```

Unterstuetzt Fuzzy-Search und phonetische Suche — findet Produkte auch bei Tippfehlern.

#### Excel-Import & Export

- **Import:** 14-Tab-Excel-Format mit intelligenter Validierung, Fehlerprotokoll und Vorschau
- **Export:** JSON-Export, Publixx-Export oder eigene Formate ueber Export-Mappings
- **BMEcat:** Unterstuetzung fuer den Branchenstandard BMEcat

#### Feingranulare Rechte (RBAC)

7 vordefinierte Rollen: Admin, Data Steward, Product Manager, Viewer, Export Manager, API Designer, Project Management.

Berechtigungen steuerbar bis auf:
- Attribut-Ebene (wer darf welche Felder sehen/bearbeiten)
- Knoten-Ebene (wer darf welche Kategorien bearbeiten)
- Aktions-Ebene (Import, Export, Workflow-Uebergaenge)

#### Echte Mehrsprachigkeit

Beliebig viele Sprachen pro Attribut — nativ in der Architektur verankert. Keine Plugins, keine Workarounds. Optional: automatische Uebersetzung ueber den integrierten Translation Memory Service (TMS) mit DeepL, Claude AI, Google Translate oder OpenAI.

---

### E-Commerce Integrationen

anyPIM synchronisiert Produktdaten nativ mit fuehrenden Shopsystemen — ohne Middleware, ohne manuellen Export.

| Connector | Funktionen |
|-----------|-----------|
| **Shopware 6** | Produkte, Kategorien, Medien, Properties, Delta-Sync, Profil-basierter Sync, Shop-Reset, Thumbnail-Generierung |
| **Shopify** | Produkte, Kategorien, Medien, Metafields, Delta-Sync (OAuth + Legacy Token) |
| **Salesforce Commerce Cloud** | Produkte, Kategorien, Medien, Checksums fuer grosse Kataloge |
| **anyPIM-to-anyPIM** | Bidirektionaler Sync: Push, Pull, Uebersetzungen, Verbindungstest |

Alle Connectors bieten: OAuth-Authentifizierung, Delta-Sync mit Checksums, Sync-Logs mit Export, Bulk-Operationen, Vorschau/Dry Run und Verbindungsverwaltung.

Mehr Details: [E-Commerce Integrationen](/de/marketing/integrationen)

---

### KI & Automatisierung

#### Translation Memory Service (TMS)

Eigener Mikro-Service fuer automatische Uebersetzung mit vier Providern:

| Provider | Staerke |
|----------|--------|
| **DeepL** | Hoechste Qualitaet fuer europaeische Sprachen |
| **Claude AI** | Kontextsensitive Uebersetzung mit Fachterminologie |
| **Google Translate** | Breiteste Sprachabdeckung |
| **OpenAI** | Flexible Alternative mit GPT-4o |

Translation Jobs mit Workflow (Erstellen → Absenden → Freigeben) und XLIFF Import/Export fuer Uebersetzungsagenturen.

#### Claude AI Connector

KI-gestuetzte Textverarbeitung direkt im PIM:
- Produktbeschreibungen und SEO-Texte generieren
- Bestehende Texte optimieren und umformulieren
- Kontextbezogen auf Basis der Produktattribute

#### GraphQL & API Designer

- **Visueller API Designer** — API-Endpoints per GUI erstellen, ohne Code
- **GraphQL-Schemas** — Dynamisch generiert aus dem PIM-Datenmodell
- **API Streams** — Oeffentliche Endpoints mit Slug-basiertem Zugriff
- **API Templates** — Mit Vorschau, eigenen Keys und Abhaengigkeits-Uebersicht

Mehr Details: [KI, Uebersetzung & API Designer](/de/marketing/ki-uebersetzung)

---

### DAM & Design Integrationen

| Connector | Funktionen |
|-----------|-----------|
| **Canva** | OAuth-basierter Asset-Upload, Export-Profile, Brand-Template-Autofill |
| **Cloudinary** | Asset-Upload in die Cloudinary-Cloud, Transformations-URLs |

---

### Erweiterte Funktionen

| Funktion | Beschreibung |
|----------|--------------|
| **Workflow-System** | Mehrstufige Freigabeprozesse fuer Produktdaten |
| **Versionierung** | Zeitgesteuerte Versionen mit geplantem Aktivierungsdatum |
| **PDF-Vorlagen** | Individuelle Produktdatenblaetter mit eigenem Layout |
| **Katalog-Embed** | Einbettbare Produktkataloge fuer Websites (Online & Offline) |
| **Planungskalender** | Visuelle Uebersicht ueber geplante Aenderungen und Veroeffentlichungen |
| **Preisregionen** | Regionsspezifische Preislisten mit Waehrungsunterstuetzung |
| **Berichte** | Datenqualitaets-Reports und Vollstaendigkeitsanalysen |
| **Medienmanagement** | Upload, Verwaltung und Zuordnung von Bildern, PDFs und Dokumenten |
| **Journal** | Lueckenlose Protokollierung aller Aenderungen |
| **Volltextsuche** | PDF-Volltextsuche ueber Typesense |
| **Export-Jobs** | Automatisierte, zeitgesteuerte Exporte |
| **Hersteller** | Herstellerverwaltung mit Zuordnung zu Produkten |
| **Beziehungstypen** | Flexible Produktbeziehungen (Zubehoer, Ersatzteil, etc.) |
| **Einheiten** | Masseinheitenverwaltung mit Umrechnung |
| **Woerterbuch** | Zentrale Begriffsdatenbank fuer konsistente Terminologie |
| **SSO** | Single Sign-On ueber Azure AD / Entra ID |
| **Excel Template Designer** | Individuelle Excel-Exportvorlagen visuell konfigurieren |
| **Attribut-Mapping** | Cross-Klassifikations-Mapping (z.B. PIM → ETIM) mit Excel Im-/Export |
| **Projekte** | Produktgruppen in Projekten organisieren mit Bulk-Zuordnung |
| **PimSync API** | Dedizierte API fuer PIM-zu-PIM-Synchronisation |
| **User API Keys** | Self-Service: Benutzer verwalten eigene API-Schluessel |
| **Catalog Templates** | Vorlagen fuer Produktkataloge mit Presets und Vorschau |

---

## Technische Architektur

### Systemvoraussetzungen

| Komponente | Version/Spezifikation |
|------------|----------------------|
| Betriebssystem | Ubuntu 24.04 LTS |
| RAM | Minimum 2 GB (4 GB empfohlen) |
| PHP | 8.4 mit OPcache, Redis, GD, Intl |
| Datenbank | MySQL 8.0 (InnoDB, utf8mb4) |
| Cache/Queue | Redis (getrennte Datenbanken fuer Cache, Queue, Session) |
| Suchmaschine | Typesense 27.1 (PDF-Volltextsuche) |
| Webserver | Apache 2 mit mod_rewrite, SSL |
| Queue-Worker | Laravel Horizon via Supervisor |
| Frontend | Vue.js 3, Tailwind CSS, Vite |
| Node.js | 20 LTS |

### Queue-Architektur (Horizon)

Dedizierte Queues fuer verschiedene Aufgaben mit automatischer Skalierung:

| Queue | Zweck | Max. Prozesse |
|-------|-------|---------------|
| indexing | Suchindex-Updates | 4 |
| cache | Cache-Invalidierung | 2 |
| default | Allgemeine Jobs, Import, Export | 4 |
| pdf | PDF-Verarbeitung | 2 |
| warmup | Cache-Warming | 2 |

### API-first-Design

Vollstaendige REST-API mit Token-Authentifizierung (Laravel Sanctum) plus GraphQL-Unterstuetzung. Jede Funktion, die das Frontend kann, kann auch die API. 370+ Endpoints fuer:

- Produkte (CRUD, Suche, PQL-Abfragen)
- Attribute (Verwaltung, Gruppen, Typen)
- Hierarchien (Baumstruktur, Knoten)
- Import/Export (Trigger, Status, Download)
- Connectors (Shopware, Shopify, Salesforce, Canva, Cloudinary)
- Uebersetzung (TMS, Translation Jobs, XLIFF)
- API Designer (Templates, Streams, GraphQL)
- System (Health-Check, Status, Queue-Management)

### Monitoring & Betrieb

- **Health-Endpoint:** `/api/v1/health` — Prueft Datenbank, Redis, Storage, Queue, Disk
- **Horizon-Dashboard:** Echtzeit-Ueberwachung aller Queue-Worker
- **Healthcheck-Script:** CLI-Tool mit JSON-Output fuer Monitoring-Systeme
- **Logging:** Getrennte Logs fuer Application, Import, Export, Horizon

---

## Vergleich: anyPIM vs. Enterprise-PIM

| Kriterium | anyPIM | Typisches Enterprise-PIM |
|-----------|--------|--------------------------|
| **Lizenzkosten** | 0 EUR — Open Source | 50.000–500.000+ EUR/Jahr |
| **Setup-Zeit** | 10 Minuten | 3–12 Monate |
| **Neue Attribute** | Sofort, ohne Migration | Schema-Aenderung + Deployment |
| **API** | 370+ REST-Endpoints + GraphQL | Oft eingeschraenkt oder kostenpflichtig |
| **E-Commerce** | Shopware 6, Shopify, Salesforce nativ | Eigene Connectoren oder Middleware |
| **KI & Uebersetzung** | DeepL, Claude AI, Google, OpenAI | Manuell oder Drittanbieter-Plugin |
| **Abfragesprache** | PQL mit Fuzzy + phonetisch | Einfache Filter oder SQL |
| **Quellcode** | 100% einsehbar, anpassbar | Closed Source, Blackbox |
| **Vendor Lock-in** | Keiner | Hoch |
| **Tech-Stack** | Laravel 11, Vue 3, MySQL | Java/Proprietary, komplex |
| **Hosting** | Self-hosted, volle Kontrolle | Cloud-Abhaengigkeit oder teure Infrastruktur |
| **Support** | Community + optionaler Enterprise-Support | Kostenpflichtige Wartungsvertraege |

---

## Zielgruppen

### Mittelstaendische Unternehmen
Unternehmen mit 500–50.000 Produkten, die ihre Produktdaten professionell verwalten moechten, ohne sechsstellige Lizenzkosten.

### E-Commerce-Haendler
Online-Haendler, die Produktdaten fuer mehrere Kanaele (Webshop, Marktplaetze, Print) zentral pflegen und automatisiert exportieren moechten.

### Industrieunternehmen
Hersteller mit komplexen Produkthierarchien, technischen Attributen und Mehrsprachigkeitsbedarf (BMEcat, eCl@ss-kompatibel).

### Agenturen & Systemintegratoren
Dienstleister, die fuer ihre Kunden PIM-Loesungen implementieren und dabei volle Kontrolle ueber den Quellcode benoetigen.

---

## Implementierung

### Schnellstart

```bash
# 1. Repository klonen
git clone https://github.com/incoxx/publixx-pim.git
cd publixx-pim

# 2. Setup ausfuehren (installiert alles automatisch)
sudo bash setup.sh

# 3. Fertig — PIM ist erreichbar
```

Das Setup-Script installiert und konfiguriert automatisch: PHP 8.4, MySQL 8, Redis, Apache, Node.js, Typesense, Horizon/Supervisor und alle Abhaengigkeiten.

### Update-Prozess

```bash
sudo bash update.sh
```

Automatisierter 10-Schritte-Prozess: Wartungsmodus, Git Pull, Composer, Migration, Frontend-Build, Dokumentation, Cache, Service-Neustart, Health-Check.

### Deployment-Modi

| Modus | Befehl | Umfang |
|-------|--------|--------|
| Full | `sudo bash deploy.sh` | Alles (Standard) |
| Quick | `sudo bash deploy.sh --quick` | Nur Cache & Restart |
| Backend | `sudo bash deploy.sh --backend` | Ohne Frontend |
| Frontend | `sudo bash deploy.sh --frontend` | Nur Frontend |

---

## Sicherheit

- **Authentifizierung:** Laravel Sanctum (Token + SPA-Session)
- **SSO:** Azure AD / Entra ID Integration
- **RBAC:** Feingranulare Rollen- und Berechtigungsverwaltung
- **HTTPS:** Automatische Let's Encrypt SSL-Zertifikate
- **Session:** Redis-basiert, konfigurierbare Lebensdauer (Standard: 120 Minuten)
- **Audit-Log:** Lueckenlose Protokollierung aller Benutzeraktivitaeten
- **CORS:** Konfigurierbare Stateful Domains

---

## Support & Kontakt

### Open-Source-Community
- GitHub: [github.com/incoxx/publixx-pim](https://github.com/incoxx/publixx-pim)
- Issues & Feature-Requests ueber GitHub

### Enterprise-Support
Professioneller Support, individuelle Anpassungen und Schulungen durch die incoxx GmbH.

### Kontakt

**incoxx GmbH**
Aloisiweg 11
85049 Ingolstadt
Deutschland

Telefon: 0800 7542116
E-Mail: [info@incoxx.com](mailto:info@incoxx.com)
Web: [www.incoxx.com](https://www.incoxx.com)

Geschaeftsfuehrung: Gabriele Karst, Markus Gerber
Handelsregister: AG Ingolstadt, HRB 5970
USt-IdNr: DE277889591

</div>
