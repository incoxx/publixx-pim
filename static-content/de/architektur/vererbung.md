---
title: Vererbungssystem
---

# Vererbungssystem

Das Vererbungssystem ist eines der zentralen Architekturelemente von Publixx PIM. Es reduziert Redundanz, gewaehrleistet Konsistenz und ermoeglicht die effiziente Pflege grosser Produktsortimente mit gemeinsamen Eigenschaften. Das System arbeitet auf zwei unabhaengigen Ebenen, die sich gegenseitig ergaenzen.

## Uebersicht der Vererbungstypen

| Merkmal | Hierarchie-Vererbung | Varianten-Vererbung |
|---|---|---|
| **Richtung** | Hierarchieknoten zu Produkt | Elternprodukt zu Variante |
| **Gegenstand** | Attribut-Zuweisungen (welche Attribute ein Produkt hat) | Attribut-Werte (welche Werte eine Variante traegt) |
| **Steuerung** | Automatisch ueber Knotenposition im Baum | Pro Attribut konfigurierbar (inherit/override) |
| **Propagation** | Aenderung am Knoten betrifft alle Kindknoten und deren Produkte | Aenderung am Elternprodukt betrifft alle erbenden Varianten |

---

## Hierarchie-Vererbung

Die Hierarchie-Vererbung bestimmt, **welche Attribute** ein Produkt besitzt. Sie operiert auf der Ebene der Attribut-Zuweisungen, nicht auf der Ebene der Werte.

### Mechanismus

Hierarchieknoten sind in einer Baumstruktur organisiert. Jedem Knoten koennen Attribute zugewiesen werden. Ein Produkt, das einem Knoten zugeordnet ist, erhaelt automatisch alle Attribute dieses Knotens sowie alle Attribute der uebergeordneten Knoten bis zur Wurzel.

