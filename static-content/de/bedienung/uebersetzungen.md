---
title: Übersetzungen (TMS)
---

# Übersetzungen (TMS)

Das **Translation Memory Service** (TMS) ist ein integriertes Übersetzungssystem für Metadaten im anyPIM. Während Produktdaten (Attributwerte) über den XLIFF-Export/-Import übersetzt werden, kümmert sich das TMS um die Übersetzung von **Systembezeichnungen** wie Attributnamen, Wertelisten-Einträge, Hierarchie-Knoten und andere Metadaten-Entitäten.

## Übersicht

Das TMS verwaltet Übersetzungen für folgende Entitätstypen:

| Entitätstyp | Feld | Beispiel |
|---|---|---|
| **Attribute** | `name_json` | "Farbe" → "Color", "Couleur", "Colore" |
| **Attributgruppen** | `name_json` | "Technische Daten" → "Technical Data" |
| **Attribut-Sichten** | `name_json` | "Grunddaten" → "Basic Data" |
| **Wertelisten** | `name_json` | "Materialien" → "Materials" |
| **Wertelisten-Einträge** | `display_value_json` | "Baumwolle" → "Cotton" |
| **Produkttypen** | `name_json` | "Standardartikel" → "Standard Article" |
| **Preisarten** | `name_json` | "Listenpreis" → "List Price" |
| **Beziehungstypen** | `name_json` | "Zubehör" → "Accessories" |
| **Einheitengruppen** | `name_json` | "Gewicht" → "Weight" |
| **Einheiten** | `abbreviation_json` | "kg" → "kg" |
| **Hierarchien** | `name_json` | "Produktkatalog" → "Product Catalog" |
| **Hierarchie-Knoten** | `name_json` | "Elektrowerkzeuge" → "Power Tools" |

## Die Benutzeroberfläche

Die Übersetzungsverwaltung erreichen Sie über den Menüpunkt **Übersetzungen** in der Sidebar (Symbol: Sprachen-Icon). Die Oberfläche ist in vier Tabs gegliedert:

### Tab: Übersicht

Die Übersichtsseite zeigt den aktuellen Stand der Übersetzungsabdeckung:

- **Begriffe gesamt**: Anzahl aller im TMS registrierten Quell-Texte
- **Sprachkarten**: Für jede konfigurierte Zielsprache wird eine Karte mit folgenden Informationen angezeigt:
  - Abdeckung in Prozent (Fortschrittsbalken)
  - Anzahl übersetzter Begriffe
  - Anzahl geprüfter Übersetzungen (manuell bestätigt)
  - Anzahl fehlender Übersetzungen

### Tab: Begriffe

Die Begriffs-Tabelle zeigt alle im TMS registrierten Übersetzungseinheiten (Translation Units). Sie können filtern nach:

- **Suchtext**: Volltextsuche im Quelltext
- **Sprache**: Zielsprache für die Übersetzungen
- **Bereich**: Entitätstyp (z.B. Attribute, Wertelisten, Hierarchien)
- **Status**: Fehlend, Automatisch, Geprüft

Klicken Sie auf einen Eintrag, um das **Detail-Panel** zu öffnen. Im Panel sehen Sie:

- Den Quelltext mit Quellsprache
- Alle vorhandenen Übersetzungen mit Status und Provider
- Die Verwendungsorte (welche Entitäten diesen Text verwenden)

#### Übersetzungen bearbeiten

Klicken Sie im Detail-Panel auf eine vorhandene Übersetzung oder auf **"+ Übersetzung hinzufügen"**, um den Text manuell zu bearbeiten. Manuell eingegebene Übersetzungen erhalten den Status **"Geprüft"** und werden nicht durch automatische Übersetzungen überschrieben.

### Tab: Fehlend

Zeigt alle Begriffe, für die in der gewählten Zielsprache noch keine Übersetzung vorhanden ist. Von hier aus können Sie gezielt fehlende Übersetzungen nachpflegen oder eine automatische Übersetzung anstoßen.

### Tab: Einstellungen

Über die Einstellungen können Sie zwei Aktionen manuell auslösen:

| Aktion | Beschreibung |
|---|---|
| **Ingest starten** | Überträgt alle Metadaten-Entitäten aus dem PIM an den TMS. Neue Begriffe werden automatisch zur Übersetzung weitergeleitet. |
| **Sync starten** | Holt alle verfügbaren Übersetzungen vom TMS und schreibt sie in die `name_json`-Felder der PIM-Datenbank zurück. |

Beide Aktionen laufen asynchron als Queue-Jobs im Hintergrund.

## Architektur

Das TMS ist als eigenständige Laravel-Applikation im Verzeichnis `/tms/` implementiert. Die Kommunikation zwischen PIM und TMS erfolgt über eine REST-API mit Shared-Key-Authentifizierung.

```
┌─────────────────────┐         ┌─────────────────────┐
│      anyPIM         │         │        TMS           │
│                     │         │                      │
│  IngestToTmsJob ────┼── POST ─┼─► IngestController   │
│                     │ /ingest │                      │
│                     │         │  ProcessIngestBatch   │
│                     │         │         │             │
│                     │         │         ▼             │
│                     │         │  TranslateUnitJob     │
│                     │         │    │   │   │          │
│                     │         │  DeepL Google Claude  │
│                     │         │         │             │
│                     │         │         ▼             │
│  SyncTmsTranslations│◄─ GET ──┼── ResolveController  │
│  Job                │ /resolve│   (Redis → DB)       │
│    │                │         │                      │
│    ▼                │         │                      │
│  name_json          │         │  tms_units           │
│  display_value_json │         │  tms_translations    │
│  abbreviation_json  │         │  tms_usages          │
└─────────────────────┘         └──────────────────────┘
```

