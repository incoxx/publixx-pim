---
title: Medien
---

# Medien

Die Medienverwaltung des Publixx PIM bietet eine zentrale Bibliothek für alle produktbezogenen Dateien. Hier laden Sie Bilder, Dokumente und Videos hoch, organisieren diese und weisen sie Ihren Produkten zu.

## Medienbibliothek

Die Medienbibliothek erreichen Sie über den Menüpunkt **Medien** in der Sidebar. Sie bietet eine Übersicht aller hochgeladenen Dateien mit Vorschau, Filterung und Suchfunktion.

### Upload

Dateien können auf zwei Wegen hochgeladen werden:

1. **Upload-Schaltfläche** -- Klicken Sie auf **+ Medien hochladen** und wählen Sie eine oder mehrere Dateien aus dem Datei-Dialog.
2. **Drag-and-Drop** -- Ziehen Sie Dateien direkt vom Desktop oder Dateimanager in den Upload-Bereich der Medienbibliothek.

Nach dem Upload werden Vorschaubilder (Thumbnails) automatisch generiert. Der Upload-Fortschritt wird pro Datei angezeigt.

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