<svg viewBox="0 0 820 520" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:2rem auto;display:block;">
  <defs>
    <filter id="inhShadow" x="-3%" y="-3%" width="108%" height="112%">
      <feDropShadow dx="1" dy="2" stdDeviation="2" flood-opacity="0.1"/>
    </filter>
    <marker id="inhArrow" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
      <polygon points="0 0, 10 3.5, 0 7" fill="#64748b"/>
    </marker>
    <marker id="inhArrowGreen" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
      <polygon points="0 0, 10 3.5, 0 7" fill="#16a34a"/>
    </marker>
  </defs>

  <!-- Title -->
  <text x="410" y="28" text-anchor="middle" fill="#1e293b" font-size="15" font-weight="bold">Hierarchie-Vererbung: Attributfluss von Knoten zu Produkten</text>

  <!-- Root Node -->
  <rect x="300" y="50" width="220" height="55" rx="10" fill="#1e40af" filter="url(#inhShadow)"/>
  <text x="410" y="73" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Wurzel: Alle Produkte</text>
  <text x="410" y="92" text-anchor="middle" fill="#bfdbfe" font-size="11">Attribute: Name, SKU, Status</text>

  <!-- Level 1 Nodes -->
  <rect x="80" y="160" width="220" height="55" rx="10" fill="#2563eb" filter="url(#inhShadow)"/>
  <text x="190" y="183" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Elektronik</text>
  <text x="190" y="202" text-anchor="middle" fill="#bfdbfe" font-size="11">+ Spannung, Leistung</text>

  <rect x="520" y="160" width="220" height="55" rx="10" fill="#2563eb" filter="url(#inhShadow)"/>
  <text x="630" y="183" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Bekleidung</text>
  <text x="630" y="202" text-anchor="middle" fill="#bfdbfe" font-size="11">+ Groesse, Material, Farbe</text>

  <!-- Level 2 Nodes -->
  <rect x="30" y="275" width="200" height="55" rx="10" fill="#3b82f6" filter="url(#inhShadow)"/>
  <text x="130" y="298" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Kabel</text>
  <text x="130" y="317" text-anchor="middle" fill="#bfdbfe" font-size="11">+ Laenge, Steckertyp</text>

  <rect x="250" y="275" width="200" height="55" rx="10" fill="#3b82f6" filter="url(#inhShadow)"/>
  <text x="350" y="298" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Displays</text>
  <text x="350" y="317" text-anchor="middle" fill="#bfdbfe" font-size="11">+ Aufloesung, Diagonale</text>

  <!-- Products -->
  <rect x="20" y="400" width="180" height="100" rx="8" fill="#f0fdf4" stroke="#16a34a" stroke-width="2" filter="url(#inhShadow)"/>
  <text x="110" y="422" text-anchor="middle" fill="#166534" font-size="12" font-weight="bold">USB-C Kabel 2m</text>
  <text x="30" y="442" fill="#166534" font-size="10">Erbt von Wurzel:</text>
  <text x="35" y="455" fill="#15803d" font-size="10" font-style="italic">Name, SKU, Status</text>
  <text x="30" y="470" fill="#166534" font-size="10">Erbt von Elektronik:</text>
  <text x="35" y="483" fill="#15803d" font-size="10" font-style="italic">Spannung, Leistung</text>
  <text x="30" y="496" fill="#166534" font-size="10">Erbt von Kabel:</text>
  <text x="35" y="509" fill="#15803d" font-size="10" font-style="italic">Laenge, Steckertyp</text>

  <rect x="620" y="400" width="180" height="80" rx="8" fill="#f0fdf4" stroke="#16a34a" stroke-width="2" filter="url(#inhShadow)"/>
  <text x="710" y="422" text-anchor="middle" fill="#166534" font-size="12" font-weight="bold">T-Shirt Classic</text>
  <text x="630" y="442" fill="#166534" font-size="10">Erbt von Wurzel:</text>
  <text x="635" y="455" fill="#15803d" font-size="10" font-style="italic">Name, SKU, Status</text>
  <text x="630" y="470" fill="#166534" font-size="10">Erbt von Bekleidung:</text>
  <text x="635" y="483" fill="#15803d" font-size="10" font-style="italic">Groesse, Material, Farbe</text>

  <!-- Connecting Lines (Node tree) -->
  <line x1="360" y1="105" x2="240" y2="158" stroke="#64748b" stroke-width="2" marker-end="url(#inhArrow)"/>
  <line x1="460" y1="105" x2="580" y2="158" stroke="#64748b" stroke-width="2" marker-end="url(#inhArrow)"/>
  <line x1="160" y1="215" x2="140" y2="273" stroke="#64748b" stroke-width="2" marker-end="url(#inhArrow)"/>
  <line x1="220" y1="215" x2="330" y2="273" stroke="#64748b" stroke-width="2" marker-end="url(#inhArrow)"/>

  <!-- Connecting Lines (Node to Product - green dashed) -->
  <line x1="110" y1="330" x2="110" y2="398" stroke="#16a34a" stroke-width="2" stroke-dasharray="6,3" marker-end="url(#inhArrowGreen)"/>
  <line x1="660" y1="215" x2="700" y2="398" stroke="#16a34a" stroke-width="2" stroke-dasharray="6,3" marker-end="url(#inhArrowGreen)"/>

  <!-- Legend -->
  <rect x="560" y="285" width="220" height="60" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
  <line x1="575" y1="308" x2="615" y2="308" stroke="#64748b" stroke-width="2"/>
  <text x="625" y="312" fill="#475569" font-size="10">Knoten-Hierarchie</text>
  <line x1="575" y1="330" x2="615" y2="330" stroke="#16a34a" stroke-width="2" stroke-dasharray="6,3"/>
  <text x="625" y="334" fill="#475569" font-size="10">Attribut-Vererbung</text>
</svg>

### Das `dont_inherit`-Flag

In manchen Faellen soll ein Attribut nur fuer Produkte eines bestimmten Knotens gelten, nicht aber fuer Produkte in Kindknoten. Dafuer existiert das `dont_inherit`-Flag in der Tabelle `hierarchy_node_attribute`.

| Szenario | `dont_inherit` | Verhalten |
|---|---|---|
| Attribut soll an Kindknoten weitergegeben werden | `false` (Standard) | Alle Kindknoten und deren Produkte erben das Attribut |
| Attribut gilt nur fuer diesen Knoten | `true` | Nur Produkte dieses Knotens erhalten das Attribut, Kindknoten nicht |

**Anwendungsbeispiel:** Der Knoten "Sonderposten" hat ein Attribut "Abverkaufspreis" mit `dont_inherit = true`. Nur direkt in "Sonderposten" eingeordnete Produkte erhalten dieses Attribut. Produkte in Unterknoten von "Sonderposten" (z.B. "Sonderposten > Elektronik") erhalten es nicht.

---

## Varianten-Vererbung

