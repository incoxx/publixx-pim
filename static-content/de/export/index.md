---
title: Export - Übersicht
---

# Export

Das Export-Modul des Publixx PIM stellt Produktdaten in strukturierter Form für externe Systeme bereit. Es unterstützt sowohl generische JSON-Exporte als auch die spezialisierte Publixx-Integration über PXF-Datensätze (Publixx Exchange Format).

## Exportformate

Das Publixx PIM bietet zwei Export-Kanäle:

| Kanal | Format | Zielgruppe | Beschreibung |
|---|---|---|---|
| **JSON-Export** | JSON | Entwickler, Systeme | Generischer Export mit konfigurierbaren Filtern und Formaten |
| **Publixx-Export** | PXF-JSON | Publixx-Plattform | Spezialisierter Export mit Mapping-Konfiguration für Publixx-Kataloge |

## Export-Pipeline

Der Exportprozess folgt einer klar definierten Pipeline:

<svg viewBox="0 0 900 380" xmlns="http://www.w3.org/2000/svg" style="max-width: 100%; height: auto; margin: 2rem 0;">
  <defs>
    <marker id="arrow-export" viewBox="0 0 10 7" refX="10" refY="3.5" markerWidth="10" markerHeight="7" orient="auto-start-reverse">
      <path d="M 0 0 L 10 3.5 L 0 7 z" fill="#0891b2"/>
    </marker>
    <filter id="shadow-export" x="-5%" y="-5%" width="115%" height="115%">
      <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.1"/>
    </filter>
  </defs>

  <!-- Step 1: Anfrage -->
  <rect x="20" y="30" width="160" height="130" rx="12" fill="#ecfeff" stroke="#0891b2" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="20" y="30" width="160" height="40" rx="12" fill="#0891b2"/>
  <rect x="20" y="58" width="160" height="12" fill="#0891b2"/>
  <text x="100" y="56" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Anfrage</text>
  <text x="100" y="95" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">API-Endpoint oder</text>
  <text x="100" y="112" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">PQL-Query mit</text>
  <text x="100" y="129" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Filtern und Optionen</text>

  <!-- Arrow 1→2 -->
  <line x1="185" y1="95" x2="215" y2="95" stroke="#0891b2" stroke-width="2" marker-end="url(#arrow-export)"/>

  <!-- Step 2: Filterung -->
  <rect x="225" y="30" width="160" height="130" rx="12" fill="#ecfeff" stroke="#0891b2" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="225" y="30" width="160" height="40" rx="12" fill="#0891b2"/>
  <rect x="225" y="58" width="160" height="12" fill="#0891b2"/>
  <text x="305" y="56" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Filterung</text>
  <text x="305" y="95" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Status, Hierarchie,</text>
  <text x="305" y="112" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Attribute, Delta-</text>
  <text x="305" y="129" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Zeitstempel</text>

  <!-- Arrow 2→3 -->
  <line x1="390" y1="95" x2="420" y2="95" stroke="#0891b2" stroke-width="2" marker-end="url(#arrow-export)"/>

  <!-- Step 3: Datenanreicherung -->
  <rect x="430" y="30" width="160" height="130" rx="12" fill="#ecfeff" stroke="#0891b2" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="430" y="30" width="160" height="40" rx="12" fill="#0891b2"/>
  <rect x="430" y="58" width="160" height="12" fill="#0891b2"/>
  <text x="510" y="56" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Anreicherung</text>
  <text x="510" y="95" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Attributwerte, Medien,</text>
  <text x="510" y="112" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Preise, Relationen,</text>
  <text x="510" y="129" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Varianten laden</text>

  <!-- Arrow 3→4 -->
  <line x1="595" y1="95" x2="625" y2="95" stroke="#0891b2" stroke-width="2" marker-end="url(#arrow-export)"/>

  <!-- Step 4: Transformation -->
  <rect x="635" y="30" width="160" height="130" rx="12" fill="#ecfeff" stroke="#0891b2" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="635" y="30" width="160" height="40" rx="12" fill="#0891b2"/>
  <rect x="635" y="58" width="160" height="12" fill="#0891b2"/>
  <text x="715" y="56" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Transformation</text>
  <text x="715" y="95" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Mapping anwenden,</text>
  <text x="715" y="112" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">Format wählen</text>
  <text x="715" y="129" text-anchor="middle" fill="#164e63" font-size="11" font-family="system-ui, sans-serif">(flat/nested/publixx)</text>

  <!-- Output branches -->
  <!-- Arrow down from Transform -->
  <line x1="715" y1="165" x2="715" y2="200" stroke="#0891b2" stroke-width="2" marker-end="url(#arrow-export)"/>

  <!-- Step 5: Ausgabe -->
  <rect x="430" y="210" width="160" height="110" rx="12" fill="#f0fdf4" stroke="#16a34a" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="430" y="210" width="160" height="40" rx="12" fill="#16a34a"/>
  <rect x="430" y="238" width="160" height="12" fill="#16a34a"/>
  <text x="510" y="236" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">JSON-Ausgabe</text>
  <text x="510" y="275" text-anchor="middle" fill="#14532d" font-size="11" font-family="system-ui, sans-serif">Generischer Export</text>
  <text x="510" y="295" text-anchor="middle" fill="#14532d" font-size="11" font-family="system-ui, sans-serif">für externe Systeme</text>

  <rect x="635" y="210" width="160" height="110" rx="12" fill="#fefce8" stroke="#eab308" stroke-width="2" filter="url(#shadow-export)"/>
  <rect x="635" y="210" width="160" height="40" rx="12" fill="#eab308"/>
  <rect x="635" y="238" width="160" height="12" fill="#eab308"/>
  <text x="715" y="236" text-anchor="middle" fill="white" font-size="14" font-weight="bold" font-family="system-ui, sans-serif">Publixx PXF</text>
  <text x="715" y="275" text-anchor="middle" fill="#713f12" font-size="11" font-family="system-ui, sans-serif">Publixx-Datensätze</text>
  <text x="715" y="295" text-anchor="middle" fill="#713f12" font-size="11" font-family="system-ui, sans-serif">für Kataloge</text>

  <!-- Connecting lines to outputs -->
  <line x1="715" y1="200" x2="510" y2="210" stroke="#16a34a" stroke-width="2"/>
  <line x1="715" y1="200" x2="715" y2="210" stroke="#eab308" stroke-width="2"/>
