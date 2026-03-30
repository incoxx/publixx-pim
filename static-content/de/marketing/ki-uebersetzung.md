---
title: KI, Uebersetzung & API Designer — anyPIM
---

# KI, Uebersetzung & API Designer

<div class="sales-folder">

## Intelligente Automatisierung fuer Produktdaten

anyPIM nutzt kuenstliche Intelligenz und moderne API-Technologien, um Produktdaten-Workflows zu automatisieren. Von der automatischen Uebersetzung ueber KI-gestuetzte Texterstellung bis zum visuellen API Designer — alles nativ integriert.

---

## Translation Memory Service (TMS)

### Vier Provider, ein Workflow

anyPIM betreibt einen eigenen Translation Memory Service als Mikro-Service. Uebersetzungen werden zentral gespeichert, wiederverwendet und ueber einen Freigabe-Workflow gesteuert.

**Unterstuetzte Uebersetzungsprovider:**

| Provider | Staerke |
|----------|--------|
| **DeepL** | Hoechste Qualitaet fuer europaeische Sprachen, formell/informell steuerbar |
| **Claude AI** | Kontextsensitive Uebersetzung mit Verstaendnis fuer Fachterminologie |
| **Google Translate** | Breiteste Sprachabdeckung, kostenguenstig fuer grosse Volumen |
| **OpenAI** | Flexible Alternative mit GPT-4o |

### Translation Jobs

Strukturierter Uebersetzungs-Workflow:

1. **Erstellen** — Quellsprache, Zielsprache(n) und Produkte waehlen
2. **Absenden** — Job an den TMS-Provider senden
3. **Pruefen** — Uebersetzungen vor Uebernahme pruefen
4. **Freigeben** — Genehmigte Uebersetzungen in die Produktdaten uebernehmen
5. **Wiederholen** — Bei Bedarf einzelne Uebersetzungen erneut anfordern

### XLIFF Import & Export

Fuer die Zusammenarbeit mit Uebersetzungsagenturen und CAT-Tools:

- **Export** — Produkttexte als XLIFF-Datei exportieren (branchenstandard)
- **Import** — Fertige Uebersetzungen aus XLIFF zurueck ins PIM importieren
- **Merklisten-Export** — XLIFF-Export direkt aus der Merkliste fuer selektive Uebersetzung

---

## KI-Integration

### Claude AI Connector

Direkte Integration mit der Anthropic Claude API fuer KI-gestuetzte Textverarbeitung:

- **Texterstellung** — Produktbeschreibungen, SEO-Texte und Marketingtexte generieren
- **Textoptimierung** — Bestehende Texte verbessern, kuerzen oder umformulieren
- **Kontextbezogen** — Die KI kennt die Produktattribute und erstellt passende Inhalte
- **Konfigurierbares Modell** — Standard: Claude Sonnet, anpassbar auf andere Modelle

### OpenAI

Alternative KI-Anbindung ueber die OpenAI API (GPT-4o). Gleiche Funktionalitaet, andere Modellbasis.

---

## GraphQL & API Designer

### Visueller API Designer

Erstellen Sie massgeschneiderte API-Endpoints — ohne Code:

- **API Templates** — Definieren Sie, welche Felder und Relationen ein Endpoint zurueckgeben soll
- **Schema-Vorschau** — Live-Vorschau des generierten Schemas vor der Veroeffentlichung
- **API Keys** — Pro Template eigene Authentifizierung, jederzeit regenerierbar
- **Abhaengigkeiten** — Uebersicht, welche Produkte und Attribute ein Template nutzt

### GraphQL-Unterstuetzung

Dynamisch generierte GraphQL-Schemas basierend auf Ihrem Datenmodell:

- **Schema Builder** — Automatische Generierung aus PIM-Attributen und -Relationen
- **Flexible Abfragen** — Clients fragen nur die Felder ab, die sie benoetigen
- **Verschachtelte Daten** — Produkte mit Varianten, Medien und Preisen in einer Abfrage

### API Streams

Oeffentliche API-Endpoints mit Slug-basiertem Zugriff:

- **URL-basiert** — Jeder Stream bekommt eine lesbare URL (`/api-streams/mein-katalog`)
- **Konfigurierbar** — Format, Felder und Filter pro Stream definierbar
- **Ohne Authentifizierung** — Fuer oeffentliche Kataloge und Integrationen

---

## Excel Template Designer

Individuelle Excel-Exportvorlagen visuell konfigurieren:

- **Feld-Auswahl** — Frei waehlbare Spalten aus Attributen, Preisen, Medien und Relationen
- **Vorschau** — Live-Vorschau vor dem Export
- **Import** — Bestehende Excel-Strukturen als Vorlage importieren
- **Fortschrittsanzeige** — Echtzeit-Fortschritt bei grossen Exporten mit Abbruch-Option

---

## Attribut-Mapping

Cross-Klassifikations-Mapping fuer Industriestandards:

- **Quell → Ziel** — PIM-Attribute auf Klassifikationsfelder mappen (z.B. ETIM, eCl@ss)
- **Mapping-Regeln** — Transformationsregeln pro Zuordnung definieren
- **Bulk-Operationen** — Mappings fuer viele Attribute auf einmal anlegen
- **Sync** — Gemappte Werte pro Produkt oder im Batch synchronisieren
- **Excel Im-/Export** — Mapping-Tabellen als Excel pflegen und importieren

---

## Weitere Automatisierungen

| Feature | Beschreibung |
|---------|-------------|
| **Projekte** | Produktgruppen in Projekten organisieren, mit Bulk-Zuordnung |
| **Catalog Templates** | Vorlagen fuer Produktkataloge mit Presets und Vorschau |
| **User API Keys** | Self-Service: Benutzer verwalten eigene API-Schluessel |
| **PimSync API** | Dedizierte API fuer PIM-zu-PIM-Synchronisation |
| **Export-Profile** | Konfigurierbare Profile mit Streaming-Unterstuetzung |
| **Export-Jobs** | Zeitgesteuerte Exporte mit SFTP-Zustellung |

---

## Bereit fuer die Zukunft?

<div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 2rem; margin-bottom: 2rem;">
<a href="/web/help/de/installation/schnellstart" class="marketing-cta-button marketing-cta-primary">Schnellstart-Anleitung</a>
<a href="/web/help/de/marketing/integrationen" class="marketing-cta-button marketing-cta-secondary">E-Commerce Integrationen</a>
<a href="/web/help/de/marketing/" class="marketing-cta-button marketing-cta-secondary">Zurueck zur Uebersicht</a>
</div>

</div>