Die Varianten-Vererbung bestimmt, **welche Werte** eine Produktvariante traegt. Sie operiert auf der Ebene der konkreten Attributwerte.

### Mechanismus

Ein Elternprodukt kann beliebig viele Varianten besitzen. Fuer jede Kombination aus Variante und Attribut wird eine **Vererbungsregel** definiert:

- **`inherit`**: Die Variante uebernimmt den Wert des Elternprodukts. Der Wert ist in der Variante schreibgeschuetzt und wird bei Aenderung am Elternprodukt automatisch aktualisiert.
- **`override`**: Die Variante hat einen eigenstaendigen Wert, der unabhaengig vom Elternprodukt gepflegt wird.

<svg viewBox="0 0 820 480" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:2rem auto;display:block;">
  <defs>
    <filter id="varShadow" x="-3%" y="-3%" width="108%" height="112%">
      <feDropShadow dx="1" dy="2" stdDeviation="2" flood-opacity="0.1"/>
    </filter>
    <marker id="varArrowBlue" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
      <polygon points="0 0, 10 3.5, 0 7" fill="#2563eb"/>
    </marker>
    <marker id="varArrowOrange" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
      <polygon points="0 0, 10 3.5, 0 7" fill="#ea580c"/>
    </marker>
  </defs>

  <!-- Title -->
  <text x="410" y="28" text-anchor="middle" fill="#1e293b" font-size="15" font-weight="bold">Varianten-Vererbung: Elternprodukt zu Varianten</text>

  <!-- Parent Product -->
  <rect x="250" y="50" width="320" height="170" rx="10" fill="white" stroke="#2563eb" stroke-width="2" filter="url(#varShadow)"/>
  <rect x="250" y="50" width="320" height="38" rx="10" fill="#2563eb"/>
  <rect x="250" y="80" width="320" height="8" fill="#2563eb"/>
  <text x="410" y="75" text-anchor="middle" fill="white" font-size="14" font-weight="bold">Elternprodukt: T-Shirt Classic</text>

  <text x="270" y="110" fill="#1e293b" font-size="12" font-family="monospace">Marke      = "FashionBrand"</text>
  <text x="270" y="130" fill="#1e293b" font-size="12" font-family="monospace">Material   = "100% Baumwolle"</text>
  <text x="270" y="150" fill="#1e293b" font-size="12" font-family="monospace">Pflegehinw = "30 Grad waschen"</text>
  <text x="270" y="170" fill="#1e293b" font-size="12" font-family="monospace">Farbe      = "Weiss"</text>
  <text x="270" y="190" fill="#1e293b" font-size="12" font-family="monospace">Groesse    = "M"</text>
  <text x="270" y="210" fill="#1e293b" font-size="12" font-family="monospace">Preis      = 29.90</text>

  <!-- Variant 1 -->
  <rect x="30" y="300" width="320" height="170" rx="10" fill="white" stroke="#16a34a" stroke-width="2" filter="url(#varShadow)"/>
  <rect x="30" y="300" width="320" height="38" rx="10" fill="#16a34a"/>
  <rect x="30" y="330" width="320" height="8" fill="#16a34a"/>
  <text x="190" y="325" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Variante: T-Shirt Rot/L</text>

  <text x="50" y="358" fill="#2563eb" font-size="12" font-family="monospace">Marke      = "FashionBrand"</text>
  <rect x="280" y="346" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="309" y="359" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="50" y="378" fill="#2563eb" font-size="12" font-family="monospace">Material   = "100% Baumwolle"</text>
  <rect x="280" y="366" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="309" y="379" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="50" y="398" fill="#2563eb" font-size="12" font-family="monospace">Pflegehinw = "30 Grad waschen"</text>
  <rect x="280" y="386" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="309" y="399" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="50" y="418" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Farbe      = "Rot"</text>
  <rect x="272" y="406" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="307" y="419" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <text x="50" y="438" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Groesse    = "L"</text>
  <rect x="272" y="426" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="307" y="439" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <text x="50" y="458" fill="#2563eb" font-size="12" font-family="monospace">Preis      = 29.90</text>
  <rect x="280" y="446" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="309" y="459" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <!-- Variant 2 -->
  <rect x="470" y="300" width="320" height="170" rx="10" fill="white" stroke="#16a34a" stroke-width="2" filter="url(#varShadow)"/>
  <rect x="470" y="300" width="320" height="38" rx="10" fill="#16a34a"/>
  <rect x="470" y="330" width="320" height="8" fill="#16a34a"/>
  <text x="630" y="325" text-anchor="middle" fill="white" font-size="13" font-weight="bold">Variante: T-Shirt Blau/S</text>

  <text x="490" y="358" fill="#2563eb" font-size="12" font-family="monospace">Marke      = "FashionBrand"</text>
  <rect x="720" y="346" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="749" y="359" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="490" y="378" fill="#2563eb" font-size="12" font-family="monospace">Material   = "100% Baumwolle"</text>
  <rect x="720" y="366" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="749" y="379" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="490" y="398" fill="#2563eb" font-size="12" font-family="monospace">Pflegehinw = "30 Grad waschen"</text>
  <rect x="720" y="386" width="58" height="18" rx="4" fill="#dbeafe"/>
  <text x="749" y="399" text-anchor="middle" fill="#1e40af" font-size="9" font-weight="bold">INHERIT</text>

  <text x="490" y="418" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Farbe      = "Blau"</text>
  <rect x="712" y="406" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="747" y="419" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <text x="490" y="438" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Groesse    = "S"</text>
  <rect x="712" y="426" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="747" y="439" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <text x="490" y="458" fill="#ea580c" font-size="12" font-family="monospace" font-weight="bold">Preis      = 24.90</text>
  <rect x="712" y="446" width="70" height="18" rx="4" fill="#fed7aa"/>
  <text x="747" y="459" text-anchor="middle" fill="#9a3412" font-size="9" font-weight="bold">OVERRIDE</text>

  <!-- Arrows from Parent to Variants -->
  <line x1="340" y1="220" x2="200" y2="298" stroke="#2563eb" stroke-width="2" stroke-dasharray="6,3" marker-end="url(#varArrowBlue)"/>
  <line x1="480" y1="220" x2="620" y2="298" stroke="#2563eb" stroke-width="2" stroke-dasharray="6,3" marker-end="url(#varArrowBlue)"/>

  <!-- Legend -->
  <rect x="30" y="48" width="200" height="55" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>
  <rect x="42" y="60" width="40" height="14" rx="3" fill="#dbeafe"/>
  <text x="62" y="71" text-anchor="middle" fill="#1e40af" font-size="8" font-weight="bold">INHERIT</text>
  <text x="92" y="71" fill="#475569" font-size="10">Wert vom Eltern</text>
  <rect x="42" y="80" width="46" height="14" rx="3" fill="#fed7aa"/>
  <text x="65" y="91" text-anchor="middle" fill="#9a3412" font-size="8" font-weight="bold">OVERRIDE</text>
  <text x="98" y="91" fill="#475569" font-size="10">Eigener Wert</text>
