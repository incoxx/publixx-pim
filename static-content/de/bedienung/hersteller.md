---
title: Hersteller
---

# Hersteller

Die Herstellerverwaltung im anyPIM ermöglicht die zentrale Pflege von Herstellerstammdaten. Hersteller können Produkten zugeordnet werden, um die Herkunft der Artikel zu dokumentieren und eine gezielte Filterung nach Marken oder Lieferanten zu ermöglichen.

## Herstellerübersicht

Über den Menüpunkt **Hersteller** in der Sidebar gelangen Sie zur Herstellerübersicht. Diese zeigt alle angelegten Hersteller in einer tabellarischen Liste:

| Spalte | Beschreibung |
|---|---|
| **Name** | Bezeichnung des Herstellers |
| **Logo** | Vorschau des Hersteller-Logos (falls hinterlegt) |
| **Website** | URL der Hersteller-Website |
| **Produkte** | Anzahl der zugeordneten Produkte |
| **Erstellt** | Zeitpunkt der Anlage |

Die Liste kann über die Volltextsuche nach Herstellernamen durchsucht und nach jeder Spalte sortiert werden.

## Hersteller anlegen

Klicken Sie auf die Schaltfläche **+ Neuer Hersteller**, um einen neuen Hersteller im System zu erfassen. Füllen Sie die folgenden Felder aus:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Name** | Offizielle Bezeichnung des Herstellers | Ja |
| **Logo** | Bilddatei mit dem Hersteller-Logo (JPG, PNG, SVG) | Nein |
| **Website** | URL der Hersteller-Website | Nein |
| **E-Mail** | Kontakt-E-Mail-Adresse | Nein |
| **Telefon** | Telefonnummer des Herstellers | Nein |
| **Anschrift** | Postanschrift des Herstellers | Nein |
| **Beschreibung** | Freitext zur Beschreibung des Herstellers | Nein |

Nach dem Speichern steht der Hersteller zur Zuordnung an Produkte bereit.

::: tip Hinweis
Das Logo wird in der Medienablage des anyPIM gespeichert und kann in Exporten und PDF-Katalogen als Herstellerkennzeichnung verwendet werden.
:::

## Hersteller bearbeiten

Klicken Sie in der Herstellerübersicht auf den Namen eines Herstellers, um dessen Detailansicht zu öffnen. Dort können Sie alle Stammdaten bearbeiten und das Logo austauschen.

### Kontaktdaten verwalten

Im Abschnitt **Kontakt** der Detailansicht pflegen Sie die Kontaktinformationen des Herstellers:

- **Primäre E-Mail-Adresse** -- Haupt-Kontaktadresse für die geschäftliche Kommunikation.
- **Telefonnummer** -- Inklusive internationaler Vorwahl.
- **Anschrift** -- Vollständige Postanschrift mit Strasse, PLZ, Ort und Land.

Diese Daten dienen der internen Dokumentation und können optional in Exporte einbezogen werden.

## Hersteller Produkten zuordnen

Die Zuordnung eines Herstellers zu einem Produkt erfolgt in der Produktdetailansicht:

1. Öffnen Sie das gewünschte Produkt.
2. Navigieren Sie zum Feld **Hersteller** (in der Regel in der Attributgruppe „Stammdaten").
3. Wählen Sie den Hersteller aus dem Dropdown-Menü aus.
4. Speichern Sie das Produkt.

Ein Produkt kann genau einem Hersteller zugeordnet werden. Um den Hersteller zu ändern, wählen Sie einfach einen anderen Eintrag aus dem Dropdown.

### Zuordnung aufheben

Um die Herstellerzuordnung eines Produkts zu entfernen, setzen Sie das Feld **Hersteller** auf den leeren Standardwert und speichern Sie das Produkt.

## Produkte nach Hersteller filtern

In der Produktliste können Sie Produkte gezielt nach Hersteller filtern:

1. Öffnen Sie die **Produktliste**.
2. Klicken Sie in der Filterleiste auf **Hersteller**.
3. Wählen Sie einen oder mehrere Hersteller aus der Liste.
4. Die Produktliste zeigt nur noch Produkte der gewählten Hersteller.

Dieser Filter lässt sich mit allen anderen Filtern (Status, Produkttyp, Volltextsuche) kombinieren, um die Auswahl weiter einzugrenzen.

::: warning Warnung
Das Löschen eines Herstellers ist nur möglich, wenn keine Produkte mehr zugeordnet sind. Entfernen Sie zunächst alle Produktzuordnungen oder weisen Sie die betroffenen Produkte einem anderen Hersteller zu.
:::

## Hersteller im Export

Herstellerdaten können in Exportprofilen als Felder eingebunden werden. Folgende Herstellerinformationen stehen als Exportfelder zur Verfügung:

| Feld | Beschreibung |
|---|---|
| **Herstellername** | Offizielle Bezeichnung |
| **Hersteller-Logo (URL)** | Link zum hinterlegten Logo |
| **Hersteller-Website** | URL der Website |
| **Hersteller-E-Mail** | Kontakt-E-Mail-Adresse |

Diese Felder können in Exportprofilen und PDF-Vorlagen als Platzhalter verwendet werden.

## Nächste Schritte

- Erfahren Sie, wie Sie [Produkte](./produkte) anlegen und mit Herstellern verknüpfen.
- Nutzen Sie [Beziehungstypen](./beziehungstypen), um Produktrelationen zu definieren.
- Erstellen Sie [PDF-Vorlagen](../erweitert/pdf-vorlagen) mit Herstellerangaben für Ihre Kataloge.
