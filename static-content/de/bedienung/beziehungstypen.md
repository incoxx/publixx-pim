---
title: Beziehungstypen
---

# Beziehungstypen

Beziehungstypen definieren die Arten von Verknüpfungen, die zwischen Produkten im anyPIM hergestellt werden können. Typische Beziehungen sind Cross-Sell, Up-Sell, Zubehör oder Ersatzteile. Durch die Definition eigener Beziehungstypen passen Sie das Relationssystem an die Anforderungen Ihres Produktsortiments an.

## Übersicht der Beziehungstypen

Über den Menüpunkt **Beziehungstypen** in der Sidebar gelangen Sie zur Verwaltungsübersicht. Dort sehen Sie alle definierten Beziehungstypen in einer tabellarischen Darstellung:

| Spalte | Beschreibung |
|---|---|
| **Name** | Bezeichnung des Beziehungstyps |
| **Technischer Name** | Eindeutiger Systembezeichner (snake_case) |
| **Richtung** | Bidirektional oder unidirektional |
| **Anzahl Relationen** | Wie viele Produktverknüpfungen diesen Typ verwenden |

## Beziehungstyp anlegen

Klicken Sie auf **+ Neuer Beziehungstyp**, um einen neuen Typ zu definieren:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Technischer Name** | Eindeutiger Bezeichner in snake_case (z.B. `cross_sell`) | Ja |
| **Anzeigename (DE)** | Lesbare Bezeichnung in Deutsch (z.B. „Querverkauf") | Ja |
| **Anzeigename (EN)** | Lesbare Bezeichnung in Englisch (z.B. „Cross-Sell") | Ja |
| **Richtung** | Bidirektional oder unidirektional | Ja |
| **Beschreibung** | Erläuterung des Verwendungszwecks | Nein |

### Richtung von Beziehungen

Die Richtung bestimmt, wie eine Verknüpfung zwischen zwei Produkten wirkt:

- **Unidirektional** -- Die Beziehung gilt nur in eine Richtung. Wenn Produkt A als Zubehör von Produkt B definiert wird, hat Produkt B keine automatische Rückverknüpfung zu Produkt A.
- **Bidirektional** -- Die Beziehung gilt in beide Richtungen. Wenn Produkt A als Cross-Sell von Produkt B definiert wird, erscheint Produkt B automatisch auch als Cross-Sell bei Produkt A.

::: tip Hinweis
Wählen Sie die Richtung sorgfältig, da sie das Verhalten im gesamten System beeinflusst. Typische bidirektionale Beziehungen sind „Cross-Sell" und „Ähnliche Produkte". Typische unidirektionale Beziehungen sind „Zubehör" und „Ersatzteil".
:::

## Vordefinierte Beziehungstypen

Das anyPIM liefert standardmässig folgende Beziehungstypen aus, die Sie an Ihre Bedürfnisse anpassen oder ergänzen können:

| Beziehungstyp | Richtung | Beschreibung |
|---|---|---|
| **Cross-Sell** | Bidirektional | Verwandte Produkte für den Querverkauf |
| **Up-Sell** | Unidirektional | Höherwertige Alternativen zum aktuellen Produkt |
| **Zubehör** | Unidirektional | Ergänzende Produkte, die zum Hauptprodukt passen |
| **Ersatzteil** | Unidirektional | Ersatzteile und Verschleissteile für das Hauptprodukt |
| **Ähnliche Produkte** | Bidirektional | Produkte mit vergleichbaren Eigenschaften |
| **Set-Bestandteil** | Unidirektional | Produkte, die Teil eines Sets oder Bundles sind |

## Relationen zwischen Produkten zuweisen

Die Zuweisung von Relationen erfolgt in der Produktdetailansicht im Tab **Relationen**:

1. Öffnen Sie das gewünschte Produkt.
2. Wechseln Sie zum Tab **Relationen**.
3. Klicken Sie auf **+ Relation hinzufügen**.
4. Wählen Sie den gewünschten Beziehungstyp aus dem Dropdown.
5. Suchen Sie das Zielprodukt über SKU oder Name.
6. Bestätigen Sie die Zuordnung.

### Relationen verwalten

Im Tab **Relationen** werden alle bestehenden Verknüpfungen nach Beziehungstyp gruppiert angezeigt. Für jede Relation sehen Sie:

- **Zielprodukt** -- SKU und Name des verknüpften Produkts.
- **Beziehungstyp** -- Art der Verknüpfung.
- **Entfernen** -- Schaltfläche zum Aufheben der Relation.

::: warning Warnung
Beim Löschen eines Beziehungstyps werden alle bestehenden Relationen dieses Typs unwiderruflich entfernt. Prüfen Sie vor dem Löschen die Anzahl der betroffenen Verknüpfungen.
:::

## Beziehungstypen in Exporten

Produktrelationen können in Exportprofilen berücksichtigt werden. Je nach Exportformat werden die verknüpften Produkte als separate Spalten, als kommagetrennte Liste oder als verschachtelte Struktur (JSON/XML) ausgegeben.

Die Konfiguration der Relationsausgabe erfolgt im jeweiligen Exportprofil unter dem Abschnitt **Relationen**. Dort legen Sie fest, welche Beziehungstypen exportiert werden und in welchem Format die verknüpften Produkt-SKUs erscheinen.

## Nächste Schritte

- Erfahren Sie, wie Sie [Produkte](./produkte) anlegen und mit Relationen verknüpfen.
- Nutzen Sie die [Merkliste](./merkliste), um Produkte für die spätere Verknüpfung vorzumerken.
- Konfigurieren Sie [Export-Jobs](../erweitert/exportjobs), um Produktrelationen automatisiert zu exportieren.
