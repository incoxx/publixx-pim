---
title: Berichte
---

# Berichte

Das Berichtsmodul im anyPIM ermöglicht die Erstellung individueller Auswertungen über Ihre Produktdaten. Mit dem visuellen Report-Designer definieren Sie Felder, Filter und Sortierungen und generieren Berichte in verschiedenen Ausgabeformaten. Gespeicherte Berichtsvorlagen können jederzeit erneut ausgeführt oder automatisiert nach Zeitplan generiert werden.

## Berichtsübersicht

Über den Menüpunkt **Berichte** in der Sidebar gelangen Sie zur Berichtsübersicht. Diese zeigt alle gespeicherten Berichtsvorlagen:

| Spalte | Beschreibung |
|---|---|
| **Name** | Bezeichnung der Berichtsvorlage |
| **Erstellt von** | Benutzer, der die Vorlage angelegt hat |
| **Ausgabeformat** | Format der Berichtsausgabe (PDF, DOCX, CSV) |
| **Letzte Ausführung** | Zeitpunkt der letzten Berichtsgenerierung |
| **Zeitplan** | Automatische Ausführung (falls konfiguriert) |

## Bericht erstellen

Klicken Sie auf **+ Neuer Bericht**, um den Report-Designer zu öffnen.

### Schritt 1: Grundeinstellungen

Vergeben Sie zunächst die grundlegenden Einstellungen des Berichts:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Name** | Bezeichnung der Berichtsvorlage | Ja |
| **Beschreibung** | Erläuterung des Berichtszwecks | Nein |
| **Ausgabeformat** | PDF, DOCX oder CSV | Ja |

### Schritt 2: Felder auswählen

Im Bereich **Felder** definieren Sie, welche Daten in den Bericht aufgenommen werden. Auf der linken Seite sehen Sie eine Liste aller verfügbaren Felder, gegliedert nach Kategorien:

- **Produktstammdaten** -- SKU, Name, Status, Produkttyp, Erstellungsdatum
- **Attribute** -- Alle im System definierten Produktattribute
- **Preise** -- Preise nach Preisart und Währung
- **Medien** -- Anzahl zugeordneter Medien, Medien-URLs
- **Hersteller** -- Herstellername, Website, Kontaktdaten
- **Relationen** -- Verknüpfte Produkte nach Beziehungstyp

Ziehen Sie die gewünschten Felder per Drag-and-Drop in den Berichtsbereich auf der rechten Seite. Die Reihenfolge der Felder bestimmt die Spaltenreihenfolge im Bericht.

::: tip Hinweis
Für PDF- und DOCX-Berichte können Sie zusätzlich die Spaltenbreite und Ausrichtung (links, zentriert, rechts) für jedes Feld konfigurieren.
:::

### Schritt 3: Filter definieren

Im Abschnitt **Filter** legen Sie Bedingungen fest, die bestimmen, welche Produkte in den Bericht aufgenommen werden:

| Filteroption | Beschreibung |
|---|---|
| **Attributfilter** | Filtert Produkte nach Attributwerten (z.B. „Gewicht > 5 kg") |
| **Statusfilter** | Beschränkt den Bericht auf Produkte mit einem bestimmten Status |
| **Herstellerfilter** | Nur Produkte eines bestimmten Herstellers einbeziehen |
| **Datumsfilter** | Produkte filtern, die in einem bestimmten Zeitraum erstellt oder geändert wurden |
| **Hierarchiefilter** | Nur Produkte aus bestimmten Kategorien berücksichtigen |

Filter können mit UND/ODER-Logik verknüpft werden, um komplexe Abfragen zu erstellen.

### Schritt 4: Sortierung und Gruppierung

Definieren Sie die Reihenfolge der Datensätze im Bericht:

- **Sortierung** -- Wählen Sie ein oder mehrere Felder als Sortierkriterium und legen Sie die Richtung fest (aufsteigend/absteigend).
- **Gruppierung** -- Gruppieren Sie die Ergebnisse nach einem Feld (z.B. nach Hersteller oder Produkttyp). Jede Gruppe erhält eine eigene Überschrift im Bericht.

## Ausgabeformate

Das Berichtsmodul unterstützt drei Ausgabeformate:

| Format | Beschreibung | Eignung |
|---|---|---|
| **PDF** | Druckoptimiertes Dokument mit Kopf- und Fusszeilen | Berichte für die Geschäftsführung, Audits, Dokumentation |
| **DOCX** | Bearbeitbares Word-Dokument | Berichte, die nachbearbeitet werden sollen |
| **CSV** | Tabellarische Rohdaten, kommagetrennt | Weiterverarbeitung in Excel, BI-Tools oder Drittsystemen |

::: warning Warnung
CSV-Berichte enthalten keine Formatierungen, Gruppierungen oder Kopfzeilen. Verwenden Sie dieses Format nur, wenn Sie die Daten maschinell weiterverarbeiten möchten.
:::

## Berichtsvorlagen speichern

Nach der Konfiguration können Sie den Bericht als Vorlage speichern. Gespeicherte Vorlagen stehen Ihnen in der Berichtsübersicht zur Verfügung und können jederzeit erneut ausgeführt werden, ohne die Konfiguration wiederholen zu müssen.

### Vorlage bearbeiten

Öffnen Sie eine gespeicherte Vorlage über das Bearbeitungssymbol in der Berichtsübersicht. Alle Einstellungen (Felder, Filter, Sortierung, Format) können angepasst und erneut gespeichert werden.

## Berichte nach Zeitplan ausführen

Sie können Berichtsvorlagen so konfigurieren, dass sie automatisch nach einem definierten Zeitplan generiert werden:

1. Öffnen Sie eine gespeicherte Berichtsvorlage.
2. Wechseln Sie zum Abschnitt **Zeitplan**.
3. Aktivieren Sie die automatische Ausführung.
4. Definieren Sie das Intervall (täglich, wöchentlich, monatlich).
5. Legen Sie den Zustellweg fest (E-Mail, SFTP, Download-Bereich).

Zeitplangesteuerte Berichte werden im Hintergrund generiert und gemäss der Zustellkonfiguration bereitgestellt.

## Nächste Schritte

- Nutzen Sie [PDF-Vorlagen](./pdf-vorlagen) für die visuelle Gestaltung Ihrer Berichte.
- Konfigurieren Sie [Export-Jobs](./exportjobs) für den automatisierten Datenexport.
- Verwalten Sie Ihre Produktdaten in der [Produktverwaltung](../bedienung/produkte).
