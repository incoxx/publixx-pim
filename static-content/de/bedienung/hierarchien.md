---
title: Hierarchien
---

# Hierarchien

Hierarchien sind ein zentrales Organisationsprinzip im Publixx PIM. Sie strukturieren Produkte in Baumstrukturen und steuern, welche Attribute den Produkten in einer bestimmten Kategorie zur Verfügung stehen. Das System unterscheidet zwei Hierarchiearten: **Master-Hierarchien** für die interne Produktklassifizierung und **Ausgabe-Hierarchien** für die Exportstruktur.

## Hierarchietypen

### Master-Hierarchien

Master-Hierarchien bilden die **primäre Produktklassifizierung** und dienen folgenden Zwecken:

- **Produktzuordnung** -- Jedes Produkt wird genau einem Knoten in der Master-Hierarchie zugeordnet. Diese Zuordnung bestimmt die Produktkategorie.
- **Attributvererbung** -- Den Knoten einer Master-Hierarchie werden Attributgruppen (mit Collection Groups) zugeordnet. Produkte erben die Attribute ihres Knotens sowie aller übergeordneten Knoten.
- **Strukturelle Klassifizierung** -- Die Master-Hierarchie definiert die Warengruppen und Produktkategorien Ihres Sortiments.

::: info Beispiel
Wenn dem Knoten „Elektronik" die Attributgruppe „Technische Daten" zugeordnet wird und dem Unterknoten „Smartphones" die Gruppe „Display-Eigenschaften", dann erhält ein Produkt in „Smartphones" sowohl die Attribute aus „Technische Daten" als auch aus „Display-Eigenschaften".
:::

### Ausgabe-Hierarchien

Ausgabe-Hierarchien definieren die **Exportstruktur** und dienen der Abbildung von Katalogstrukturen, Shop-Kategorien oder anderen externen Ordnungssystemen:

- **Mehrfachzuordnung** -- Ein Produkt kann in mehreren Ausgabe-Hierarchien und an mehreren Stellen innerhalb einer Ausgabe-Hierarchie erscheinen.
- **Exportsteuerung** -- Die Struktur der Ausgabe-Hierarchie wird beim Export als Kategoriebaum verwendet.
- **Unabhängig von Attributen** -- Ausgabe-Hierarchien haben keinen Einfluss auf die verfügbaren Attribute eines Produkts.

## Beispielhafte Hierarchiestruktur

