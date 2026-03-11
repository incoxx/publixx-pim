---
title: Medien
---

# Medien

Die Medienverwaltung des anyPIM bietet eine zentrale Bibliothek für alle produktbezogenen Dateien. Hier laden Sie Bilder, Dokumente und Videos hoch, organisieren diese und weisen sie Ihren Produkten zu.

## Medienbibliothek

Die Medienbibliothek erreichen Sie über den Menüpunkt **Medien** in der Sidebar. Sie bietet eine Übersicht aller hochgeladenen Dateien mit Vorschau, Filterung und Suchfunktion.

### Upload

Dateien können auf mehreren Wegen hochgeladen werden:

1. **Upload-Schaltfläche** -- Klicken Sie auf **+ Medien hochladen** und wählen Sie eine oder mehrere Dateien aus dem Datei-Dialog.
2. **Drag-and-Drop** -- Ziehen Sie Dateien direkt vom Desktop oder Dateimanager in den Upload-Bereich der Medienbibliothek.
3. **URL-Import** -- Importieren Sie einzelne Medien direkt von einer URL. Das System lädt die Datei herunter, erkennt den MIME-Typ automatisch und erstellt den Medieneintrag. SSRF-Schutz verhindert den Zugriff auf interne Netzwerkadressen.
4. **Bulk-Import per Excel** -- Laden Sie eine Excel- oder CSV-Datei mit URLs hoch, um viele Medien auf einmal zu importieren. Das System erkennt die URL-Spalte automatisch (unterstützte Spaltennamen: `url`, `bild-url`, `bild_url`, `image_url`, `link`). Maximal 500 URLs pro Import.

Nach dem Upload werden Vorschaubilder (Thumbnails) automatisch generiert. Der Upload-Fortschritt wird pro Datei angezeigt. Bei JPEG-Dateien wird die EXIF-Orientierung automatisch korrigiert.

::: tip Hinweis
Große Dateien werden asynchron verarbeitet. Bei umfangreichen Uploads kann die Thumbnail-Generierung einige Sekunden dauern. Die Dateien sind jedoch sofort nach dem Upload verfügbar.
:::

### Unterstützte Medientypen

| Kategorie | Formate | Beschreibung |
|---|---|---|
| **Bilder** | JPG, PNG, GIF, SVG, WebP | Produktfotos, Illustrationen, Grafiken |
| **Dokumente** | PDF, DOCX, XLSX | Datenblätter, Anleitungen, Spezifikationen |
| **Videos** | MP4, WebM | Produktvideos, Anleitungen |

### Durchsuchen und Filtern

Die Medienbibliothek bietet folgende Such- und Filtermöglichkeiten:

- **Volltextsuche** -- Durchsucht Dateinamen und Metadaten.
- **Typfilter** -- Schränkt die Anzeige auf eine bestimmte Kategorie ein (Bilder, Dokumente, Videos).
- **Sortierung** -- Sortiert nach Dateiname, Upload-Datum oder Dateigröße.

## Medien einem Produkt zuweisen

Die Zuordnung von Medien zu Produkten erfolgt in der **Produktdetailansicht** im Tab **Medien**.

### Per Drag-and-Drop

1. Öffnen Sie die Produktdetailansicht und wechseln Sie zum Tab **Medien**.
2. Ziehen Sie Dateien aus der Medienbibliothek oder direkt vom Desktop in den Zuordnungsbereich.
3. Die Datei wird dem Produkt zugeordnet und in der Medienliste angezeigt.

### Per Auswahldialog

1. Klicken Sie im Medien-Tab auf **+ Medium zuweisen**.
2. Es öffnet sich ein Auswahldialog mit der Medienbibliothek.
3. Suchen und wählen Sie die gewünschten Dateien.
4. Bestätigen Sie die Zuordnung.

### Reihenfolge festlegen

Die Reihenfolge der zugeordneten Medien kann per Drag-and-Drop geändert werden. Das erste Bild in der Liste dient typischerweise als **Hauptbild** (Teaser) des Produkts.

