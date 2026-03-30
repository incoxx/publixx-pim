---
title: E-Commerce Integrationen — anyPIM
---

# E-Commerce & Integrationen

<div class="sales-folder">

## anyPIM verbindet sich mit Ihrem Stack

anyPIM ist kein isoliertes System. Ueber native Connectors synchronisieren Sie Produktdaten direkt mit Ihren Shopsystemen, DAM-Plattformen und Design-Tools — ohne Middleware, ohne CSV-Export, ohne manuellen Aufwand.

Alle Connectors teilen eine gemeinsame Architektur: OAuth-Authentifizierung, Delta-Sync, Checksums, Sync-Logs und Bulk-Operationen. Konfiguration ueber die Weboberflaeche, Ausfuehrung per Klick oder automatisiert.

---

## Shopsysteme

### Shopware 6

Vollstaendige, bidirektionale Integration mit Shopware 6. anyPIM synchronisiert:

- **Produkte** — Stammdaten, Beschreibungen, Varianten und benutzerdefinierte Felder
- **Kategorien** — Hierarchien automatisch als Shopware-Kategoriebaeume abbilden
- **Medien** — Produktbilder und Dokumente inkl. Thumbnail-Generierung
- **Properties** — Shopware-Eigenschaftsgruppen und -Optionen aus PIM-Attributen erzeugen
- **Delta-Sync** — Nur geaenderte Produkte uebertragen, basierend auf Checksums
- **Profil-basierter Sync** — Export-Profile definieren, welche Produkte und Attribute synchronisiert werden

Zusatzfunktionen: Shop-Reset, Purge (Kategorien/Medien einzeln loeschen), Sync-Logs mit Export, Checksum-Verwaltung.

### Shopify

Native Anbindung an Shopify ueber die Admin API:

- **Produkte** — Stammdaten, Varianten und Preise synchronisieren
- **Kategorien** — Collections automatisch erstellen und zuordnen
- **Medien** — Produktbilder hochladen und verwalten
- **Metafields** — Benutzerdefinierte Felder als Shopify-Metafields abbilden
- **Delta-Sync** — Checksums fuer inkrementelle Updates

Unterstuetzt sowohl Legacy Access Tokens als auch OAuth (Client ID / Secret).

### Salesforce Commerce Cloud

Enterprise-E-Commerce-Anbindung fuer Salesforce Commerce Cloud (SFCC):

- **Produkte** — Produktdaten in den SFCC-Katalog synchronisieren
- **Kategorien** — Katalogstruktur automatisch abbilden
- **Medien** — Assets in die SFCC-Medienbibliothek hochladen
- **Checksums** — Inkrementelle Updates fuer grosse Kataloge

Konfiguration ueber Account Manager Client Credentials. Unterstuetzt beliebige Site- und Catalog-IDs.

---

## PIM-to-PIM

### anyPIM Connector

Bidirektionale Synchronisation zwischen anyPIM-Instanzen:

- **Push** — Produkte und Medien an eine andere anyPIM-Instanz senden
- **Pull** — Produkte und Uebersetzungen von einer anderen Instanz abrufen
- **Bidirektional** — Vollstaendiger Abgleich in beide Richtungen
- **Verbindungstest** — Konnektivitaet vorab pruefen

Ideal fuer verteilte Teams, Mandanten-Setups oder die Zusammenarbeit zwischen Unternehmen.

---

## DAM & Design

### Canva

OAuth-basierte Integration mit Canva fuer kreative Workflows:

- **Asset-Sync** — Produktbilder und Medien mit Canva synchronisieren
- **Export-Profile** — Konfigurierbare Profile fuer automatisierte Canva-Exporte
- **Design-Templates** — Produktdaten in Canva-Designs einfliessen lassen

Perfekt fuer Marketing-Teams, die Produktbilder direkt in Canva weiterverarbeiten.

### Cloudinary

Cloud-basiertes Media Asset Management:

- **Asset-Sync** — Medien zwischen anyPIM und Cloudinary synchronisieren
- **Transformationen** — Cloudinary-URLs fuer automatische Bildoptimierung nutzen
- **Zentrales DAM** — Cloudinary als Single Source of Truth fuer Medien

---

## Gemeinsame Connector-Features

Alle Connectors in anyPIM nutzen dieselbe robuste Infrastruktur:

| Feature | Beschreibung |
|---------|-------------|
| **OAuth-Authentifizierung** | Sichere Autorisierung ueber OAuth 2.0 Flow |
| **Delta-Sync** | Nur geaenderte Daten uebertragen — spart Zeit und Traffic |
| **Checksums** | Aenderungserkennung auf Feldebene |
| **Bulk-Operationen** | Hunderte Produkte in einem Durchlauf synchronisieren |
| **Vorschau / Dry Run** | Aenderungen pruefen, bevor sie uebertragen werden |
| **Sync-Logs** | Lueckenlose Protokollierung jeder Synchronisation |
| **Export-Profile** | Definieren, welche Produkte und Attribute synchronisiert werden |
| **Verbindungsverwaltung** | Mehrere Verbindungen pro Connector verwalten |

---

## Bereit fuer die Integration?

<div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 2rem; margin-bottom: 2rem;">
<a href="/web/help/de/installation/schnellstart" class="marketing-cta-button marketing-cta-primary">Schnellstart-Anleitung</a>
<a href="/web/help/de/marketing/ki-uebersetzung" class="marketing-cta-button marketing-cta-secondary">KI & API Designer</a>
<a href="/web/help/de/marketing/" class="marketing-cta-button marketing-cta-secondary">Zurueck zur Uebersicht</a>
</div>

</div>
