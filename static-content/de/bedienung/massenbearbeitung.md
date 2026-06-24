---
title: Massenbearbeitung
---

# Massenbearbeitung

Für die Pflege vieler Produkte auf einmal bietet anyPIM zwei sich ergänzende Werkzeuge: den **Bulk-Editor** für die direkte tabellarische Bearbeitung und die **Massendatenpflege** für regel- bzw. filterbasierte Änderungen mit Vorschau.

## Bulk-Editor

Der Bulk-Editor zeigt mehrere Produkte und ausgewählte Attribute in einer Tabelle (Produkte × Attribute) und erlaubt das direkte Bearbeiten der Werte — vergleichbar mit einer Tabellenkalkulation.

### Ablauf

1. Produkte in der Produktliste auswählen (per Mehrfachauswahl) und den **Bulk-Editor** öffnen.
2. **Schritt 1 — Attribute auswählen:** Festlegen, welche Attribute bearbeitet werden sollen (gruppiert nach Datentyp).
3. **Schritt 2 — Bearbeiten:** Die Tabelle zeigt links die Produkte (SKU/Name) und je Spalte ein Attribut. Werte direkt in den Zellen ändern. Geänderte Zellen werden hervorgehoben, die Fußzeile zeigt die Anzahl vorgemerkter Änderungen.
4. Über die Sprachauswahl lässt sich die Bearbeitungssprache für übersetzbare Attribute umschalten.
5. **„Alle speichern"** schreibt alle Änderungen in einem Vorgang zurück.

::: tip
Der Bulk-Editor arbeitet mit direkten Attributwerten (keine vererbten Werte) und unterstützt übersetzbare Attribute pro Sprache.
:::

## Massendatenpflege

Die Massendatenpflege ändert viele Produkte anhand einer Auswahl oder eines Filters und deckt deutlich mehr Objektarten ab als der Bulk-Editor. Die Operationen sind in Reitern organisiert:

| Reiter | Wirkung |
|---|---|
| **Attribute** | Werte setzen — Modi *Überschreiben*, *Leere füllen* oder *Werte löschen* |
| **Beziehungen** | Produktbeziehungen hinzufügen/entfernen |
| **Ausgabehierarchie** | Knotenzuordnungen hinzufügen/entfernen |
| **Status** | Produktstatus setzen |
| **Master-Hierarchie** | Master-Knoten setzen/entfernen |
| **Hersteller** | Hersteller zuordnen/entfernen |
| **Medien** | Medien zuordnen/entfernen (optional mit Verwendungstyp) |

### Ablauf

1. Produkte auswählen **oder** einen Filter anwenden (filterbasiert).
2. Gewünschte Operationen über die Reiter konfigurieren.
3. **„Vorschau & Prüfen"** zeigt die voraussichtliche Anzahl betroffener Objekte je Operation (Trockenlauf, ohne Änderung).
4. **„Ausführen"** wendet alle Operationen an. Sehr große Mengen werden in Blöcken (Chunks) mit Fortschrittsanzeige verarbeitet.

::: warning
Die Massendatenpflege ist mächtig und wirkt auf viele Produkte gleichzeitig. Nutzen Sie die Vorschau und prüfen Sie das Filterergebnis, bevor Sie ausführen.
:::

## Berechtigungen

Der Bulk-Editor nutzt die Standard-Produktrechte (Produkte ansehen bzw. bearbeiten). Die Massendatenpflege erfordert das gesonderte Recht zur Massenaktualisierung. Welche Rolle diese Werkzeuge nutzen darf, ist unter [Rollen & Berechtigungen](/de/administration/rollen) konfigurierbar.
