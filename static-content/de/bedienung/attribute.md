---
title: Attribute
---

# Attribute

Attribute bilden das Datenmodell des anyPIM. Dank der EAV-Architektur (Entity-Attribute-Value) können Sie beliebig viele Attribute definieren, ohne das Datenbankschema ändern zu müssen. Dieses Kapitel beschreibt die Verwaltung von Attributen, deren Datentypen, Eigenschaften sowie die zugehörigen Konfigurationsbereiche für Attributgruppen, Wertelisten, Einheiten und Attributansichten.

## Attributverwaltung im Überblick

Die Attributverwaltung erreichen Sie über den Menüpunkt **Attribute** in der Sidebar. Dort sehen Sie eine tabellarische Liste aller definierten Attribute mit folgenden Spalten:

| Spalte | Beschreibung |
|---|---|
| **Technischer Name** | Eindeutiger Bezeichner im System (snake_case) |
| **Anzeigename** | Lesbare Bezeichnung (DE/EN) |
| **Datentyp** | Art des gespeicherten Werts |
| **Übersetzbar** | Ob der Wert pro Sprache gepflegt wird |
| **Pflicht** | Ob das Attribut ausgefüllt sein muss |

Über die Schaltfläche **+ Neues Attribut** öffnen Sie das Formular-Panel (AttributeFormPanel) zur Neuanlage.

## Datentypen

Das anyPIM unterstützt acht Datentypen, die das Eingabefeld und die Validierung bestimmen:

