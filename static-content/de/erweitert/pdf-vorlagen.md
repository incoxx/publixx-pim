---
title: PDF-Vorlagen
---

# PDF-Vorlagen

Mit dem PDF-Vorlagen-Designer des anyPIM erstellen Sie individuelle Layouts für Produktdatenblätter, Katalogseiten und Berichte. Über einen visuellen Editor platzieren Sie Platzhalter für Produktattribute, Bilder und statische Texte und gestalten mehrseitige Dokumente mit Kopf- und Fusszeilen.

## Vorlagenübersicht

Über den Menüpunkt **PDF-Vorlagen** in der Sidebar gelangen Sie zur Übersicht aller angelegten Vorlagen:

| Spalte | Beschreibung |
|---|---|
| **Name** | Bezeichnung der Vorlage |
| **Seitenformat** | Seitenformat (A4, A3, Letter etc.) |
| **Ausrichtung** | Hochformat oder Querformat |
| **Erstellt von** | Benutzer, der die Vorlage angelegt hat |
| **Geändert** | Zeitpunkt der letzten Bearbeitung |

## Vorlage erstellen

Klicken Sie auf **+ Neue Vorlage**, um den visuellen PDF-Designer zu öffnen.

### Grundeinstellungen

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Name** | Bezeichnung der Vorlage | Ja |
| **Seitenformat** | Format der Seite (A4, A3, Letter, Custom) | Ja |
| **Ausrichtung** | Hochformat (Portrait) oder Querformat (Landscape) | Ja |
| **Ränder** | Seitenränder in Millimetern (oben, unten, links, rechts) | Ja |

## Visueller Editor

Der PDF-Designer stellt eine WYSIWYG-Oberfläche bereit, auf der Sie Elemente frei positionieren können. Die Arbeitsfläche entspricht massstabsgetreu dem gewählten Seitenformat.

### Verfügbare Elemente

| Element | Beschreibung |
|---|---|
| **Textfeld** | Statischer Text mit konfigurierbarer Schriftart, -grösse und -farbe |
| **Platzhalter** | Dynamischer Wert aus einem Produktattribut (z.B. <code v-pre>{{product.name}}</code>) |
| **Bild-Platzhalter** | Platzhalter für ein Produktbild oder Herstellerlogo |
| **Statisches Bild** | Fest eingebundenes Bild (z.B. Firmenlogo, Hintergrundbild) |
| **Tabelle** | Tabellarische Darstellung mehrerer Attribute |
| **Linie / Trennstrich** | Horizontale oder vertikale Trennlinie |
| **Seitenzahl** | Automatische Seitennummerierung |
| **Barcode / QR-Code** | Generierter Code aus einem Attributwert (z.B. EAN) |

### Elemente positionieren

Ziehen Sie Elemente aus der Werkzeugleiste auf die Arbeitsfläche. Jedes Element kann per Drag-and-Drop verschoben und über Anfasspunkte in der Grösse angepasst werden. Im Eigenschaftenpanel auf der rechten Seite konfigurieren Sie die detaillierten Einstellungen des ausgewählten Elements.

::: tip Hinweis
Verwenden Sie das Raster und die Hilfslinien des Editors, um Elemente präzise auszurichten. Die Rasterfunktion kann über das Menü **Ansicht** > **Raster einblenden** aktiviert werden.
:::

## Platzhalter

Platzhalter sind dynamische Felder, die bei der PDF-Generierung durch die tatsächlichen Produktdaten ersetzt werden. Sie verwenden die Syntax <code v-pre>{{Bereich.Feldname}}</code>.

### Verfügbare Platzhalterbereiche