</svg>

### Aufloesungsreihenfolge

Wenn ein Attributwert fuer ein Produkt (oder eine Variante) angefragt wird, durchlaeuft der `InheritanceService` die folgende Kaskade:

```
1. Eigener Wert des Produkts/der Variante vorhanden?
   └─ JA  ──> Wert zurueckgeben (Herkunft: "own")
   └─ NEIN ──> weiter zu Schritt 2

2. Ist das Produkt eine Variante UND Vererbungsregel = "inherit"?
   └─ JA  ──> Wert des Elternprodukts zurueckgeben (Herkunft: "parent")
   └─ NEIN ──> weiter zu Schritt 3

3. Existiert ein Standardwert aus der Hierarchie?
   └─ JA  ──> Standardwert zurueckgeben (Herkunft: "hierarchy")
   └─ NEIN ──> null zurueckgeben (Herkunft: "none")
```

Die **Herkunftsinformation** wird zusammen mit dem Wert zurueckgegeben und im Frontend genutzt, um geerbte Felder visuell zu kennzeichnen.

---

## Collection-Sortierung

Collections (wiederholbare Attributgruppen) verwenden ein zweistufiges Sortierungsmodell:

### `collection_sort` -- Reihenfolge der Eintraege

Bestimmt die Reihenfolge der einzelnen Eintraege innerhalb einer Collection-Instanz. Werte werden in **Zehnerschritten** vergeben (10, 20, 30, ...), um nachtraegliches Einfuegen ohne Neunummerierung zu ermoeglichen.

| Eintrag | `collection_sort` |
|---|---|
| Zertifizierung ISO 9001 | 10 |
| Zertifizierung CE | 20 |
| Zertifizierung TUeV | 30 |

