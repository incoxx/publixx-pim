---
title: Planungskalender
---

# Planungskalender

Der Planungskalender im anyPIM bietet eine visuelle Zeitachse für geplante Aktionen und Termine rund um Ihre Produktdaten. Er vereint Export-Jobs, Veröffentlichungstermine und benutzerdefinierte Ereignisse in einer übersichtlichen Kalenderansicht, sodass Sie alle zeitgesteuerten Aktivitäten auf einen Blick erfassen können.

## Kalenderansicht

Über den Menüpunkt **Planungskalender** in der Sidebar öffnen Sie die Kalenderansicht. Der Kalender unterstützt verschiedene Darstellungsformen:

| Ansicht | Beschreibung |
|---|---|
| **Monatsansicht** | Überblick über den gesamten Monat mit allen Ereignissen als kompakte Karten |
| **Wochenansicht** | Detaillierte Darstellung einer Kalenderwoche mit Zeitachse |
| **Tagesansicht** | Stundenweise Aufteilung eines einzelnen Tages |
| **Zeitleiste** | Horizontale Timeline mit konfigurierbarem Zeitraum |

Wechseln Sie zwischen den Ansichten über die Schaltflächen im Kopfbereich des Kalenders. Über die Navigationspfeile blättern Sie zum vorherigen oder nächsten Zeitraum.

## Ereignistypen

Der Planungskalender unterscheidet verschiedene Kategorien von Ereignissen, die farblich kodiert dargestellt werden:

| Typ | Farbe | Beschreibung |
|---|---|---|
| **Export-Job** | Blau | Geplante oder wiederkehrende Datenexporte |
| **Veröffentlichung** | Grün | Produktveröffentlichungen oder Katalogaktualisierungen |
| **Aufgabe** | Orange | Fällige Workflow-Aufgaben |
| **Benutzerdefiniert** | Violett | Manuell angelegte Termine und Erinnerungen |

### Ereignisse filtern

Über die Filterleiste oberhalb des Kalenders können Sie die Darstellung nach Ereignistyp, beteiligtem Benutzer oder verknüpftem Exportprofil einschränken. Aktive Filter werden als Chips angezeigt und können einzeln deaktiviert werden.

## Ereignisse anlegen

Klicken Sie auf einen Tag oder Zeitslot im Kalender oder verwenden Sie die Schaltfläche **+ Neues Ereignis**:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Titel** | Kurze Bezeichnung des Ereignisses | Ja |
| **Typ** | Kategorie des Ereignisses (siehe Ereignistypen) | Ja |
| **Startdatum** | Beginn des Ereignisses | Ja |
| **Enddatum** | Ende des Ereignisses (bei mehrtägigen Ereignissen) | Nein |
| **Uhrzeit** | Genaue Uhrzeit (optional, z.B. für geplante Exporte) | Nein |
| **Beschreibung** | Detailbeschreibung oder Hinweise | Nein |
| **Verknüpfung** | Optionale Verknüpfung mit einem Export-Job oder einer Aufgabe | Nein |

::: tip Hinweis
Export-Jobs, die über das Modul [Export-Jobs](./exportjobs) mit einem Zeitplan konfiguriert wurden, erscheinen automatisch im Planungskalender. Sie müssen diese nicht manuell anlegen.
:::

## Drag-and-Drop

Ereignisse im Kalender können per Drag-and-Drop verschoben werden:

- **Innerhalb einer Ansicht verschieben** -- Ziehen Sie ein Ereignis auf einen anderen Tag oder Zeitslot, um das Datum zu ändern.
- **Dauer anpassen** -- In der Wochen- und Tagesansicht können Sie den unteren Rand eines Ereignisses ziehen, um die Dauer zu verlängern oder zu verkürzen.

Änderungen, die per Drag-and-Drop vorgenommen werden, werden sofort gespeichert. Bei Ereignissen, die mit einem Export-Job verknüpft sind, wird der Zeitplan des Export-Jobs automatisch aktualisiert.

::: warning Warnung
Das Verschieben eines mit einem Export-Job verknüpften Ereignisses ändert den Ausführungszeitpunkt des Jobs. Stellen Sie sicher, dass der neue Zeitpunkt mit Ihren Prozessen vereinbar ist.
:::

## Verknüpfung mit Export-Jobs

Der Planungskalender ist eng mit dem Modul [Export-Jobs](./exportjobs) verzahnt. Folgende Verknüpfungen bestehen:

- **Automatische Darstellung** -- Alle zeitplangesteuerten Export-Jobs werden als blaue Ereignisse im Kalender angezeigt.
- **Direkte Navigation** -- Ein Klick auf ein Export-Ereignis öffnet die Detailansicht des zugehörigen Export-Jobs.
- **Statusanzeige** -- Bereits ausgeführte Ereignisse zeigen den Ausführungsstatus (erfolgreich, fehlgeschlagen) als Symbol an.

## Visuelle Zeitleiste

Die Zeitleistenansicht eignet sich besonders für die Planung über einen längeren Zeitraum hinweg. Sie zeigt Ereignisse als horizontale Balken entlang einer Zeitachse:

### Konfiguration der Zeitleiste

1. Wechseln Sie zur Ansicht **Zeitleiste**.
2. Definieren Sie den darzustellenden Zeitraum (z.B. aktuelles Quartal, nächste 6 Monate).
3. Gruppieren Sie die Darstellung optional nach Ereignistyp oder Benutzer.

Die Zeitleiste bietet eine kompakte Übersicht über alle geplanten Aktivitäten und eignet sich für die strategische Planung von Produktveröffentlichungen und Exportzyklen.

## Kalenderfreigaben

Sie können Ihren Planungskalender für andere Benutzer freigeben oder als iCal-Feed in externe Kalenderanwendungen einbinden:

1. Klicken Sie auf das Freigabesymbol im Kopfbereich des Kalenders.
2. Wählen Sie die Art der Freigabe (interne Freigabe oder iCal-URL).
3. Kopieren Sie die generierte URL oder wählen Sie die Benutzer für die interne Freigabe aus.

::: danger Achtung
Die iCal-URL enthält einen persönlichen Zugriffsschlüssel. Geben Sie diese URL nicht an unbefugte Personen weiter, da sie Zugriff auf Ihre Kalenderinformationen gewährt.
:::

## Nächste Schritte

- Konfigurieren Sie [Export-Jobs](./exportjobs) mit Zeitplan, um sie im Kalender zu sehen.
- Nutzen Sie den [Workflow](../bedienung/workflow), um Aufgaben mit Fälligkeitsterminen im Kalender darzustellen.
- Erstellen Sie [Berichte](./berichte) nach Zeitplan, die ebenfalls im Kalender erscheinen.