## Medien-Metadaten

Jedes Medium verfügt über bearbeitbare Metadaten, die für die Ausgabe und Auffindbarkeit wichtig sind:

| Feld | Beschreibung |
|---|---|
| **Dateiname** | Originalname der hochgeladenen Datei |
| **Alt-Text** | Alternativer Text für Barrierefreiheit und SEO (übersetzbar) |
| **Titel** | Anzeigetitel des Mediums (übersetzbar) |
| **Verwendungstyp** | Art der Verwendung (siehe unten) |
| **Dateigröße** | Automatisch ermittelte Größe in KB/MB |
| **Abmessungen** | Breite und Höhe in Pixel (nur bei Bildern) |
| **MIME-Typ** | Technischer Dateityp (z.B. `image/jpeg`) |

### Verwendungstypen

Der Verwendungstyp definiert den Kontext, in dem ein Medium eingesetzt wird:

| Verwendungstyp | Beschreibung |
|---|---|
| **Teaser** | Hauptbild für Produktübersichten, Listenansichten und Vorschaubilder |
| **Galerie** | Zusätzliche Bilder für die Produkt-Bildergalerie |
| **Datenblatt** | Technisches Datenblatt oder Spezifikation (typisch: PDF) |
| **Anleitung** | Bedienungsanleitung oder Montageanweisung |
| **Video** | Produktvideo oder Anwendungsvideo |
| **Sonstige** | Alle anderen Medientypen |

Der Verwendungstyp wird bei der Zuordnung zum Produkt festgelegt und kann nachträglich geändert werden. Er dient beim Export als Filter, um z.B. nur Teaser-Bilder für einen Online-Shop zu exportieren.

### Erlaubte Dateitypen pro Verwendungstyp

Für jeden Verwendungstyp (Bildtyp) können die **erlaubten Dateitypen** konfiguriert werden. Standardmäßig sind alle Dateitypen erlaubt. Über die Bildtyp-Verwaltung (**Einstellungen** > **Bildtypen**) lässt sich pro Verwendungstyp festlegen, welche Dateiendungen zugelassen sind:

- **Alle Dateitypen erlaubt** (Standard) -- Keine Einschränkung, jeder Medientyp kann zugeordnet werden.
- **Eingeschränkte Dateitypen** -- Nur bestimmte Endungen sind erlaubt (z.B. nur `jpg`, `png`, `webp` für Teaser-Bilder oder nur `pdf` für Datenblätter).

Die erlaubten Endungen sind in Gruppen organisiert:

| Gruppe | Endungen |
|---|---|
| **Bilder** | jpg, jpeg, png, gif, webp, svg, bmp, tiff |
| **Dokumente** | pdf, doc, docx, xls, xlsx, ppt, pptx, csv |
| **Design** | eps, ai |

Beim Zuweisen eines Mediums zu einem Produkt prüft das System automatisch, ob die Dateiendung zum gewählten Verwendungstyp passt. Der Medien-Auswahldialog filtert die verfügbaren Medien ebenfalls entsprechend.

## PDF-Vorschau

PDF-Dateien werden in der Medienbibliothek und in der Produktdetailansicht mit einer **Live-Vorschau** der ersten Seite angezeigt. Die Vorschau wird clientseitig mit PDF.js gerendert. Nicht-Bild-Medien (PDF, DOCX, etc.) zeigen in der Grid-Ansicht ein Dateityp-Symbol mit der Dateiendung.

## Automatische SKU-Zuordnung (Auto-Match)

Die Auto-Match-Funktion ordnet Medien automatisch Produkten zu, basierend auf dem Dateinamen. Sie definieren ein **reguläres Ausdrucksmuster** (Regex), das die SKU aus dem Dateinamen extrahiert:

