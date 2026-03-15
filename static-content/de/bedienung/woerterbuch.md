---
title: Wörterbuch
---

# Wörterbuch

Das Wörterbuch im anyPIM dient der zentralen Verwaltung von Fachbegriffen und Terminologie. Es stellt sicher, dass in allen Produktdaten, Exporten und Übersetzungen eine einheitliche Sprache verwendet wird. Über das Wörterbuch definieren Sie verbindliche Begriffe, hinterlegen Übersetzungen und verknüpfen Terminologieeinträge mit Attributen.

## Übersicht

Über den Menüpunkt **Wörterbuch** in der Sidebar gelangen Sie zur Wörterbuchübersicht. Diese zeigt alle gepflegten Begriffe in einer durchsuchbaren Liste:

| Spalte | Beschreibung |
|---|---|
| **Begriff** | Der definierte Fachbegriff in der Standardsprache |
| **Übersetzungen** | Verfügbare Übersetzungen mit Sprachkürzel |
| **Kategorie** | Thematische Einordnung des Begriffs |
| **Verwendet in** | Anzahl der Attribute, die diesen Begriff referenzieren |
| **Geändert** | Zeitpunkt der letzten Bearbeitung |

Die Liste kann über die Volltextsuche nach Begriffen und Übersetzungen durchsucht werden.

## Begriff anlegen

Klicken Sie auf **+ Neuer Begriff**, um einen Eintrag im Wörterbuch zu erstellen:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Begriff** | Der Fachbegriff in der Standardsprache | Ja |
| **Kategorie** | Thematische Zuordnung (z.B. „Technik", „Marketing", „Logistik") | Nein |
| **Definition** | Erläuterung der Bedeutung und des Verwendungskontexts | Nein |
| **Synonyme** | Alternative Bezeichnungen, die denselben Begriff meinen | Nein |

### Übersetzungen hinzufügen

Für jeden Begriff können Sie Übersetzungen in allen konfigurierten Systemsprachen hinterlegen:

1. Öffnen Sie den Begriff in der Detailansicht.
2. Wechseln Sie zum Abschnitt **Übersetzungen**.
3. Geben Sie für jede Sprache die korrekte Übersetzung ein.
4. Speichern Sie die Änderungen.

::: tip Hinweis
Achten Sie darauf, dass Übersetzungen den jeweiligen sprachlichen Konventionen entsprechen. Das Wörterbuch dient als verbindliche Referenz für alle Übersetzer und Redakteure im Team.
:::

## Kategorien

Kategorien helfen Ihnen, das Wörterbuch thematisch zu strukturieren. Sie können beliebig viele Kategorien anlegen und Begriffe einer Kategorie zuordnen.

### Typische Kategorien

| Kategorie | Beispiele |
|---|---|
| **Technik** | Leistung, Spannung, Drehmoment, Schutzklasse |
| **Material** | Edelstahl, Aluminium, Polycarbonat, Baumwolle |
| **Marketing** | Bestseller, Neuheit, Saisonartikel, Premium |
| **Logistik** | Palettenware, Gefahrgut, Sperrgut, Stückgut |
| **Zertifizierung** | CE-Kennzeichnung, TÜV-Siegel, GS-Zeichen |

## Verknüpfung mit Attributen

Wörterbucheinträge können mit Attributen verknüpft werden, um sicherzustellen, dass bei der Datenpflege die korrekte Terminologie verwendet wird.

### Verknüpfung herstellen

1. Navigieren Sie zur Attributverwaltung und öffnen Sie das gewünschte Attribut.
2. Im Feld **Wörterbuchbegriff** wählen Sie den passenden Eintrag aus.
3. Speichern Sie das Attribut.

Verknüpfte Begriffe werden in der Produktdetailansicht als Tooltip neben dem Attributnamen angezeigt. So können Redakteure jederzeit die Definition und die korrekte Übersetzung eines Fachbegriffs nachschlagen, ohne das Wörterbuch separat öffnen zu müssen.

## Konsistente Benennung

Das Wörterbuch unterstützt die konsistente Benennung in mehreren Bereichen:

- **Attributbezeichnungen** -- Verwenden Sie Wörterbuchbegriffe als Grundlage für Attributnamen, um einheitliche Benennungen zu gewährleisten.
- **Wertelisten** -- Orientieren Sie sich beim Erstellen von Wertelisten-Einträgen an den definierten Fachbegriffen.
- **Übersetzungen** -- Das Wörterbuch dient als Referenz für die Produktübersetzung und stellt sicher, dass Fachbegriffe in allen Sprachen konsistent übersetzt werden.
- **Exporte** -- Exportierte Daten verwenden die im Wörterbuch definierten Begriffe als Standard.

::: warning Warnung
Änderungen an einem Wörterbucheintrag wirken sich nicht automatisch auf bestehende Produktdaten aus. Wenn Sie einen Begriff umbenennen, müssen bestehende Texte in Produktattributen manuell angepasst werden.
:::

## Import und Export des Wörterbuchs

Das Wörterbuch kann als CSV-Datei exportiert und importiert werden. Dies ist besonders nützlich, um Begriffe mit externen Übersetzungsdienstleistern auszutauschen.

### Export

1. Klicken Sie auf **Exportieren** in der Wörterbuchübersicht.
2. Wählen Sie die gewünschten Sprachen.
3. Laden Sie die CSV-Datei herunter.

### Import

1. Klicken Sie auf **Importieren**.
2. Laden Sie eine CSV-Datei mit den Spalten „Begriff", „Kategorie" und den jeweiligen Sprachspalten hoch.
3. Prüfen Sie die Vorschau und bestätigen Sie den Import.

## Nächste Schritte

- Erfahren Sie, wie Sie [Attribute](./attribute) definieren und mit Wörterbuchbegriffen verknüpfen.
- Nutzen Sie die [Übersetzungsverwaltung](./uebersetzungen) für die mehrsprachige Produktpflege.
- Verwalten Sie [Einheiten](./einheiten) für technische Massangaben in Ihren Produktdaten.
