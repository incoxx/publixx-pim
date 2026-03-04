---
title: Alleinstellungsmerkmale
---

# Alleinstellungsmerkmale des Publixx PIM

Publixx PIM unterscheidet sich durch eine Reihe architektonischer und funktionaler Entscheidungen grundlegend von herkoemmlichen PIM-Systemen. Dieses Kapitel stellt die zentralen Differenzierungsmerkmale vor und erlaeutert, warum sie fuer den produktiven Einsatz relevant sind.

## Uebersicht der Kernmerkmale

<svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:2rem auto;display:block;">
  <defs>
    <filter id="shadow" x="-5%" y="-5%" width="115%" height="115%">
      <feDropShadow dx="1" dy="2" stdDeviation="3" flood-opacity="0.15"/>
    </filter>
    <linearGradient id="hubGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#1d4ed8;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#60a5fa;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#3b82f6;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad2" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#34d399;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#059669;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad3" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#f59e0b;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#d97706;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad4" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#a78bfa;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#7c3aed;stop-opacity:1"/>
    </linearGradient>
    <linearGradient id="spokeGrad5" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#fb7185;stop-opacity:1"/>
      <stop offset="100%" style="stop-color:#e11d48;stop-opacity:1"/>
    </linearGradient>
  </defs>

  <!-- Connecting Lines -->
  <line x1="400" y1="280" x2="160" y2="100" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="640" y2="100" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="100" y2="310" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="700" y2="310" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="160" y2="500" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="400" y2="530" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>
  <line x1="400" y1="280" x2="640" y2="500" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,3"/>

  <!-- Hub -->
  <circle cx="400" cy="280" r="70" fill="url(#hubGrad)" filter="url(#shadow)"/>
  <text x="400" y="272" text-anchor="middle" fill="white" font-size="15" font-weight="bold">Publixx</text>
  <text x="400" y="294" text-anchor="middle" fill="white" font-size="15" font-weight="bold">PIM</text>

  <!-- Spoke 1: EAV -->
  <rect x="70" y="62" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad1)" filter="url(#shadow)"/>
  <text x="160" y="92" text-anchor="middle" fill="white" font-size="13" font-weight="bold">EAV-Architektur</text>
  <text x="160" y="112" text-anchor="middle" fill="white" font-size="11">Flexible Attribute</text>

  <!-- Spoke 2: Vererbung -->
  <rect x="550" y="62" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad2)" filter="url(#shadow)"/>
  <text x="640" y="92" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Vererbungssystem</text>
  <text x="640" y="112" text-anchor="middle" fill="white" font-size="11">Hierarchie + Varianten</text>

  <!-- Spoke 3: PQL -->
  <rect x="10" y="275" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad3)" filter="url(#shadow)"/>
  <text x="100" y="305" text-anchor="middle" fill="white" font-size="13" font-weight="bold">PQL-Abfragesprache</text>
  <text x="100" y="325" text-anchor="middle" fill="white" font-size="11">FUZZY, SOUNDS_LIKE</text>

  <!-- Spoke 4: Import/Export -->
  <rect x="610" y="275" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad4)" filter="url(#shadow)"/>
  <text x="700" y="305" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Import / Export</text>
  <text x="700" y="325" text-anchor="middle" fill="white" font-size="11">Excel + Publixx PXF</text>

  <!-- Spoke 5: RBAC -->
  <rect x="70" y="462" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad5)" filter="url(#shadow)"/>
  <text x="160" y="492" text-anchor="middle" fill="white" font-size="13" font-weight="bold">RBAC</text>
  <text x="160" y="512" text-anchor="middle" fill="white" font-size="11">Attribut- &amp; Knotenrechte</text>

  <!-- Spoke 6: i18n -->
  <rect x="310" y="495" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad1)" filter="url(#shadow)"/>
  <text x="400" y="525" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Mehrsprachigkeit</text>
  <text x="400" y="545" text-anchor="middle" fill="white" font-size="11">Unbegrenzte Sprachen</text>

  <!-- Spoke 7: Hierarchien -->
  <rect x="550" y="462" rx="12" ry="12" width="180" height="72" fill="url(#spokeGrad2)" filter="url(#shadow)"/>
  <text x="640" y="492" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Hierarchien</text>
  <text x="640" y="512" text-anchor="middle" fill="white" font-size="11">Master + Ausgabe</text>
</svg>

---

## 1. EAV-Architektur -- Unbegrenzt flexible Attribute

Herkoemmliche PIM-Systeme legen Produkteigenschaften als feste Spalten in einer Datenbanktabelle ab. Jedes neue Attribut erfordert dort eine Schemamigration, Deployment-Zyklen und unter Umstaenden Ausfallzeiten. Publixx PIM verfolgt einen grundlegend anderen Ansatz.

Das **Entity-Attribute-Value-Modell** (EAV) entkoppelt die Produktstruktur vom Datenbankschema. Attribute werden als eigenstaendige Entitaeten definiert und ihre Werte in einer separaten Zuordnungstabelle (`product_attribute_values`) gespeichert. Das bedeutet:

- **Neue Attribute** werden ueber die Oberflaeche oder API angelegt -- ohne Migration, ohne Deployment
- **Attributtypen** koennen frei definiert werden: Text, Zahl, Datum, Auswahl, Mehrfachauswahl, Medien, Referenzen und mehr
- **Keine Spaltenbegrenzung**: Waehrend relationale Tabellen bei einigen Hundert Spalten an Grenzen stossen, skaliert EAV auf tausende Attribute pro Produkt
- **Mandantenfaehigkeit**: Unterschiedliche Produktkategorien koennen voellig unterschiedliche Attributsets verwenden, ohne sich gegenseitig zu beeinflussen

```
Produkt (Entity)    Attribut (Attribute)      Wert (Value)
───────────────     ──────────────────────    ─────────────────
Artikel-4711   ──>  Farbe (select)       ──>  "Blau"
Artikel-4711   ──>  Gewicht (decimal)    ──>  2.45
Artikel-4711   ──>  Beschreibung (text)  ──>  "Ergonomisch..."
```

Die Performance-Herausforderung, die EAV-Systeme typischerweise mit sich bringen (viele JOINs bei Abfragen), wird durch einen **materialisierten Suchindex** (`products_search_index`) kompensiert. Dieser denormalisiert die relevanten Attributwerte in eine flache Struktur, die fuer Volltextsuche und Filterung optimiert ist.

---

## 2. Zweistufiges Vererbungssystem

Publixx PIM implementiert Vererbung auf zwei Ebenen, die sich gegenseitig ergaenzen:

### Hierarchie-Vererbung (Knoten zu Produkt)

Produkte sind in Hierarchieknoten einsortiert. Jeder Knoten kann Attribute zuweisen, die seine Produkte erhalten. Da Knoten in einer Baumstruktur organisiert sind, erben Kindknoten die Attributzuweisungen ihrer Vorfahren. Ein Produkt, das in einem Blattknoten liegt, erhaelt automatisch die Attribute aller uebergeordneten Knoten.

### Varianten-Vererbung (Elternprodukt zu Variante)

Produktvarianten erben Attributwerte von ihrem Elternprodukt. Pro Attribut ist konfigurierbar, ob ein Wert geerbt (`inherit`) oder eigenstaendig ueberschrieben (`override`) werden soll. Aendert sich ein geerbter Wert am Elternprodukt, propagiert die Aenderung automatisch an alle Varianten.

Die **Aufloesungsreihenfolge** ist klar definiert:

1. Eigener Wert der Variante (falls override aktiv)
2. Geerbter Wert vom Elternprodukt
3. Standardwert aus der Hierarchie
4. `null` (kein Wert vorhanden)

Dieses System reduziert Redundanz drastisch: Gemeinsame Attribute wie Markenname, Hersteller oder Materialbeschreibungen werden nur einmal gepflegt und automatisch an hunderte Varianten vererbt.

---

## 3. PQL -- Publixx Query Language

PQL ist eine eigenstaendige, SQL-aehnliche Abfragesprache, die speziell fuer die Suche und Filterung von Produkten ueber beliebige Attributkombinationen entwickelt wurde. Sie geht ueber die Moeglichkeiten typischer Filterinterfaces hinaus:

| Operator | Beschreibung | Beispiel |
|---|---|---|
| `=`, `!=`, `<`, `>` | Standardvergleiche | `preis > 100` |
| `LIKE` | Mustersuche mit Platzhaltern | `name LIKE "%Schraube%"` |
| `IN` | Wertliste | `farbe IN ("Rot", "Blau")` |
| `FUZZY` | Unscharfe Suche (Levenshtein-Distanz) | `name FUZZY "Schraub"` |
| `SOUNDS_LIKE` | Phonetische Suche | `hersteller SOUNDS_LIKE "Mayer"` |
| `SEARCH_FIELDS` | Volltextsuche ueber mehrere Felder | `SEARCH_FIELDS("Ventil DN50")` |
| `AND`, `OR`, `NOT` | Logische Verknuepfungen | `farbe = "Rot" AND preis < 50` |

PQL-Abfragen werden serverseitig in optimierte SQL-Queries uebersetzt und profitieren dabei vom materialisierten Suchindex. Die Sprache ist sowohl ueber die REST-API als auch ueber die Oberflaeche nutzbar.

```
-- Finde alle roten Schrauben unter 5 Euro mit unscharfem Namensabgleich
name FUZZY "Schraube" AND farbe = "Rot" AND preis < 5.00
```

---

## 4. Excel-Import mit 14-Tab-Struktur

Der Import-Prozess ist auf maximale Benutzerfreundlichkeit bei gleichzeitiger Datenintegritaet ausgelegt. Eine einzelne Excel-Datei mit **14 spezialisierten Tabellenblättern** bildet das gesamte Produktdatenmodell ab:

**Dreiphasiger Import:**

1. **Upload und Parsing** -- Die Datei wird hochgeladen, jedes Tabellenblatt wird strukturiert eingelesen
2. **Validierung und Fuzzy-Matching** -- Attributnamen, Hierarchie-Pfade und Referenzen werden gegen den Bestand abgeglichen. Tippfehler werden ueber Fuzzy-Matching erkannt und als Korrekturvorschlaege praesentiert
3. **Ausfuehrung** -- Nach Bestaetigung durch den Benutzer werden die Daten transaktional importiert

Das **Fuzzy-Matching** bei der Zuordnung von Spaltennamen zu Attributen ist besonders wertvoll: Statt bei einem Tippfehler wie "Gewciht" statt "Gewicht" den gesamten Import abzubrechen, erkennt das System die Aehnlichkeit und schlaegt die korrekte Zuordnung vor.

---

## 5. Konfigurierbarer Export mit Publixx-Integration

Das Export-System arbeitet mit **konfigurierbaren Mapping-Templates** (PXF-Format). Jedes Template definiert:

- Welche Attribute exportiert werden
- Wie Attributnamen im Zielformat heissen sollen (Mapping)
- Welche Transformationen angewendet werden
- Fuer welchen Kanal (z.B. Publixx-Katalog, Webshop, Marktplatz) der Export bestimmt ist

Die **Publixx-Katalog-Integration** ermoeglicht den direkten Export von Produktdaten in das Publixx-Katalogsystem. Aenderungen an Produkten koennen automatisch oder manuell an den Katalog uebermittelt werden.

---

## 6. Feingranulares Berechtigungssystem (RBAC)

Das Berechtigungssystem geht weit ueber die ueblichen Rollen wie "Admin", "Editor" und "Viewer" hinaus. Aufbauend auf Spatie Permission implementiert Publixx PIM **zwei zusaetzliche Granularitaetsebenen**:

### Attribut-View-Einschraenkungen

Benutzerrollen koennen auf bestimmte **Attribut-Views** beschraenkt werden. Eine Attribut-View definiert eine Teilmenge aller verfuegbaren Attribute. Ein Benutzer mit eingeschraenkter View sieht nur die ihm zugewiesenen Attribute -- alle anderen sind weder sichtbar noch ueber die API abrufbar.

### Hierarchieknoten-Einschraenkungen

Benutzer koennen auf bestimmte **Teilbaeume der Hierarchie** eingeschraenkt werden. Ein Benutzer, der nur Zugriff auf den Knoten "Elektronik" hat, sieht ausschliesslich Produkte in diesem Teilbaum und dessen Kindknoten.

Beide Einschraenkungen wirken kumulativ: Ein Benutzer kann beispielsweise nur die Marketing-Attribute von Produkten im Bereich Elektronik sehen und bearbeiten.

---

## 7. Integrierte Mehrsprachigkeit

Mehrsprachigkeit ist kein Aufsatz, sondern integraler Bestandteil der EAV-Architektur. Jeder Attributwert kann in **beliebig vielen Sprachen** gepflegt werden. Die Sprachversion wird direkt in der Wert-Tabelle gespeichert, sodass keine zusaetzlichen Uebersetzungstabellen noetig sind.

- Sprachen werden systemweit definiert und stehen sofort fuer alle Attribute zur Verfuegung
- Die API unterstuetzt sprachspezifische Abfragen (`?locale=de`, `?locale=en`)
- Im Frontend wechselt der Benutzer die Bearbeitungssprache per Dropdown
- PQL-Abfragen koennen sprachspezifisch filtern

---

## 8. Hierarchische Produktklassifizierung

Das Hierarchiesystem unterscheidet zwei Typen:

### Master-Hierarchie

Die primaere Produktstruktur. Jedes Produkt ist genau einem Knoten in der Master-Hierarchie zugeordnet. Die Master-Hierarchie bestimmt, welche Attribute ein Produkt besitzt (ueber Hierarchie-Vererbung).

### Ausgabe-Hierarchien

Zusaetzliche Hierarchien fuer kanalspezifische Strukturen. Ein Produkt kann in mehreren Ausgabe-Hierarchien gleichzeitig referenziert werden, beispielsweise in einer Webshop-Kategorie und einer Katalog-Struktur.

Diese Trennung ermoeglicht es, die interne Produktorganisation (nach Warengruppen, Materialien, Herstellern) von der externen Darstellung (nach Kundenbeduerfnissen, Anwendungsfaellen, Kanaelen) zu entkoppeln.

---

## 9. Collection-Attribute (Wiederholbare Attributgruppen)

Collection-Attribute ermoeglichen die Abbildung von **wiederholt strukturierten Daten** innerhalb eines Produkts. Ein klassisches Beispiel:

- Ein Produkt hat eine Collection "Zertifizierungen" mit den Attributen "Norm", "Nummer", "Gueltig bis" und "Dokument"
- Pro Produkt koennen beliebig viele Eintraege in dieser Collection existieren

Collections unterstuetzen:
- **Sortierung** ueber `collection_sort` (Reihenfolge der Eintraege) und `attribute_sort` (Reihenfolge der Attribute innerhalb einer Collection) -- jeweils in 10er-Schritten
- **Vererbung**: Collection-Eintraege koennen ebenfalls von Elternprodukten geerbt werden
- **Validierung**: Jedes Attribut innerhalb der Collection wird einzeln validiert

---

## 10. Materialisierter Suchindex

Der `products_search_index` ist eine denormalisierte Tabelle, die die wichtigsten Produktdaten in einer flachen, suchoptimierten Struktur zusammenfasst. Dieser Index wird automatisch aktualisiert, wenn sich Produktdaten aendern, und bietet:

- **MySQL FULLTEXT-Indizes** fuer performante Freitextsuche
- **Voraggregierte Attributwerte** -- keine EAV-JOINs bei Leseoperationen noetig
- **Unterstuetzung fuer PQL** -- die PQL-Engine nutzt den Index als primaere Datenquelle
- **Automatische Invalidierung** -- Aenderungen an Produkten, Attributen oder Vererbungswerten loesen eine Neuberechnung des betroffenen Index-Eintrags aus

Durch diese Strategie erreicht Publixx PIM die Flexibilitaet eines EAV-Systems bei gleichzeitiger Abfrageperformance, die mit fest definierten Schemata vergleichbar ist.