| Bereich | Beispiele | Beschreibung |
|---|---|---|
| `product` | <code v-pre>{{product.sku}}</code>, <code v-pre>{{product.name}}</code> | Produktstammdaten |
| `attribute` | <code v-pre>{{attribute.gewicht}}</code>, <code v-pre>{{attribute.farbe}}</code> | Produktattribute (technischer Name) |
| `price` | <code v-pre>{{price.netto}}</code>, <code v-pre>{{price.brutto}}</code> | Preisdaten |
| `manufacturer` | <code v-pre>{{manufacturer.name}}</code>, <code v-pre>{{manufacturer.logo}}</code> | Herstellerdaten |
| `media` | <code v-pre>{{media.main_image}}</code>, <code v-pre>{{media.gallery}}</code> | Produktmedien |
| `meta` | <code v-pre>{{meta.date}}</code>, <code v-pre>{{meta.page}}</code> | Metadaten (Datum, Seitenzahl) |

### Platzhalter formatieren

Für jeden Platzhalter können Sie Formatierungsoptionen festlegen:

- **Zahlenformat** -- Dezimalstellen, Tausendertrennzeichen, Währungssymbol
- **Datumsformat** -- Darstellung des Datums (z.B. „dd.MM.yyyy" oder „MMMM yyyy")
- **Textlänge** -- Maximale Zeichenanzahl mit optionalem Abschneiden und Auslassungszeichen
- **Fallback-Wert** -- Text, der angezeigt wird, wenn der Platzhalter keinen Wert hat

## Mehrseitige Layouts

PDF-Vorlagen können mehrere Seiten umfassen. Jede Seite kann ein eigenes Layout besitzen.

### Seitentypen

| Typ | Beschreibung |
|---|---|
| **Einzelseite** | Eine feste Seite mit statischem Layout |
| **Wiederholungsseite** | Wird für jedes Produkt im Bericht wiederholt |
| **Deckblatt** | Erste Seite des Dokuments mit Titelgestaltung |
| **Schlussseite** | Letzte Seite mit Zusammenfassung oder Kontaktdaten |

### Seitenreihenfolge verwalten

Im Seitennavigator am linken Rand des Editors sehen Sie alle Seiten als Miniaturansichten. Per Drag-and-Drop können Sie die Reihenfolge ändern. Über das Kontextmenü fügen Sie neue Seiten hinzu oder entfernen bestehende.

## Kopf- und Fusszeilen

Jede Vorlage kann mit Kopf- und Fusszeilen versehen werden, die auf allen Seiten oder nur auf bestimmten Seiten angezeigt werden.

### Konfiguration

1. Klicken Sie auf **Kopf-/Fusszeile bearbeiten** im Menü des Editors.
2. Definieren Sie den Inhalt (Text, Bilder, Platzhalter).
3. Legen Sie fest, ob die Kopf-/Fusszeile auf der ersten Seite, auf allen Seiten oder nur auf Folgeseiten erscheint.

::: warning Warnung
Kopf- und Fusszeilen reduzieren den verfügbaren Inhaltsbereich der Seite. Berücksichtigen Sie dies bei der Gestaltung Ihres Layouts.
:::

## Vorschau

Der PDF-Designer bietet eine Vorschaufunktion, mit der Sie das Ergebnis vor der endgültigen Generierung prüfen können:

1. Klicken Sie auf **Vorschau** in der Werkzeugleiste.
2. Wählen Sie ein Beispielprodukt aus, dessen Daten für die Vorschau verwendet werden sollen.
3. Das System generiert eine PDF-Vorschau, in der alle Platzhalter durch die tatsächlichen Produktdaten ersetzt sind.

Die Vorschau öffnet sich in einem neuen Tab oder als eingebettete Ansicht im Editor.

## Nächste Schritte

- Erstellen Sie [Berichte](./berichte) und weisen Sie ihnen eine PDF-Vorlage zu.
- Konfigurieren Sie [Export-Jobs](./exportjobs) für die automatisierte PDF-Generierung.
- Verwalten Sie [Produktmedien](../bedienung/medien), die in Ihren PDF-Vorlagen verwendet werden.
