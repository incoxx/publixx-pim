---
title: Einheiten
---

# Einheiten

Das Einheitensystem im anyPIM ermöglicht die strukturierte Verwaltung physikalischer und kaufmännischer Masseinheiten. Einheiten werden in Gruppen organisiert und Attributen zugeordnet, sodass Werte mit der passenden Masseinheit erfasst und bei Bedarf automatisch umgerechnet werden können.

## Einheitengruppen

Einheitengruppen fassen thematisch zusammengehörige Einheiten zusammen. Jede Gruppe besitzt eine Basiseinheit, auf die sich die Umrechnungsfaktoren aller anderen Einheiten der Gruppe beziehen.

### Übersicht

Über den Menüpunkt **Einheiten** in der Sidebar gelangen Sie zur Gruppenübersicht:

| Spalte | Beschreibung |
|---|---|
| **Name** | Bezeichnung der Einheitengruppe (z.B. „Gewicht", „Länge") |
| **Basiseinheit** | Die Referenzeinheit der Gruppe (Faktor 1) |
| **Anzahl Einheiten** | Wie viele Einheiten in der Gruppe enthalten sind |
| **Verwendung** | Anzahl der Attribute, die diese Gruppe nutzen |

### Einheitengruppe anlegen

1. Klicken Sie auf **+ Neue Einheitengruppe**.
2. Vergeben Sie einen Namen (z.B. „Volumen").
3. Speichern Sie die Gruppe.
4. Fügen Sie anschliessend die zugehörigen Einheiten hinzu.

## Einheiten verwalten

Innerhalb einer Einheitengruppe definieren Sie die einzelnen Einheiten mit ihren Umrechnungsfaktoren.

### Einheit hinzufügen

Öffnen Sie eine Einheitengruppe und klicken Sie auf **+ Neue Einheit**. Füllen Sie die folgenden Felder aus:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Name** | Vollständiger Name der Einheit (z.B. „Kilogramm") | Ja |
| **Kürzel** | Abkürzung der Einheit (z.B. „kg") | Ja |
| **Umrechnungsfaktor** | Faktor relativ zur Basiseinheit | Ja |
| **Basiseinheit** | Markiert diese Einheit als Basiseinheit der Gruppe (Faktor = 1) | Nein |

::: tip Hinweis
Der Umrechnungsfaktor gibt an, wie viele Basiseinheiten einer Einheit entsprechen. Beispiel: Wenn Kilogramm die Basiseinheit ist, hat Gramm den Faktor 0,001 (1 g = 0,001 kg).
:::

### Standardeinheitengruppen

Das anyPIM liefert häufig benötigte Einheitengruppen als Vorlagen mit:

#### Gewicht

| Einheit | Kürzel | Faktor |
|---|---|---|
| Kilogramm | kg | 1 (Basis) |
| Gramm | g | 0,001 |
| Milligramm | mg | 0,000001 |
| Tonne | t | 1000 |
| Pfund (lb) | lb | 0,453592 |
| Unze (oz) | oz | 0,028350 |

#### Länge

| Einheit | Kürzel | Faktor |
|---|---|---|
| Meter | m | 1 (Basis) |
| Zentimeter | cm | 0,01 |
| Millimeter | mm | 0,001 |
| Kilometer | km | 1000 |
| Zoll (inch) | in | 0,0254 |
| Fuss (foot) | ft | 0,3048 |

#### Volumen

| Einheit | Kürzel | Faktor |
|---|---|---|
| Liter | l | 1 (Basis) |
| Milliliter | ml | 0,001 |
| Kubikmeter | m³ | 1000 |
| Kubikzentimeter | cm³ | 0,001 |
| Gallone (US) | gal | 3,78541 |

#### Fläche

| Einheit | Kürzel | Faktor |
|---|---|---|
| Quadratmeter | m² | 1 (Basis) |
| Quadratzentimeter | cm² | 0,0001 |
| Quadratmillimeter | mm² | 0,000001 |
| Quadratkilometer | km² | 1000000 |

## Basiseinheit festlegen

Jede Einheitengruppe besitzt genau eine Basiseinheit. Diese dient als Referenz für alle Umrechnungen. Um die Basiseinheit zu ändern:

1. Öffnen Sie die Einheitengruppe.
2. Klicken Sie bei der gewünschten Einheit auf **Als Basiseinheit festlegen**.
3. Das System berechnet die Umrechnungsfaktoren aller anderen Einheiten automatisch neu.

::: danger Achtung
Das Ändern der Basiseinheit führt zur Neuberechnung aller Umrechnungsfaktoren. Bestehende Produktdaten bleiben unverändert, die Anzeige der Werte kann sich jedoch ändern. Prüfen Sie nach einer Änderung stichprobenartig die betroffenen Produkte.
:::

## Einheiten Attributen zuordnen

Einheitengruppen werden Attributen vom Datentyp **Float** zugeordnet. Dadurch wird neben dem Eingabefeld ein Dropdown mit den verfügbaren Einheiten der Gruppe angezeigt.

### Zuordnung in der Attributverwaltung

1. Navigieren Sie zu **Attribute** und öffnen Sie das gewünschte Attribut.
2. Wählen Sie im Feld **Einheitengruppe** die passende Gruppe aus.
3. Speichern Sie das Attribut.

In der Produktdetailansicht erscheint nun neben dem Zahlenfeld ein Dropdown, über das der Benutzer die Einheit auswählen kann. Der eingegebene Wert wird intern zusammen mit der gewählten Einheit gespeichert.

## Umrechnung

Bei der Datenpflege und im Export kann das anyPIM Werte automatisch zwischen Einheiten einer Gruppe umrechnen. Dies ist besonders nützlich, wenn verschiedene Kanäle unterschiedliche Einheiten erfordern (z.B. Zentimeter für den deutschen Shop, Inches für den US-Shop).

Die Umrechnung erfolgt über den Umrechnungsfaktor:

```
Zielwert = Quellwert × (Quellfaktor / Zielfaktor)
```

## Nächste Schritte

- Erfahren Sie, wie Sie [Attribute](./attribute) mit Einheitengruppen verknüpfen.
- Nutzen Sie [Export-Jobs](../erweitert/exportjobs), um Werte in der gewünschten Einheit auszugeben.
- Lernen Sie das [Wörterbuch](./woerterbuch) kennen, um Ihre Terminologie konsistent zu halten.