1. Navigieren Sie zur Auto-Match-Funktion in der Medienverwaltung.
2. Geben Sie ein Regex-Muster ein (z.B. `^(\w+)_` um die SKU vor dem ersten Unterstrich zu extrahieren).
3. Optional: Wählen Sie einen Verwendungstyp für die automatischen Zuordnungen.
4. Starten Sie einen **Trockenlauf** (`dry_run`), um die Zuordnungen vorab zu prüfen.
5. Bestätigen Sie die Zuordnung, um die Medien den passenden Produkten zuzuweisen.

Das System verarbeitet die Medien in Blöcken und gibt eine Zusammenfassung zurück: Anzahl zugeordnet, nicht zugeordnet und Gesamtanzahl.

## Asset-Ordner

Medien können in **Asset-Ordnern** organisiert werden. Asset-Ordner sind als Baumstruktur aufgebaut (analog zu Hierarchien) und bieten:

- **Ordnerstruktur** -- Verschachtelte Ordner zur logischen Organisation von Medien.
- **Medienanzahl** -- Jeder Ordner zeigt die Anzahl der enthaltenen Medien an (inklusive Unterordner).
- **Breadcrumb-Navigation** -- In der Einzelansicht eines Mediums wird der Ordnerpfad angezeigt.
- **Bulk-Verschieben** -- Mehrere Medien können gleichzeitig in einen anderen Ordner verschoben werden.
- **ZIP-Download** -- Ausgewählte Medien können als ZIP-Archiv heruntergeladen werden.
- **Verwendungszweck** -- Medien können als `print`, `web` oder `both` klassifiziert werden.

## Medien bearbeiten und löschen

### Metadaten bearbeiten

Klicken Sie in der Medienbibliothek auf ein Medium, um dessen Detailansicht zu öffnen. Dort können Sie Alt-Text, Titel und Verwendungstyp bearbeiten. Die Felder Alt-Text und Titel sind **übersetzbar** und können in Deutsch und Englisch gepflegt werden.

### Medium löschen

Klicken Sie in der Detailansicht auf **Löschen**, um ein Medium aus der Bibliothek zu entfernen.

::: danger Achtung
Das Löschen eines Mediums entfernt es auch aus allen Produktzuordnungen. Prüfen Sie vor dem Löschen, bei welchen Produkten das Medium verwendet wird.
:::

### Zuordnung entfernen

Um ein Medium von einem Produkt zu lösen, ohne es aus der Bibliothek zu löschen, klicken Sie im Medien-Tab des Produkts auf das Entfernen-Symbol neben dem entsprechenden Medium.

## Bereitstellung über die API

Hochgeladene Medien werden über die REST-API bereitgestellt und können von externen Systemen abgerufen werden:

- **Originaldatei** -- Zugriff auf die Originaldatei in voller Auflösung.
- **Vorschaubild** -- Automatisch generierte Thumbnails für eine schnelle Vorschau.
- **Metadaten** -- Die Medien-Metadaten (Alt-Text, Titel, Verwendungstyp) sind über die API als JSON abrufbar.

Für Details zur API-Integration verweisen wir auf die [API-Dokumentation](/de/api/).

## Best Practices

- **Dateinamen** -- Verwenden Sie aussagekräftige Dateinamen, die das Produkt und den Inhalt beschreiben (z.B. `SKU12345_frontal.jpg` statt `IMG_001.jpg`).
- **Alt-Texte** -- Pflegen Sie Alt-Texte für alle Bilder. Sie sind nicht nur für die Barrierefreiheit wichtig, sondern verbessern auch die Auffindbarkeit und SEO.
- **Verwendungstypen** -- Setzen Sie den Verwendungstyp konsequent, damit beim Export die richtigen Medien an die richtigen Stellen gelangen.
- **Dateiformate** -- Verwenden Sie für Produktfotos WebP oder JPG in ausreichender Auflösung. PDF eignet sich für Datenblätter und Anleitungen.

## Nächste Schritte

- Erfahren Sie, wie Sie [Produkte](./produkte) anlegen und Medien im Tab „Medien" zuordnen.
- Lernen Sie die [API-Dokumentation](/de/api/) kennen, um Medien programmatisch abzurufen.