<svg viewBox="0 0 800 440" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:1.5rem 0;">
  <defs>
    <style>
      .dt-bg { fill: #f8fafc; stroke: #e2e8f0; stroke-width: 2; rx: 12; }
      .dt-card { fill: #ffffff; stroke: #e2e8f0; stroke-width: 1.5; rx: 8; }
      .dt-card:hover { stroke: #6366f1; }
      .dt-icon-bg { rx: 6; }
      .dt-title { font-family: system-ui, sans-serif; font-size: 12px; font-weight: 700; fill: #1e293b; }
      .dt-desc { font-family: system-ui, sans-serif; font-size: 10px; fill: #64748b; }
      .dt-icon { font-family: system-ui, sans-serif; font-size: 18px; fill: #ffffff; font-weight: 700; }
      .dt-heading { font-family: system-ui, sans-serif; font-size: 16px; font-weight: 700; fill: #1e293b; }
    </style>
  </defs>
  <rect class="dt-bg" x="0" y="0" width="800" height="440" />
  <text class="dt-heading" x="24" y="32">Attribut-Datentypen</text>

  <!-- Row 1 -->
  <!-- String -->
  <rect class="dt-card" x="16" y="52" width="182" height="130" />
  <rect class="dt-icon-bg" x="32" y="68" width="36" height="36" fill="#6366f1" rx="8" />
  <text class="dt-icon" x="41" y="93">Aa</text>
  <text class="dt-title" x="32" y="124">String</text>
  <text class="dt-desc" x="32" y="140">Einzeiliger oder mehrzeiliger</text>
  <text class="dt-desc" x="32" y="154">Text. Für Namen, Beschrei-</text>
  <text class="dt-desc" x="32" y="168">bungen und Freitextfelder.</text>

  <!-- Number -->
  <rect class="dt-card" x="210" y="52" width="182" height="130" />
  <rect class="dt-icon-bg" x="226" y="68" width="36" height="36" fill="#0ea5e9" rx="8" />
  <text class="dt-icon" x="233" y="93">123</text>
  <text class="dt-title" x="226" y="124">Number</text>
  <text class="dt-desc" x="226" y="140">Ganzzahlen ohne Dezimal-</text>
  <text class="dt-desc" x="226" y="154">stellen. Für Mengen, Stück-</text>
  <text class="dt-desc" x="226" y="168">zahlen und Ganzzahlwerte.</text>

  <!-- Float -->
  <rect class="dt-card" x="404" y="52" width="182" height="130" />
  <rect class="dt-icon-bg" x="420" y="68" width="36" height="36" fill="#14b8a6" rx="8" />
  <text class="dt-icon" x="427" y="93">1.5</text>
  <text class="dt-title" x="420" y="124">Float</text>
  <text class="dt-desc" x="420" y="140">Fließkommazahlen mit optio-</text>
  <text class="dt-desc" x="420" y="154">naler Einheit. Für Gewicht,</text>
  <text class="dt-desc" x="420" y="168">Maße und Preise.</text>

  <!-- Date -->
  <rect class="dt-card" x="598" y="52" width="182" height="130" />
  <rect class="dt-icon-bg" x="614" y="68" width="36" height="36" fill="#f59e0b" rx="8" />
  <text class="dt-icon" x="619" y="92">📅</text>
  <text class="dt-title" x="614" y="124">Date</text>
  <text class="dt-desc" x="614" y="140">Datum im Format YYYY-MM-DD.</text>
  <text class="dt-desc" x="614" y="154">Für Erscheinungsdaten,</text>
  <text class="dt-desc" x="614" y="168">Gültigkeiten und Fristen.</text>

  <!-- Row 2 -->
  <!-- Flag -->
  <rect class="dt-card" x="16" y="198" width="182" height="130" />
  <rect class="dt-icon-bg" x="32" y="214" width="36" height="36" fill="#22c55e" rx="8" />
  <text class="dt-icon" x="41" y="239">✓</text>
  <text class="dt-title" x="32" y="270">Flag</text>
  <text class="dt-desc" x="32" y="286">Ja/Nein-Wert (Boolean).</text>
  <text class="dt-desc" x="32" y="300">Für Schalter wie „Verfügbar",</text>
  <text class="dt-desc" x="32" y="314">„Hervorgehoben" etc.</text>

  <!-- Selection -->
  <rect class="dt-card" x="210" y="198" width="182" height="130" />
  <rect class="dt-icon-bg" x="226" y="214" width="36" height="36" fill="#a855f7" rx="8" />
  <text class="dt-icon" x="233" y="238">▼</text>
  <text class="dt-title" x="226" y="270">Selection</text>
  <text class="dt-desc" x="226" y="286">Auswahl aus einer Werteliste.</text>
  <text class="dt-desc" x="226" y="300">Für vordefinierte Optionen</text>
  <text class="dt-desc" x="226" y="314">wie Farbe, Material etc.</text>

  <!-- Dictionary -->
  <rect class="dt-card" x="404" y="198" width="182" height="130" />
  <rect class="dt-icon-bg" x="420" y="214" width="36" height="36" fill="#ec4899" rx="8" />
  <text class="dt-icon" x="426" y="238">{..}</text>
  <text class="dt-title" x="420" y="270">Dictionary</text>
  <text class="dt-desc" x="420" y="286">Schlüssel-Wert-Paare als</text>
  <text class="dt-desc" x="420" y="300">JSON-Struktur. Für flexible</text>
  <text class="dt-desc" x="420" y="314">Zusatzdaten und Mappings.</text>

  <!-- Collection -->
  <rect class="dt-card" x="598" y="198" width="182" height="130" />
  <rect class="dt-icon-bg" x="614" y="214" width="36" height="36" fill="#f43f5e" rx="8" />
  <text class="dt-icon" x="621" y="238">[..]</text>
  <text class="dt-title" x="614" y="270">Collection</text>
  <text class="dt-desc" x="614" y="286">Strukturierte Sammlungen</text>
  <text class="dt-desc" x="614" y="300">als JSON-Array. Für wieder-</text>
  <text class="dt-desc" x="614" y="314">holbare Datenblöcke.</text>

  <!-- Legend -->
  <rect fill="#f1f5f9" x="16" y="350" width="764" height="76" rx="8" />
  <text class="dt-title" x="32" y="374">Hinweis zur Eingabe</text>
  <text class="dt-desc" x="32" y="392">String, Number, Float und Date werden als native HTML-Eingabefelder dargestellt. Flag als Checkbox.</text>
  <text class="dt-desc" x="32" y="408">Selection zeigt ein Dropdown mit Werten aus der verknüpften Werteliste. Dictionary und Collection nutzen einen JSON-Editor.</text>
</svg>

### Detaillierte Typbeschreibung

#### String
Für einzeilige und mehrzeilige Texte. Ideal für Produktnamen, Beschreibungen, Kurztexte und technische Bezeichnungen. String-Attribute können als **übersetzbar** markiert werden, sodass pro Sprache ein eigener Text gepflegt wird.

#### Number
Speichert Ganzzahlen ohne Dezimalstellen. Geeignet für Mengenangaben, Stückzahlen, Bestandswerte und ganzzahlige Kennwerte.

#### Float
Fließkommazahlen mit konfigurierbarer Genauigkeit. Kann mit einer **Einheit** aus einer Einheitengruppe verknüpft werden (z.B. Gewicht in kg, Länge in mm). Die Einheit wird neben dem Eingabefeld angezeigt.

#### Date
Datumswerte im ISO-Format (YYYY-MM-DD). Das Eingabefeld zeigt ein Kalender-Widget. Typische Anwendungsfälle: Erscheinungsdatum, Gültigkeitsbeginn, Verfallsdatum.

#### Flag
Boolescher Wert (Ja/Nein), dargestellt als Checkbox. Für binäre Eigenschaften wie „Verfügbar", „Hervorgehoben", „Gefahrgut" oder „Neuheit".

#### Selection
Auswahl eines einzelnen Werts aus einer verknüpften **Werteliste**. Das Eingabefeld zeigt ein Dropdown-Menü. Die verfügbaren Optionen werden in der Werteliste zentral gepflegt und können übersetzt werden.

#### Dictionary
Strukturierte Schlüssel-Wert-Paare als JSON-Objekt. Das System stellt einen JSON-Editor bereit. Anwendungsbeispiele: Technische Datenblätter mit variablen Feldern, Key-Value-Mappings für externe Systeme.

#### Collection
JSON-Arrays mit wiederholbaren Strukturen. Ermöglicht das Speichern von Listen gleichartiger Datensätze innerhalb eines einzelnen Attributs. Anwendungsbeispiel: Mehrere Zertifizierungseinträge mit jeweils Name, Nummer und Gültigkeitsdatum.

## Attributeigenschaften

Beim Anlegen oder Bearbeiten eines Attributs können folgende Eigenschaften konfiguriert werden:

| Eigenschaft | Beschreibung |
|---|---|
| **Technischer Name** | Eindeutiger Systembezeichner in snake_case (z.B. `product_name`). Kann nach Anlage nicht geändert werden. |
| **Anzeigename (DE/EN)** | Menschenlesbarer Name in Deutsch und Englisch |
| **Datentyp** | Einer der acht unterstützten Typen (siehe oben) |
| **Übersetzbar** (`is_translatable`) | Wenn aktiviert, kann der Attributwert pro Sprache separat gepflegt werden |
| **Pflichtfeld** (`is_mandatory`) | Wenn aktiviert, muss das Attribut ausgefüllt sein, bevor ein Produkt auf „Aktiv" gesetzt werden kann |
| **Eindeutig** (`is_unique`) | Der Wert muss systemweit eindeutig sein (z.B. für EAN-Nummern) |
| **Durchsuchbar** (`is_searchable`) | Das Attribut wird in der Volltextsuche und der PQL-Abfrage berücksichtigt |
| **Vererbbar** (`is_inheritable`) | Varianten können den Wert vom Elternprodukt erben |
| **Variantenattribut** (`is_variant_attribute`) | Markiert das Attribut als variantenspezifisch (unterscheidet Varianten voneinander) |
| **Wiederholbar** (`is_repeatable`) | Erlaubt mehrere Werte für das gleiche Attribut (Collection Groups) |
| **Werteliste** | Verknüpfung mit einer Werteliste (nur bei Selection-Typ) |
| **Einheitengruppe** | Verknüpfung mit einer Einheitengruppe (typisch bei Float-Typ) |

::: tip Best Practice
Legen Sie den technischen Namen mit Bedacht fest -- er dient als API-Schlüssel und kann nachträglich nicht mehr geändert werden. Verwenden Sie beschreibende, englische snake_case-Bezeichner (z.B. `net_weight`, `short_description`).
:::

## Attributgruppen (AttributeTypes)

Attributgruppen -- im System als **AttributeTypes** bezeichnet -- dienen der logischen Organisation von Attributen. Sie gruppieren thematisch zusammengehörige Attribute und bestimmen deren Reihenfolge in der Produktdetailansicht.

### Verwaltung

Über den Menüpunkt **Attributgruppen** in der Sidebar erreichen Sie die Gruppenübersicht. Dort können Sie:

- **Neue Gruppe anlegen** -- Vergeben Sie einen technischen Namen sowie Anzeigenamen in DE und EN.
- **Attribute zuordnen** -- Weisen Sie bestehende Attribute der Gruppe zu und legen Sie die Reihenfolge fest.
- **Gruppen bearbeiten** -- Ändern Sie den Anzeigenamen oder die Reihenfolge der enthaltenen Attribute.
- **Gruppen löschen** -- Entfernt die Gruppe. Die enthaltenen Attribute bleiben erhalten, verlieren aber ihre Gruppenzuordnung.

### Beispielgruppen

| Gruppe | Enthaltene Attribute |
|---|---|
| Stammdaten | Produktname, Beschreibung, Kurzbeschreibung, EAN |
| Technische Daten | Gewicht, Abmessungen, Material, Schutzklasse |
| Logistik | Verpackungseinheit, Kartoninhalt, Zolltarifnummer |
| Marketing | Werbeslogan, Bullet Points, SEO-Titel |

## Einheitengruppen und Einheiten

Einheitengruppen fassen physikalische Einheiten einer Kategorie zusammen. Sie werden Attributen vom Typ **Float** zugeordnet, sodass der Benutzer beim Befüllen die passende Einheit auswählen kann.

### Einheitengruppe anlegen

1. Navigieren Sie zu **Attribute** > **Einheitengruppen** (bzw. über die Attributverwaltung).
2. Klicken Sie auf **+ Neue Einheitengruppe**.
3. Vergeben Sie einen Namen (z.B. „Gewicht").
4. Fügen Sie Einheiten hinzu.

### Beispiel: Einheitengruppe „Gewicht"

| Einheit | Kürzel | Faktor zur Basiseinheit |
|---|---|---|
| Kilogramm | kg | 1 (Basis) |
| Gramm | g | 0,001 |
| Pfund (lb) | lb | 0,453592 |
| Tonne | t | 1000 |

### Beispiel: Einheitengruppe „Länge"

| Einheit | Kürzel | Faktor zur Basiseinheit |
|---|---|---|
| Meter | m | 1 (Basis) |
| Zentimeter | cm | 0,01 |
| Millimeter | mm | 0,001 |
| Zoll (inch) | in | 0,0254 |

Einheiten ermöglichen die automatische Umrechnung bei der Datenpflege und im Export.

## Wertelisten (Value Lists)

Wertelisten definieren die verfügbaren Optionen für Attribute vom Typ **Selection**. Über den Menüpunkt **Wertelisten** in der Sidebar erreichen Sie die Verwaltung.

### Werteliste anlegen

1. Klicken Sie auf **+ Neue Werteliste**.
2. Vergeben Sie einen technischen Namen und Anzeigenamen.
3. Fügen Sie Werte hinzu -- jeder Wert hat:
   - **Technischer Schlüssel** (z.B. `color_red`)
   - **Anzeigename DE** (z.B. „Rot")
   - **Anzeigename EN** (z.B. „Red")
   - **Position** (Reihenfolge in der Auswahlliste)

### Beispiel: Werteliste „Farben"

| Schlüssel | Anzeige (DE) | Anzeige (EN) |
|---|---|---|
| `color_red` | Rot | Red |
| `color_blue` | Blau | Blue |
| `color_green` | Grün | Green |
| `color_black` | Schwarz | Black |
| `color_white` | Weiß | White |

::: warning Hinweis
Das Löschen eines Werts aus einer Werteliste kann dazu führen, dass Produkte einen ungültigen Wert enthalten. Prüfen Sie vor dem Löschen, welche Produkte den betroffenen Wert verwenden.
:::

## Attributansichten (Attribute Views)

Attributansichten definieren **Teilmengen** der verfügbaren Attribute für bestimmte Nutzungskontexte. Sie ermöglichen es, die Darstellung und den Zugriff auf Attribute nach Verwendungszweck einzuschränken.

### Typische Anwendungsfälle

| Ansicht | Beschreibung |
|---|---|
| **E-Shop** | Nur Attribute, die für den Online-Shop relevant sind (Name, Beschreibung, Bilder, Preis) |
| **Print** | Attribute für den Printkatalog (technische Daten, Artikelnummer, Maße) |
| **Logistik** | Logistikrelevante Attribute (Gewicht, Verpackung, Zolltarifnummer) |
| **Minimal** | Nur die wichtigsten Kernattribute für eine Schnellbearbeitung |

### Ansicht konfigurieren

1. Navigieren Sie zur Attributansichten-Verwaltung.
2. Erstellen Sie eine neue Ansicht mit einem aussagekräftigen Namen.
3. Wählen Sie die Attribute aus, die in dieser Ansicht sichtbar sein sollen.
4. Definieren Sie optional die Reihenfolge.

Attributansichten können auch als **Berechtigungsgrenze** in der Benutzerverwaltung verwendet werden. Ein Benutzer kann auf bestimmte Ansichten eingeschränkt werden, sodass er nur die dort definierten Attribute sehen und bearbeiten darf. Weitere Details finden Sie im Abschnitt [Benutzer](./benutzer).

## Eltern-Kind-Attribute (Hierarchische Attribute)

Attribute können in einer Eltern-Kind-Beziehung zueinander stehen. Ein **Elternattribut** dient als logischer Container für seine **Kindattribute**, die erst dann angezeigt werden, wenn das Elternattribut einen bestimmten Wert hat.

### Beispiel

- **Elternattribut:** `has_battery` (Flag)
- **Kindattribute:** `battery_type` (Selection), `battery_capacity_mah` (Number)

Wenn `has_battery` auf „Ja" gesetzt wird, werden die Kindattribute `battery_type` und `battery_capacity_mah` in der Produktdetailansicht eingeblendet. Andernfalls bleiben sie ausgeblendet.

Dieses Konzept reduziert die Komplexität der Produktpflege, indem nur kontextrelevante Attribute angezeigt werden.

## Nächste Schritte

- Erfahren Sie, wie [Hierarchien](./hierarchien) Attributgruppen an Produktkategorien zuweisen.
- Lernen Sie die [Produktverwaltung](./produkte) kennen, in der Attributwerte gepflegt werden.
- Konfigurieren Sie [Benutzerberechtigungen](./benutzer) auf Basis von Attributansichten.
