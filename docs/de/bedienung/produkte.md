---
title: Produkte
---

# Produkte

Die Produktverwaltung bildet das Herzstück des Publixx PIM. Hier legen Sie Produkte an, pflegen Attributwerte in verschiedenen Sprachen, verwalten Varianten und Relationen und steuern den gesamten Produktlebenszyklus.

## Produktliste

Nach Klick auf **Produkte** in der Sidebar gelangen Sie zur Produktliste. Diese zeigt alle Produkte in einer tabellarischen Übersicht mit folgenden Spalten:

| Spalte | Beschreibung |
|---|---|
| **SKU** | Eindeutige Artikelnummer (monospaced dargestellt) |
| **Name** | Produktbezeichnung in der aktuellen Sprache |
| **Typ** | Zugeordneter Produkttyp |
| **Status** | Aktueller Status (Entwurf, Aktiv, Inaktiv) |
| **Geändert** | Zeitpunkt der letzten Änderung |

### Filtern und Suchen

Oberhalb der Tabelle befindet sich die **Filterleiste** (FilterBar), mit der Sie die Produktliste eingrenzen können:

- **Volltextsuche** -- Geben Sie einen Suchbegriff ein, um Produkte nach SKU, Name oder anderen durchsuchbaren Attributen zu filtern.
- **Statusfilter** -- Schränken Sie die Anzeige auf Produkte mit einem bestimmten Status ein.
- **Produkttypfilter** -- Zeigen Sie nur Produkte eines bestimmten Typs an.
- **Aktive Filter** werden als Chips oberhalb der Tabelle angezeigt und können einzeln entfernt oder komplett zurückgesetzt werden.

### Sortieren

Klicken Sie auf eine sortierbare Spaltenüberschrift (SKU, Name, Status, Geändert), um die Liste aufsteigend zu sortieren. Ein erneuter Klick kehrt die Sortierrichtung um.

### Paginierung

Die Produktliste ist seitenweise aufgebaut. Am unteren Rand der Tabelle finden Sie die Seitennavigation, mit der Sie zwischen den Seiten wechseln. Die Anzahl der Einträge pro Seite kann in den Einstellungen konfiguriert werden.

## Produkt anlegen

Klicken Sie auf die Schaltfläche **+ Neues Produkt** oberhalb der Produktliste. Es öffnet sich ein Seitenpanel (ProductCreatePanel) mit folgenden Pflichtfeldern:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **SKU** | Eindeutige Artikelnummer | Ja |
| **Name** | Produktbezeichnung | Ja |
| **Produkttyp** | Auswahl des Produkttyps, der das Attributschema bestimmt | Ja |
| **EAN** | Europäische Artikelnummer | Nein |
| **Status** | Initialer Status (Standard: Entwurf) | Ja |

Nach dem Speichern wird das Produkt angelegt und Sie werden automatisch zur Produktdetailansicht weitergeleitet.

::: tip Hinweis
Der Produkttyp bestimmt, welche Attribute für das Produkt verfügbar sind. Wählen Sie den Typ sorgfältig, da er das gesamte Attributschema vorgibt.
:::

## Produktdetailansicht

Die Detailansicht eines Produkts ist in einen **Kopfbereich** und **Registerreiter** (Tabs) gegliedert:

<svg viewBox="0 0 800 520" xmlns="http://www.w3.org/2000/svg" style="max-width:100%;height:auto;margin:1.5rem 0;">
  <defs>
    <style>
      .pd-frame { fill: #ffffff; stroke: #e2e8f0; stroke-width: 2; rx: 8; }
      .pd-header { fill: #f8fafc; stroke: #e2e8f0; stroke-width: 1; rx: 8 8 0 0; }
      .pd-tab-bar { fill: #f1f5f9; }
      .pd-tab { fill: #ffffff; stroke: #e2e8f0; stroke-width: 1; rx: 6 6 0 0; }
      .pd-tab-active { fill: #ffffff; stroke: #6366f1; stroke-width: 2; rx: 6 6 0 0; }
      .pd-content { fill: #ffffff; stroke: #e2e8f0; stroke-width: 1; rx: 0 0 8 8; }
      .pd-title { fill: #1e293b; font-family: system-ui, sans-serif; font-size: 18px; font-weight: 700; }
      .pd-subtitle { fill: #64748b; font-family: system-ui, sans-serif; font-size: 12px; }
      .pd-label { fill: #1e293b; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .pd-value { fill: #475569; font-family: system-ui, sans-serif; font-size: 12px; }
      .pd-tab-text { fill: #64748b; font-family: system-ui, sans-serif; font-size: 12px; }
      .pd-tab-text-active { fill: #6366f1; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .pd-btn { fill: #6366f1; rx: 6; }
      .pd-btn-text { fill: #ffffff; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .pd-btn-back { fill: #f1f5f9; stroke: #e2e8f0; stroke-width: 1; rx: 6; }
      .pd-btn-back-text { fill: #475569; font-family: system-ui, sans-serif; font-size: 12px; }
      .pd-status { fill: #dcfce7; rx: 10; }
      .pd-status-text { fill: #166534; font-family: system-ui, sans-serif; font-size: 11px; font-weight: 600; }
      .pd-group-header { fill: #f8fafc; stroke: #e2e8f0; stroke-width: 1; rx: 4; }
      .pd-group-text { fill: #334155; font-family: system-ui, sans-serif; font-size: 12px; font-weight: 600; }
      .pd-input { fill: #f8fafc; stroke: #cbd5e1; stroke-width: 1; rx: 4; }
      .pd-input-text { fill: #475569; font-family: system-ui, sans-serif; font-size: 11px; }
      .pd-section-label { fill: #94a3b8; font-family: system-ui, sans-serif; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
    </style>
  </defs>
  <!-- Frame -->
  <rect class="pd-frame" x="0" y="0" width="800" height="520" />

  <!-- Header area -->
  <rect class="pd-header" x="0" y="0" width="800" height="80" />
  <!-- Back button -->
  <rect class="pd-btn-back" x="16" y="16" width="80" height="30" />
  <text class="pd-btn-back-text" x="32" y="36">Zurück</text>
  <!-- Title -->
  <text class="pd-title" x="16" y="68">SKU-12345 — Beispielprodukt Premium</text>
  <!-- Status badge -->
  <rect class="pd-status" x="420" y="52" width="55" height="22" />
  <text class="pd-status-text" x="432" y="67">Aktiv</text>
  <!-- Save button -->
  <rect class="pd-btn" x="700" y="16" width="84" height="30" />
  <text class="pd-btn-text" x="718" y="36">Speichern</text>

  <!-- Tab bar -->
  <rect class="pd-tab-bar" x="0" y="80" width="800" height="36" />
  <rect class="pd-tab-active" x="16" y="82" width="80" height="34" />
  <text class="pd-tab-text-active" x="30" y="103">Attribute</text>
  <rect class="pd-tab" x="100" y="82" width="110" height="34" />
  <text class="pd-tab-text" x="110" y="103">Var.-Attribute</text>
  <rect class="pd-tab" x="214" y="82" width="75" height="34" />
  <text class="pd-tab-text" x="222" y="103">Varianten</text>
  <rect class="pd-tab" x="293" y="82" width="65" height="34" />
  <text class="pd-tab-text" x="302" y="103">Medien</text>
  <rect class="pd-tab" x="362" y="82" width="60" height="34" />
  <text class="pd-tab-text" x="372" y="103">Preise</text>
  <rect class="pd-tab" x="426" y="82" width="80" height="34" />
  <text class="pd-tab-text" x="436" y="103">Relationen</text>
  <rect class="pd-tab" x="510" y="82" width="75" height="34" />
  <text class="pd-tab-text" x="519" y="103">Vorschau</text>
  <rect class="pd-tab" x="589" y="82" width="80" height="34" />
  <text class="pd-tab-text" x="598" y="103">Versionen</text>

  <!-- Content area -->
  <rect class="pd-content" x="0" y="116" width="800" height="404" />

  <!-- Attribute group 1 -->
  <rect class="pd-group-header" x="16" y="130" width="768" height="28" />
  <text class="pd-group-text" x="28" y="149">Stammdaten</text>

  <!-- Attribute rows -->
  <text class="pd-label" x="28" y="182">Produktname</text>
  <rect class="pd-input" x="160" y="168" width="300" height="24" />
  <text class="pd-input-text" x="168" y="184">Beispielprodukt Premium</text>
  <text class="pd-section-label" x="480" y="182">DE | EN</text>

  <text class="pd-label" x="28" y="216">Beschreibung</text>
  <rect class="pd-input" x="160" y="200" width="300" height="40" />
  <text class="pd-input-text" x="168" y="220">Ausführliche Beschreibung des</text>
  <text class="pd-input-text" x="168" y="234">Produkts mit allen Details...</text>

  <text class="pd-label" x="28" y="264">Gewicht</text>
  <rect class="pd-input" x="160" y="250" width="120" height="24" />
  <text class="pd-input-text" x="168" y="266">2.500</text>
  <text class="pd-value" x="290" y="266">kg</text>

  <text class="pd-label" x="28" y="298">Farbe</text>
  <rect class="pd-input" x="160" y="284" width="200" height="24" />
  <text class="pd-input-text" x="168" y="300">Anthrazit</text>

  <!-- Attribute group 2 -->
  <rect class="pd-group-header" x="16" y="324" width="768" height="28" />
  <text class="pd-group-text" x="28" y="343">Technische Daten</text>

  <text class="pd-label" x="28" y="376">Material</text>
  <rect class="pd-input" x="160" y="362" width="200" height="24" />
  <text class="pd-input-text" x="168" y="378">Edelstahl V2A</text>

  <text class="pd-label" x="28" y="410">Zertifizierung</text>
  <rect class="pd-input" x="160" y="396" width="200" height="24" />
  <text class="pd-input-text" x="168" y="412">CE, TÜV</text>

  <text class="pd-label" x="28" y="444">Verfügbar</text>
  <rect fill="#6366f1" x="160" y="432" width="18" height="18" rx="3" />
  <text fill="#ffffff" x="165" y="446" font-family="system-ui, sans-serif" font-size="12" font-weight="700">✓</text>

  <!-- Footer hint -->
  <text class="pd-section-label" x="28" y="490">Attribute werden nach Gruppen sortiert angezeigt. Übersetzbare Felder können pro Sprache bearbeitet werden.</text>
</svg>

### Kopfbereich

Im Kopfbereich sehen Sie:
- **Zurück-Schaltfläche** -- Navigiert zurück zur Produktliste
- **SKU und Produktname** -- Identifikation des aktuellen Produkts
- **Statusanzeige** -- Farblich kodierter Badge (Entwurf/Aktiv/Inaktiv)
- **Speichern-Schaltfläche** -- Sichert alle Änderungen (`Strg + S`)

### Registerreiter (Tabs)

Die Produktdetailansicht verfügt über folgende Tabs:

| Tab | Inhalt |
|---|---|
| **Attribute** | Reguläre Produktattribute nach Gruppen sortiert |
| **Varianten-Attribute** | Attribute, die als Variantenunterscheidung dienen |
| **Varianten** | Liste der Produktvarianten mit Vererbungsstatus |
| **Medien** | Zugeordnete Bilder, Dokumente und Videos |
| **Preise** | Preise nach Preisart und Währung |
| **Relationen** | Verknüpfte Produkte (Zubehör, Cross-Sell etc.) |
| **Vorschau** | Darstellung des Produkts im Ausgabeformat |
| **Versionen** | Versionshistorie mit Diff-Ansicht |

## Attributwerte bearbeiten

Im Tab **Attribute** werden die Attributwerte nach Attributgruppen gegliedert dargestellt. Die Darstellung des Eingabefelds richtet sich nach dem Datentyp des Attributs:

| Datentyp | Eingabefeld | Beschreibung |
|---|---|---|
| **String** | Textfeld | Einzeiliger oder mehrzeiliger Text |
| **Number** | Zahlenfeld | Ganzzahlen |
| **Float** | Dezimalfeld | Fließkommazahlen mit Einheit |
| **Date** | Datumsauswahl | Kalender-Widget |
| **Flag** | Checkbox | Ja/Nein-Wert |
| **Selection** | Dropdown | Auswahl aus einer Werteliste |
| **Dictionary** | JSON-Editor | Schlüssel-Wert-Paare |
| **Collection** | JSON-Editor | Strukturierte Sammlungen |

### Übersetzbare Attribute

Bei übersetzbaren Attributen erscheint neben dem Eingabefeld ein Sprachschalter (z.B. DE | EN). Sie können den Wert für jede konfigurierte Sprache separat pflegen. Die aktuell bearbeitete Sprache wird hervorgehoben.

### Pflichtfelder

Pflichtattribute sind mit einem Sternchen (*) markiert. Ein Produkt kann nur dann auf den Status **Aktiv** gesetzt werden, wenn alle Pflichtattribute ausgefüllt sind.

### Attributgruppen (Collection Groups)

Attribute werden in logischen Gruppen dargestellt, die durch Gruppenüberschriften getrennt sind. Diese Gruppen werden durch die Zuordnung in der Hierarchie oder im Produkttyp bestimmt und können Collection Groups enthalten -- wiederholbare Blöcke zusammengehöriger Attribute.

## Produktvarianten

Varianten sind Ausprägungen eines Elternprodukts, die sich in bestimmten Attributen unterscheiden (z.B. Farbe, Größe). Das Variantensystem basiert auf einem **Vererbungsmechanismus**.

### Varianten anlegen

1. Navigieren Sie zum Tab **Varianten** in der Produktdetailansicht.
2. Klicken Sie auf **+ Variante anlegen**.
3. Geben Sie SKU und Name der Variante ein.
4. Die Variante erbt automatisch alle Attributwerte des Elternprodukts.

### Vererbungsregeln

Jedes Attribut einer Variante unterliegt einem von zwei Vererbungsmodi:

| Modus | Verhalten |
|---|---|
| **Erben (inherit)** | Der Wert wird vom Elternprodukt übernommen. Änderungen am Elternprodukt werden automatisch propagiert. Das Feld ist in der Variante schreibgeschützt. |
| **Überschreiben (override)** | Die Variante verwendet einen eigenen Wert, der unabhängig vom Elternprodukt gepflegt wird. |

::: info Vererbungsprinzip
Beim Anlegen einer Variante stehen alle Attribute zunächst auf **Erben**. Erst wenn Sie einen Wert in der Variante explizit ändern, wechselt das Attribut auf **Überschreiben**. Dieses Verhalten kann pro Attribut zurückgesetzt werden, um wieder den geerbten Wert zu verwenden.
:::

### Varianten-Attribute

Im Tab **Varianten-Attribute** definieren Sie, welche Attribute als variantenspezifisch gelten sollen (z.B. Farbe, Größe, Ausführung). Diese Attribute werden in der Variantenliste als Spalten angezeigt und dienen der schnellen Unterscheidung.

## Produktrelationen

Im Tab **Relationen** können Sie Beziehungen zwischen Produkten herstellen:

| Relationstyp | Beschreibung |
|---|---|
| **Zubehör** | Ergänzende Produkte, die zum Hauptprodukt passen |
| **Cross-Sell** | Verwandte Produkte für den Querverkauf |
| **Up-Sell** | Höherwertige Alternativen |
| **Ersatzteil** | Ersatzteile für das Hauptprodukt |

Um eine Relation hinzuzufügen:
1. Wählen Sie den Relationstyp.
2. Suchen Sie das Zielprodukt über SKU oder Name.
3. Bestätigen Sie die Zuordnung.

Relationen sind gerichtet: Wenn Produkt A als Zubehör von Produkt B definiert wird, gilt diese Verknüpfung nur in dieser Richtung.

## Produktstatus und Workflow

Jedes Produkt durchläuft einen definierten Statusworkflow:

```
┌──────────┐      ┌──────────┐      ┌──────────┐
│ Entwurf  │ ───> │  Aktiv   │ ───> │ Inaktiv  │
│ (draft)  │      │ (active) │      │(inactive)│
└──────────┘      └──────────┘      └──────────┘
      ^                                   │
      └───────────────────────────────────┘
```

| Status | Bedeutung |
|---|---|
| **Entwurf** (draft) | Produkt ist in Bearbeitung und noch nicht freigegeben. Nicht exportierbar. |
| **Aktiv** (active) | Produkt ist vollständig gepflegt und für den Export freigegeben. Alle Pflichtattribute müssen befüllt sein. |
| **Inaktiv** (inactive) | Produkt ist deaktiviert und wird nicht mehr exportiert. Kann jederzeit reaktiviert oder zurück in den Entwurf gesetzt werden. |

::: warning Statuswechsel zu Aktiv
Der Wechsel von **Entwurf** zu **Aktiv** ist nur möglich, wenn alle Pflichtattribute des zugeordneten Produkttyps ausgefüllt sind. Andernfalls zeigt das System eine Fehlermeldung mit den fehlenden Attributen.
:::

## Massenoperationen (Bulk Operations)

Für die effiziente Bearbeitung großer Produktbestände stehen Massenoperationen zur Verfügung:

1. **Mehrfachauswahl** -- Aktivieren Sie Checkboxen in der Produktliste, um mehrere Produkte auszuwählen.
2. **Massenaktionen** -- Nach der Auswahl erscheinen Aktionsschaltflächen:
   - **Status ändern** -- Setzt den Status aller ausgewählten Produkte.
   - **Löschen** -- Entfernt die ausgewählten Produkte nach Bestätigung.

::: danger Achtung
Das Löschen von Produkten ist unwiderruflich. Gelöschte Produkte können nicht wiederhergestellt werden. Nutzen Sie stattdessen den Status **Inaktiv**, wenn Sie Produkte vorübergehend aus dem Export entfernen möchten.
:::

## Versionshistorie

Im Tab **Versionen** können Sie alle Änderungen an einem Produkt nachvollziehen:

- **Versionsliste** -- Zeigt alle gespeicherten Versionen mit Zeitstempel und Benutzer.
- **Diff-Ansicht** (ProductVersionDiff) -- Vergleicht zwei Versionen und hebt Unterschiede farblich hervor (grün = hinzugefügt, rot = entfernt).

## Nächste Schritte

- Erfahren Sie mehr über die [Attribute](./attribute), die Ihre Produkte strukturieren.
- Lernen Sie, wie [Hierarchien](./hierarchien) Ihren Produkten Attribute zuweisen.
- Weisen Sie Ihren Produkten [Medien](./medien) und [Preise](./preise) zu.