</svg>

### 1. Anfrage

Der Export wird über einen API-Endpunkt oder eine PQL-Abfrage ausgelöst. Der Aufrufer definiert dabei Filter, Include-Optionen und das gewünschte Ausgabeformat.

### 2. Filterung

Die Produktmenge wird anhand der angegebenen Kriterien eingeschränkt:

- **Status** -- Nur Produkte mit bestimmtem Status (z. B. `active`)
- **Hierarchie** -- Produkte eines bestimmten Hierarchieknotens oder -pfads
- **Attribute** -- Filterung über Attributwerte
- **Attributansichten** -- Einschränkung auf bestimmte Views
- **Ausgabehierarchie** -- Strukturierung nach einer Output-Hierarchie
- **Delta-Zeitstempel** -- Nur seit einem bestimmten Zeitpunkt geänderte Produkte (`updated_after`)

### 3. Datenanreicherung

Die gefilterten Produkte werden mit den angeforderten Zusatzdaten angereichert:

- Attributwerte (mit Vererbungsauflösung bei Varianten)
- Medien (Bilder, Dokumente, Videos)
- Preise (nach Währung und Gültigkeit)
- Relationen (Zubehör, Ersatzteile, Querverweise)
- Varianten (mit eigenen Attributwerten)

### 4. Transformation

Die angereicherten Daten werden in das gewünschte Format transformiert:

| Format | Beschreibung |
|---|---|
| `flat` | Flache Struktur mit allen Attributen als Schlüssel-Wert-Paare |
| `nested` | Verschachtelte Struktur, gruppiert nach Attributgruppen |
| `publixx` | Publixx-spezifisches Format mit Mapping-Transformation |

## Delta-Export

Der Delta-Export ermöglicht effiziente inkrementelle Synchronisationen. Über den Parameter `updated_after` werden nur Produkte exportiert, die seit dem angegebenen Zeitpunkt geändert wurden:

```
GET /api/v1/export/products?updated_after=2025-06-15T10:00:00Z
```

Der Zeitstempel berücksichtigt Änderungen an:

- Produkt-Stammdaten (Name, SKU, Status)
- Attributwerten
- Medienzuordnungen
- Preisen
- Varianten und deren Attributwerten
- Relationen

## Weiterführende Dokumentation

- [JSON-Export](/de/export/json-export) -- Endpunkte, Filter, Formate und Paginierung
- [Publixx-Export](/de/export/publixx-export) -- Mapping-Konfiguration und PXF-Integration