<svg viewBox="0 0 800 520" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:1.5rem 0;">
  <defs>
    <style>
      .h-bg { fill: #f8fafc; stroke: #e2e8f0; stroke-width: 2; rx: 12; }
      .h-node { fill: #ffffff; stroke: #6366f1; stroke-width: 1.5; rx: 6; }
      .h-node-root { fill: #6366f1; stroke: none; rx: 6; }
      .h-node-l1 { fill: #ffffff; stroke: #6366f1; stroke-width: 2; rx: 6; }
      .h-node-l2 { fill: #ffffff; stroke: #818cf8; stroke-width: 1.5; rx: 6; }
      .h-node-l3 { fill: #ffffff; stroke: #a5b4fc; stroke-width: 1; rx: 6; }
      .h-text-root { fill: #ffffff; font-family: system-ui, sans-serif; font-size: 13px; font-weight: 700; }
      .h-text-l1 { fill: #1e293b; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .h-text-l2 { fill: #334155; font-family: system-ui, sans-serif; font-size: 11px; font-weight: 500; }
      .h-text-l3 { fill: #475569; font-family: system-ui, sans-serif; font-size: 11px; }
      .h-line { stroke: #cbd5e1; stroke-width: 1.5; fill: none; }
      .h-badge { fill: #dbeafe; rx: 8; }
      .h-badge-text { fill: #1e40af; font-family: system-ui, sans-serif; font-size: 9px; font-weight: 600; }
      .h-label { fill: #94a3b8; font-family: system-ui, sans-serif; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
      .h-heading { fill: #1e293b; font-family: system-ui, sans-serif; font-size: 15px; font-weight: 700; }
    </style>
  </defs>
  <rect class="h-bg" x="0" y="0" width="800" height="520" />

  <!-- Title -->
  <text class="h-heading" x="24" y="30">Master-Hierarchie: Produktkatalog (Beispiel)</text>
  <text class="h-label" x="24" y="48">Bis zu 6 Ebenen tief. Attribute werden von oben nach unten vererbt.</text>

  <!-- Root node -->
  <rect class="h-node-root" x="320" y="68" width="160" height="36" />
  <text class="h-text-root" x="348" y="91">Alle Produkte</text>

  <!-- Lines from root to level 1 -->
  <path class="h-line" d="M 400 104 L 400 120 L 150 120 L 150 140" />
  <path class="h-line" d="M 400 104 L 400 140" />
  <path class="h-line" d="M 400 104 L 400 120 L 650 120 L 650 140" />

  <!-- Level 1 nodes -->
  <rect class="h-node-l1" x="70" y="140" width="160" height="36" />
  <text class="h-text-l1" x="108" y="163">Elektronik</text>
  <rect class="h-badge" x="178" y="146" width="42" height="18" />
  <text class="h-badge-text" x="186" y="159">12 Attr</text>

  <rect class="h-node-l1" x="320" y="140" width="160" height="36" />
  <text class="h-text-l1" x="347" y="163">Haushalt</text>
  <rect class="h-badge" x="418" y="146" width="42" height="18" />
  <text class="h-badge-text" x="426" y="159">8 Attr</text>

  <rect class="h-node-l1" x="570" y="140" width="160" height="36" />
  <text class="h-text-l1" x="598" y="163">Bekleidung</text>
  <rect class="h-badge" x="680" y="146" width="42" height="18" />
  <text class="h-badge-text" x="688" y="159">6 Attr</text>

  <!-- Lines from Elektronik to level 2 -->
  <path class="h-line" d="M 150 176 L 150 192 L 80 192 L 80 210" />
  <path class="h-line" d="M 150 176 L 150 192 L 230 192 L 230 210" />

  <!-- Level 2 under Elektronik -->
  <rect class="h-node-l2" x="16" y="210" width="130" height="32" />
  <text class="h-text-l2" x="32" y="231">Smartphones</text>

  <rect class="h-node-l2" x="168" y="210" width="130" height="32" />
  <text class="h-text-l2" x="192" y="231">Laptops</text>

  <!-- Lines from Smartphones to level 3 -->
  <path class="h-line" d="M 80 242 L 80 256 L 44 256 L 44 272" />
  <path class="h-line" d="M 80 242 L 80 256 L 130 256 L 130 272" />

  <!-- Level 3 under Smartphones -->
  <rect class="h-node-l3" x="4" y="272" width="85" height="28" />
  <text class="h-text-l3" x="16" y="290">Android</text>

  <rect class="h-node-l3" x="100" y="272" width="85" height="28" />
  <text class="h-text-l3" x="120" y="290">iOS</text>

  <!-- Lines from Laptops to level 3 -->
  <path class="h-line" d="M 230 242 L 230 256 L 195 256 L 195 272" />
  <path class="h-line" d="M 230 242 L 230 256 L 275 256 L 275 272" />

  <rect class="h-node-l3" x="150" y="272" width="95" height="28" />
  <text class="h-text-l3" x="162" y="290">Notebooks</text>

  <rect class="h-node-l3" x="256" y="272" width="95" height="28" />
  <text class="h-text-l3" x="268" y="290">Ultrabooks</text>

  <!-- Lines from Haushalt to level 2 -->
  <path class="h-line" d="M 400 176 L 400 192 L 355 192 L 355 210" />
  <path class="h-line" d="M 400 176 L 400 192 L 455 192 L 455 210" />

  <!-- Level 2 under Haushalt -->
  <rect class="h-node-l2" x="290" y="210" width="130" height="32" />
  <text class="h-text-l2" x="304" y="231">Küchengeräte</text>

  <rect class="h-node-l2" x="438" y="210" width="130" height="32" />
  <text class="h-text-l2" x="468" y="231">Möbel</text>

  <!-- Lines from Bekleidung to level 2 -->
  <path class="h-line" d="M 650 176 L 650 192 L 600 192 L 600 210" />
  <path class="h-line" d="M 650 176 L 650 192 L 710 192 L 710 210" />

  <!-- Level 2 under Bekleidung -->
  <rect class="h-node-l2" x="538" y="210" width="130" height="32" />
  <text class="h-text-l2" x="564" y="231">Oberbekleidung</text>

  <rect class="h-node-l2" x="680" y="210" width="100" height="32" />
  <text class="h-text-l2" x="700" y="231">Schuhe</text>

  <!-- Legend -->
  <rect fill="#f1f5f9" x="16" y="330" width="768" height="176" rx="8" />
  <text class="h-heading" x="32" y="356">Legende</text>

  <rect class="h-node-root" x="32" y="370" width="18" height="18" />
  <text class="h-text-l2" x="60" y="384">Wurzelknoten (Ebene 0)</text>

  <rect class="h-node-l1" x="32" y="396" width="18" height="18" />
  <text class="h-text-l2" x="60" y="410">Ebene 1 -- Hauptkategorien</text>

  <rect class="h-node-l2" x="32" y="422" width="18" height="18" />
  <text class="h-text-l2" x="60" y="436">Ebene 2 -- Unterkategorien</text>

  <rect class="h-node-l3" x="32" y="448" width="18" height="18" />
  <text class="h-text-l2" x="60" y="462">Ebene 3+ -- Feinstruktur (bis Ebene 6)</text>

  <rect class="h-badge" x="32" y="476" width="42" height="16" />
  <text class="h-badge-text" x="40" y="488">N Attr</text>
  <text class="h-text-l2" x="84" y="489">Anzahl der direkt zugeordneten Attributgruppen</text>
</svg>

## Baumstruktur und Navigation

Die Hierarchieverwaltung erreichen Sie über den Menüpunkt **Hierarchien** in der Sidebar. Die Ansicht zeigt den Hierarchiebaum als interaktive Baumkomponente (PimTree).

### Baumansicht

- **Auf-/Zuklappen** -- Klicken Sie auf den Pfeil neben einem Knoten, um seine Kindknoten ein- oder auszublenden.
- **Knotenauswahl** -- Klicken Sie auf den Knotennamen, um dessen Details im rechten Bereich zu laden.
- **Kontextmenü** -- Ein Rechtsklick oder das Drei-Punkte-Menü am Knoten bietet Aktionen wie Umbenennen, Verschieben und Löschen.

### Drag-and-Drop

Knoten können per Drag-and-Drop innerhalb der Hierarchie verschoben werden:

1. Klicken und halten Sie einen Knoten.
2. Ziehen Sie ihn auf den gewünschten Zielknoten.
3. Der Knoten wird als Kindknoten des Zielknotens eingefügt.
4. Alternativ können Sie den Knoten zwischen zwei Geschwisterknoten platzieren, um ihn auf derselben Ebene umzusortieren.

::: warning Hinweis
Beim Verschieben eines Knotens werden alle Kindknoten mitbewegt. Die Attributzuordnungen bleiben erhalten, aber die vererbten Attribute der Produkte können sich ändern, da sie nun von einem anderen Elternpfad erben.
:::

## Knotenverwaltung

### Knoten erstellen

1. Wählen Sie den Elternknoten, unter dem der neue Knoten angelegt werden soll.
2. Klicken Sie auf **+ Neuer Knoten** oder nutzen Sie das Kontextmenü.
3. Geben Sie im Panel (HierarchyNodeFormPanel) den **Namen (DE/EN)** des Knotens ein.
4. Speichern Sie. Der Knoten erscheint als letzter Kindknoten des gewählten Elternknotens.

### Knoten umbenennen

Öffnen Sie das Bearbeitungspanel des Knotens und ändern Sie den Anzeigenamen. Der Name kann in Deutsch und Englisch gepflegt werden.

### Knoten verschieben

Neben dem Drag-and-Drop können Sie Knoten auch über das Bearbeitungspanel verschieben, indem Sie einen neuen Elternknoten auswählen.

### Knoten löschen

Beim Löschen eines Knotens haben Sie zwei Optionen:

| Option | Verhalten |
|---|---|
| **Nur diesen Knoten** | Der Knoten wird entfernt. Kindknoten werden eine Ebene nach oben verschoben. |
| **Mit allen Kindknoten** | Der gesamte Teilbaum wird gelöscht. |

::: danger Achtung
Produkte, die einem gelöschten Knoten zugeordnet sind, verlieren ihre Hierarchiezuordnung und damit möglicherweise Attributdefinitionen. Prüfen Sie vor dem Löschen, welche Produkte betroffen sind.
:::

## Attribute an Hierarchieknoten zuordnen

Ein zentrales Feature der Master-Hierarchien ist die **Attributzuordnung an Knoten**. Diese Zuordnung bestimmt, welche Attribute für Produkte in einer bestimmten Kategorie verfügbar sind.

### Zuordnung erstellen

1. Wählen Sie einen Knoten in der Master-Hierarchie.
2. Öffnen Sie den Bereich **Attributzuordnungen**.
3. Klicken Sie auf **+ Attributgruppe zuordnen**.
4. Wählen Sie eine Attributgruppe (AttributeType) aus.
5. Optional: Definieren Sie eine **Collection Group**, wenn die Attribute als wiederholbarer Block angelegt werden sollen.

### Vererbung entlang des Pfades

Attribute werden entlang des Hierarchiepfades vererbt. Ein Produkt im Knoten „Smartphones > Android" erhält:

1. Attribute des Knotens **Alle Produkte** (Wurzel)
2. Attribute des Knotens **Elektronik**
3. Attribute des Knotens **Smartphones**
4. Attribute des Knotens **Android**

Die Attribute aller übergeordneten Knoten werden kumulativ an die Produkte weitergegeben. Es gibt keine Möglichkeit, geerbte Attribute auf einer tieferen Ebene zu entfernen -- sie können aber optional oder versteckt geschaltet werden.

### Collection Groups

Collection Groups ermöglichen es, eine Attributgruppe als **wiederholbaren Block** zuzuordnen. Beispiel:

- Die Attributgruppe „Zertifizierung" enthält die Attribute `cert_name`, `cert_number`, `cert_valid_until`.
- Wird sie als Collection Group zugeordnet, kann der Benutzer beim Produkt beliebig viele Zertifizierungseinträge anlegen.

## Produkte an Hierarchieknoten zuordnen

### Master-Hierarchie

In der Master-Hierarchie wird jedes Produkt **genau einem** Knoten zugeordnet. Die Zuordnung erfolgt:

- **Beim Produktanlegen** -- Im Erstellungspanel kann optional der Master-Hierarchie-Knoten gewählt werden.
- **In der Produktdetailansicht** -- Über das Hierarchie-Dropdown kann der zugeordnete Knoten geändert werden.
- **Im Hierarchiebaum** -- Im Knotendetail können Sie Produkte suchen und zuordnen.

### Ausgabe-Hierarchie

In Ausgabe-Hierarchien kann ein Produkt **mehreren Knoten** zugeordnet werden. Die Zuordnung erfolgt über die Knotendetailansicht, wo Sie Produkte hinzufügen und entfernen können.

## Hierarchietiefe

Hierarchien unterstützen bis zu **sechs Ebenen** (Wurzel + 5 Unterebenen). Diese Beschränkung stellt sicher, dass die Performance der rekursiven Abfragen (CTEs) optimal bleibt und die Navigation übersichtlich ist.

| Ebene | Typische Verwendung |
|---|---|
| 0 | Wurzelknoten (Gesamtkatalog) |
| 1 | Hauptkategorien (Elektronik, Haushalt, ...) |
| 2 | Unterkategorien (Smartphones, Laptops, ...) |
| 3 | Feinstruktur (Android, iOS, ...) |
| 4 | Spezialkategorien |
| 5 | Detailkategorien |

## Nächste Schritte

- Lernen Sie, wie [Attribute](./attribute) definiert und in Gruppen organisiert werden.
- Erfahren Sie, wie [Produkte](./produkte) in der Hierarchie zugeordnet und gepflegt werden.
- Konfigurieren Sie [Benutzerberechtigungen](./benutzer) auf Basis von Hierarchieknoten.