### Datenfluss

1. **Ingest**: Der PIM-Job `IngestToTmsJob` sammelt alle Metadaten-Entitäten und sendet sie als Batch an den TMS-Endpunkt `POST /api/ingest`. Der TMS berechnet SHA-256-Hashes und legt neue Übersetzungseinheiten an.

2. **Übersetzung**: Für neue Einheiten wird automatisch ein `TranslateUnitJob` dispatched, der den Text über die konfigurierte Provider-Kette übersetzt (DeepL → Google → Claude).

3. **Resolve/Sync**: Der PIM-Job `SyncTmsTranslationsJob` fragt den TMS-Endpunkt `GET /api/resolve` ab. Dieser liefert Übersetzungen aus dem Redis-Cache (Fallback: Datenbank). Die Ergebnisse werden in die `name_json`-Felder der PIM-Entitäten geschrieben.

### Redis-Cache

Übersetzungen werden im Redis-Cache vorgehalten:

- **Key-Schema**: `tms:t:{text_hash}:{sprache}` (z.B. `tms:t:abc123:fr`)
- **TTL**: 24 Stunden (konfigurierbar)
- **Fallback**: Bei Cache-Miss wird automatisch aus der Datenbank nachgeladen

## MT-Provider (Machine Translation)

Das TMS unterstützt drei MT-Provider in konfigurierbarer Reihenfolge:

### DeepL (Standard)

- Höchste Übersetzungsqualität für europäische Sprachen
- Unterstützt 29 Sprachen (DE, EN, FR, ES, IT, NL, PL, PT, u.a.)
- Kosten: ca. 20 EUR pro 1 Mio. Zeichen
- Konfiguration: `DEEPL_API_KEY` und `DEEPL_API_URL`

### Google Translate (Fallback)

- Breite Sprachunterstützung (100+ Sprachen)
- Geeignet als Fallback für Sprachen, die DeepL nicht unterstützt
- Konfiguration: `GOOGLE_TRANSLATE_API_KEY`

### Claude / Anthropic (Optional)

- KI-basierte Übersetzung für schwierige Texte und Review
- Besonders geeignet für domänenspezifische Terminologie
- Nicht für Massen-Übersetzung gedacht
- Konfiguration: `ANTHROPIC_API_KEY`

### Provider-Kette

Die Reihenfolge der Provider wird über die Umgebungsvariable `TMS_PROVIDER_CHAIN` konfiguriert (Standard: `deepl,google`). Bei einem Fehler oder fehlender Sprachunterstützung wird automatisch der nächste Provider versucht.

## Konfiguration

Die TMS-Konfiguration erfolgt über Umgebungsvariablen im PIM:

| Variable | Beschreibung | Standard |
|---|---|---|
| `TMS_ENABLED` | TMS aktivieren/deaktivieren | `false` |
| `TMS_BASE_URL` | URL der TMS-Applikation | `http://localhost:8001/api` |
| `TMS_API_KEY` | Shared Secret für API-Authentifizierung | — |
| `TMS_TIMEOUT` | HTTP-Timeout in Sekunden | `2` |
| `TMS_TARGET_LANGUAGES` | Kommaseparierte Zielsprachen | `en,fr,es,it,nl` |

## Automatisierung

Das TMS ist in den Laravel Scheduler integriert:

| Zeitplan | Kommando | Beschreibung |
|---|---|---|
| Täglich | `tms:ingest` | Überträgt alle Metadaten-Entitäten an den TMS |
| Täglich 04:00 | `tms:sync` | Schreibt Übersetzungen in die PIM-Datenbank |

Beide Kommandos können auch manuell über die Artisan-CLI ausgeführt werden:

```bash
php artisan tms:ingest    # Manueller Ingest
php artisan tms:sync      # Manueller Sync
```

## Abgrenzung zu XLIFF-Übersetzungen

| Merkmal | TMS (Metadaten) | XLIFF (Produktdaten) |
|---|---|---|
| **Gegenstand** | Attributnamen, Wertelisten, Hierarchien | Produktattributwerte |
| **Menüpunkt** | Übersetzungen | Produkt-Detail → Übersetzungen |
| **Workflow** | Automatisch (MT) + manuelle Prüfung | Export → Übersetzen → Import |
| **Format** | JSON (name_json) | XLIFF 1.2 |
| **Automatisierung** | Scheduler + MT-Provider | Manueller Export/Import |

## Häufige Fragen

**Werden manuell eingegebene Übersetzungen durch automatische überschrieben?**
Nein. Übersetzungen mit Status "Geprüft" werden nie automatisch überschrieben. Nur Übersetzungen mit Status "Automatisch" können durch einen erneuten MT-Lauf aktualisiert werden.

**Was passiert, wenn der TMS nicht erreichbar ist?**
Das PIM funktioniert uneingeschränkt weiter (Graceful Degradation). Der TmsClient hat ein Timeout von 2 Sekunden und gibt bei Fehlern leere Ergebnisse zurück, ohne die PIM-Funktionalität zu beeinträchtigen.

**Wie kann ich eine neue Zielsprache hinzufügen?**
Ergänzen Sie die gewünschte Sprache in der Umgebungsvariable `TMS_TARGET_LANGUAGES` (z.B. `en,fr,es,it,nl,pl`) und führen Sie anschließend einen Ingest aus, damit die neuen Übersetzungen erstellt werden.