### `attribute_sort` -- Reihenfolge der Attribute

Bestimmt die Reihenfolge der Attribute innerhalb eines Collection-Eintrags. Ebenfalls in Zehnerschritten.

| Attribut | `attribute_sort` |
|---|---|
| Norm | 10 |
| Nummer | 20 |
| Gueltig bis | 30 |
| Dokument | 40 |

Die Zehnerschritte ermoeglichen es, ein neues Attribut mit `attribute_sort = 15` zwischen "Norm" und "Nummer" einzufuegen, ohne bestehende Sortierungen anpassen zu muessen.

---

## Cache-Invalidierung bei Vererbungsaenderungen

Vererbungsaenderungen haben potenziell weitreichende Auswirkungen auf den Cache. Das System behandelt verschiedene Szenarien:

### Szenario 1: Wertaenderung am Elternprodukt

```
Elternprodukt "T-Shirt Classic" aendert Material von "Baumwolle" zu "Bio-Baumwolle"
  |
  ├─> Cache fuer "T-Shirt Classic" invalidieren
  ├─> Alle Varianten mit inherit-Regel fuer "Material" ermitteln
  │     ├─> Variante "Rot/L" erbt Material ──> Cache invalidieren
  │     ├─> Variante "Blau/S" erbt Material ──> Cache invalidieren
  │     └─> Variante "Schwarz/XL" hat override ──> kein Handlungsbedarf
  ├─> Suchindex fuer betroffene Produkte aktualisieren (async)
  └─> ProductValueChanged-Event ausloesen
```

### Szenario 2: Verschiebung eines Hierarchieknotens

```
Knoten "Displays" wird von "Elektronik" nach "Bueroausstattung" verschoben
  |
  ├─> Bisherige Attribute ermitteln (Elektronik: Spannung, Leistung)
  ├─> Neue Attribute ermitteln (Bueroausstattung: Gewicht, Abmessungen)
  ├─> Fuer alle Produkte im Knoten "Displays":
  │     ├─> Spannung, Leistung entfernen (falls keine eigenen Werte)
  │     ├─> Gewicht, Abmessungen hinzufuegen
  │     └─> Cache und Suchindex aktualisieren
  └─> HierarchyNodeMoved-Event ausloesen
```

### Szenario 3: Aenderung einer Vererbungsregel

```
Variante "Rot/L" aendert Regel fuer "Preis" von "inherit" zu "override"
  |
  ├─> Eigener Preis der Variante wird editierbar
  ├─> Cache fuer Variante "Rot/L" invalidieren
  ├─> Suchindex fuer "Rot/L" aktualisieren (async)
  └─> VariantInheritanceChanged-Event ausloesen
```

---

## UI-Darstellung

Die Vererbung ist im Frontend transparent visualisiert, damit Benutzer jederzeit nachvollziehen koennen, woher ein Wert stammt.

### Geerbte Felder

Felder mit geerbten Werten werden als **schreibgeschuetzt** dargestellt und mit einem Badge versehen, das die Herkunft angibt:

| Badge | Bedeutung | Darstellung |
|---|---|---|
| `Geerbt vom Elternprodukt` | Wert stammt aus Varianten-Vererbung | Feld ausgegraut, blauer Badge |
| `Hierarchie-Standard` | Wert stammt aus der Hierarchie-Zuweisung | Feld ausgegraut, gruener Badge |
| `Eigener Wert` | Wert wurde direkt am Produkt gesetzt | Feld editierbar, kein Badge |

### Umschalten der Vererbungsregel

Bei Varianten kann der Benutzer pro Attribut zwischen `inherit` und `override` umschalten:

- **Wechsel zu override**: Das Feld wird editierbar. Der bisherige geerbte Wert wird als Startwert eingetragen, kann aber frei geaendert werden.
- **Wechsel zu inherit**: Das Feld wird schreibgeschuetzt. Ein eventuell vorhandener eigener Wert wird verworfen und durch den Elternwert ersetzt. Das System zeigt eine Warnmeldung, bevor ein eigener Wert durch die Umschaltung ueberschrieben wird.

### Aenderungspropagation in Echtzeit

Wenn ein Benutzer einen Wert am Elternprodukt aendert und gleichzeitig eine Variante geoeffnet hat, wird die Aenderung in der Varianten-Ansicht visuell hervorgehoben, damit der Benutzer die Propagation nachvollziehen kann.
