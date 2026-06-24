---
title: Varianten & Versionen
---

# Varianten & Versionen

In der Produktdetailansicht stehen zwei verwandte, aber unterschiedliche Funktionen zur Verfügung: **Varianten** für Produktausprägungen (z. B. Farbe/Größe) und **Versionen** für die zeitliche Versionierung eines Produkts.

## Varianten

Varianten sind Kindprodukte, die einem Elternprodukt zugeordnet sind. Sie übernehmen den Produkttyp des Elternprodukts, haben aber eine eigene SKU, einen eigenen Namen, EAN und Status und können Attributwerte je nach Vererbungsregel erben oder überschreiben.

Die Funktionen liegen im Reiter **„Varianten"** des Produkts (sichtbar, wenn der Produkttyp Varianten erlaubt):

- **Neue Variante** — einzelne Variante mit SKU, Name, EAN und Status anlegen.
- **Varianten generieren** — über den *Variantengenerator* mehrere Dimensionen (z. B. Auswahl-Attribute) auswählen und alle Kombinationen automatisch erzeugen (z. B. 3 Farben × 4 Größen = 12 Varianten). SKU-Präfix und Startstatus sind einstellbar; bereits vorhandene SKUs werden übersprungen.
- **Varianten-Attribute** — pro Attribut festlegen, ob es vom Elternprodukt geerbt (*inherit*) oder eigenständig gepflegt (*override*) wird.

::: tip
Damit ein Attribut im Generator als Dimension auswählbar ist, muss es als Varianten-Attribut markiert sein.
:::

## Versionen

Versionen sind unveränderliche Momentaufnahmen des Produktzustands (Basisfelder + alle Attributwerte). Sie dienen der Nachvollziehbarkeit, der geplanten Veröffentlichung und dem Zurücksetzen. Die Funktionen liegen im Reiter **„Versionen"**.

### Lebenszyklus

```
Entwurf ──aktivieren──► Aktiv ──(durch neue aktive Version)──► Archiviert
   │
   └──planen (Veröffentlich am)──► Geplant ──(automatisch)──► Aktiv
```

- **Version erstellen** — legt einen *Entwurf* an; optional mit *Änderungsgrund*.
- **Aktivieren** — veröffentlicht eine Version; sie wird zum aktuellen Zustand.
- **Zeitplan** — Veröffentlichung auf einen Zeitpunkt (*Veröffentlich am*) legen; die Version wird automatisch aktiviert. Über *Geplante Freigabe aufheben* zurückzunehmen.
- **Zurücksetzen** — eine *archivierte* Version wiederherstellen; dabei wird eine neue aktive Version erzeugt.
- **Vergleichen** — zwei Versionen oder eine Version mit dem *aktuellen Zustand* gegenüberstellen (Diff der Basisfelder und Attributwerte).

::: tip Geplante Versionen & Kalender
Geplante Veröffentlichungen erscheinen auch im [Planungskalender](/de/erweitert/planungskalender) neben geplanten Aktionen und Export-Jobs.
:::

## Berechtigungen

Beide Funktionen sind über das Berechtigungssystem und (bei Varianten) über Tab-Zugriffsrechte geschützt. Die Konfiguration erfolgt unter [Rollen & Berechtigungen](/de/administration/rollen).
