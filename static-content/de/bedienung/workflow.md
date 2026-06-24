---
title: Workflow
---

# Workflow

Das Workflow-Modul des anyPIM ermöglicht die strukturierte Verwaltung von Aufgaben rund um die Produktdatenpflege. Sie können Aufgaben erstellen, Benutzern zuweisen, deren Fortschritt verfolgen und über Kommentare kommunizieren. Damit koordinieren Sie die Zusammenarbeit im Team und behalten den Überblick über anstehende und erledigte Arbeiten.

## Aufgabenübersicht

Nach Klick auf **Workflow** in der Sidebar gelangen Sie zur Aufgabenübersicht. Diese zeigt alle Aufgaben in einer tabellarischen Darstellung:

| Spalte | Beschreibung |
|---|---|
| **Titel** | Bezeichnung der Aufgabe |
| **Zugewiesen an** | Benutzer, der für die Aufgabe verantwortlich ist |
| **Status** | Aktueller Bearbeitungsstatus |
| **Fällig am** | Geplantes Fertigstellungsdatum |
| **Produkt** | Verknüpftes Produkt (falls vorhanden) |
| **Erstellt** | Zeitpunkt der Aufgabenerstellung |

### Filtern und Sortieren

Oberhalb der Tabelle steht Ihnen eine Filterleiste zur Verfügung:

- **Statusfilter** -- Schränken Sie die Anzeige auf Aufgaben mit einem bestimmten Status ein (offen, in Bearbeitung, erledigt).
- **Benutzerfilter** -- Zeigen Sie nur Aufgaben an, die einem bestimmten Benutzer zugewiesen sind.
- **Fälligkeitsfilter** -- Filtern Sie nach überfälligen Aufgaben oder nach Zeitraum.
- **Volltextsuche** -- Durchsuchen Sie Aufgabentitel und Beschreibungen.

## Aufgabe erstellen

Klicken Sie auf die Schaltfläche **+ Neue Aufgabe**, um eine Aufgabe anzulegen. Füllen Sie die folgenden Felder aus:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Titel** | Kurze, aussagekräftige Bezeichnung der Aufgabe | Ja |
| **Beschreibung** | Detaillierte Beschreibung der zu erledigenden Arbeit | Nein |
| **Zugewiesen an** | Auswahl des verantwortlichen Benutzers | Ja |
| **Fällig am** | Datum, bis zu dem die Aufgabe erledigt sein soll | Nein |
| **Produkt** | Optionale Verknüpfung mit einem Produkt | Nein |
| **Priorität** | Einstufung der Dringlichkeit (niedrig, normal, hoch) | Ja |

::: tip Hinweis
Wenn Sie eine Aufgabe aus der Produktdetailansicht heraus erstellen, wird das Produkt automatisch verknüpft. So sparen Sie sich die manuelle Zuordnung.
:::

## Aufgabenstatus

Jede Aufgabe durchläuft einen definierten Statusworkflow:

```
┌──────────┐      ┌────────────────┐      ┌──────────┐
│  Offen   │ ───> │ In Bearbeitung │ ───> │ Geschlossen │
│          │      │                │      │          │
└──────────┘      └────────────────┘      └──────────┘
```

| Status | Beschreibung |
|---|---|
| **Offen** | Die Aufgabe wurde erstellt, aber noch nicht begonnen |
| **In Bearbeitung** | Die Aufgabe wird aktiv bearbeitet |
| **Geschlossen** | Die Aufgabe wurde abgeschlossen |

Der Status kann jederzeit manuell geändert werden. Ein Wechsel von „Geschlossen" zurück zu „Offen" ist möglich, falls eine Aufgabe erneut bearbeitet werden muss.

### Fälligkeitsanzeige

Aufgaben mit einem Fälligkeitsdatum werden farblich gekennzeichnet:

- **Grün** -- Die Aufgabe ist innerhalb des Zeitrahmens.
- **Gelb** -- Die Aufgabe ist in den nächsten 48 Stunden fällig.
- **Rot** -- Die Aufgabe ist überfällig.

## Aufgabenkommentare

Innerhalb einer Aufgabe können Sie Kommentare hinterlassen, um den Fortschritt zu dokumentieren oder Rückfragen zu stellen.

### Kommentar hinzufügen

1. Öffnen Sie die Aufgabendetailansicht durch Klick auf den Aufgabentitel.
2. Scrollen Sie zum Kommentarbereich am unteren Rand.
3. Geben Sie Ihren Kommentar in das Textfeld ein.
4. Klicken Sie auf **Kommentar senden**.

Kommentare werden chronologisch angezeigt und enthalten den Namen des Verfassers sowie den Zeitstempel. Alle an der Aufgabe beteiligten Benutzer werden über neue Kommentare benachrichtigt.

## Massenaufgaben aus Produktlisten

Für die effiziente Erstellung mehrerer Aufgaben auf Basis einer Produktliste steht Ihnen die Funktion **Massenaufgaben** zur Verfügung.

### Massenaufgaben erstellen

1. Navigieren Sie zur **Produktliste** und wählen Sie die gewünschten Produkte über die Checkboxen aus.
2. Klicken Sie auf **Aktionen** > **Aufgaben erstellen**.
3. Füllen Sie das Aufgabenformular aus (Titel, Beschreibung, Zugewiesen an, Fällig am).
4. Bestätigen Sie mit **Aufgaben erstellen**.

Das System erstellt für jedes ausgewählte Produkt eine separate Aufgabe mit den angegebenen Daten. Der Titel wird automatisch um die SKU des jeweiligen Produkts ergänzt.

::: warning Warnung
Bei einer grossen Anzahl ausgewählter Produkte kann die Erstellung der Aufgaben einige Sekunden dauern. Schliessen Sie das Fenster nicht, bis der Vorgang abgeschlossen ist.
:::

## Benachrichtigungen

Das Workflow-Modul löst in folgenden Situationen Benachrichtigungen aus:

| Ereignis | Empfänger |
|---|---|
| Aufgabe zugewiesen | Zugewiesener Benutzer |
| Status geändert | Ersteller und zugewiesener Benutzer |
| Neuer Kommentar | Alle beteiligten Benutzer |
| Aufgabe überfällig | Zugewiesener Benutzer und Ersteller |

Benachrichtigungen werden als In-App-Benachrichtigungen angezeigt. Die Konfiguration von E-Mail-Benachrichtigungen erfolgt in den Benutzereinstellungen.

## Nächste Schritte

- Verwalten Sie [Benutzer](./benutzer) und deren Berechtigungen für die Aufgabenzuweisung.
- Nutzen Sie das [Dashboard](./dashboard), um Ihre offenen Aufgaben im Blick zu behalten.
- Erstellen Sie [Berichte](../erweitert/berichte) über den Aufgabenfortschritt in Ihrem Team.
