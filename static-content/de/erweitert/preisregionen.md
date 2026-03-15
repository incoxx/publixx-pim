---
title: Preisregionen
---

# Preisregionen

Preisregionen im anyPIM ermöglichen die Verwaltung unterschiedlicher Preisstrukturen für verschiedene geografische Märkte. Jede Region kann eine eigene Währung, regionale Preislisten und Preismodifikatoren besitzen. So bilden Sie internationale Preisstrategien strukturiert im System ab.

## Übersicht

Über den Menüpunkt **Preisregionen** in der Sidebar gelangen Sie zur Regionenübersicht:

| Spalte | Beschreibung |
|---|---|
| **Name** | Bezeichnung der Preisregion (z.B. „DACH", „Nordamerika") |
| **Währung** | Zugeordnete Währung (z.B. EUR, USD, CHF) |
| **Produkte mit Preisen** | Anzahl der Produkte, die Preise in dieser Region haben |
| **Modifikatoren** | Anzahl aktiver Preismodifikatoren |
| **Status** | Aktiv oder inaktiv |

## Preisregion anlegen

Klicken Sie auf **+ Neue Preisregion**, um eine Region zu definieren:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Name** | Bezeichnung der Region | Ja |
| **Regionscode** | Eindeutiger Kurzcode (z.B. `dach`, `us`, `uk`) | Ja |
| **Währung** | Primäre Währung der Region | Ja |
| **Beschreibung** | Erläuterung zum Geltungsbereich der Region | Nein |
| **Länder** | Zuordnung von Ländern zur Region (ISO-Codes) | Nein |
| **Status** | Aktiv oder inaktiv | Ja |

::: tip Hinweis
Eine Preisregion kann mehrere Länder umfassen. So können Sie z.B. eine Region „DACH" für Deutschland, Österreich und die Schweiz anlegen und dort Preise in EUR pflegen, während eine separate Region „Schweiz (CHF)" für Preise in Schweizer Franken dient.
:::

## Währungen

Jeder Preisregion wird eine Währung zugeordnet. Das anyPIM unterstützt alle gängigen Währungen gemäss ISO 4217:

| Währung | Code | Symbol |
|---|---|---|
| Euro | EUR | € |
| US-Dollar | USD | $ |
| Schweizer Franken | CHF | CHF |
| Britisches Pfund | GBP | £ |
| Japanischer Yen | JPY | ¥ |
| Schwedische Krone | SEK | kr |
| Polnischer Zloty | PLN | zł |

### Währung einer Region ändern

1. Öffnen Sie die Preisregion in der Detailansicht.
2. Ändern Sie die Währung im Feld **Währung**.
3. Speichern Sie die Region.

::: warning Warnung
Das Ändern der Währung einer Region, in der bereits Preise gepflegt sind, ändert nicht die bestehenden Preisbeträge. Die Werte bleiben numerisch gleich, werden aber fortan in der neuen Währung interpretiert. Prüfen Sie die betroffenen Preise nach einer Währungsänderung.
:::

## Regionale Preislisten

Innerhalb einer Preisregion können Sie Preislisten pflegen, die Produkte mit regionsspezifischen Preisen versehen.

### Preise pflegen

Die Preispflege für eine Region erfolgt in der Produktdetailansicht im Tab **Preise**:

1. Öffnen Sie ein Produkt und wechseln Sie zum Tab **Preise**.
2. Wählen Sie die gewünschte Preisregion aus dem Dropdown.
3. Geben Sie die Preise für die verschiedenen Preisarten ein (Netto, Brutto, UVP etc.).
4. Speichern Sie das Produkt.

| Preisart | Beschreibung |
|---|---|
| **Netto** | Preis ohne Mehrwertsteuer |
| **Brutto** | Preis inklusive Mehrwertsteuer |
| **UVP** | Unverbindliche Preisempfehlung des Herstellers |
| **Einkaufspreis** | Interner Einkaufspreis |
| **Aktionspreis** | Temporärer Sonderpreis |

### Preise im Überblick

Über die Schaltfläche **Preisübersicht** in der Regionendetailansicht können Sie alle Produktpreise einer Region tabellarisch einsehen und filtern. Dies ermöglicht eine schnelle Qualitätskontrolle der regionalen Preisdaten.

## Preismodifikatoren

Preismodifikatoren sind regelbasierte Anpassungen, die auf die Basispreise einer Region angewendet werden. Sie ermöglichen prozentuale Auf- oder Abschläge, ohne jeden Einzelpreis manuell ändern zu müssen.

### Modifikator anlegen

1. Öffnen Sie die gewünschte Preisregion.
2. Wechseln Sie zum Abschnitt **Modifikatoren**.
3. Klicken Sie auf **+ Neuer Modifikator**.

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Name** | Bezeichnung des Modifikators (z.B. „Exportzuschlag") | Ja |
| **Typ** | Prozentual oder absoluter Betrag | Ja |
| **Wert** | Prozentualer Aufschlag/Abschlag oder fester Betrag | Ja |
| **Preisart** | Auf welche Preisart der Modifikator angewendet wird | Ja |
| **Gültig von / bis** | Optionaler Gültigkeitszeitraum | Nein |

### Beispiel

Ein Modifikator „Exportzuschlag" mit dem Wert +15% auf den Nettopreis erhöht alle Nettopreise in der Region um 15 Prozent. Bei einem Basispreis von 100,00 EUR ergibt sich ein Endpreis von 115,00 EUR.

::: danger Achtung
Modifikatoren werden bei der Berechnung in der Reihenfolge ihrer Priorität angewendet. Achten Sie bei mehreren aktiven Modifikatoren darauf, dass die Reihenfolge korrekt konfiguriert ist, um unerwartete Ergebnisse zu vermeiden.
:::

## Preisregionen im Export

Preisregionen können in Exportprofilen als Filterkriterium und als Datenquelle verwendet werden:

- **Regionaler Export** -- Exportieren Sie Produktdaten nur mit den Preisen einer bestimmten Region.
- **Multi-Region-Export** -- Exportieren Sie Preise mehrerer Regionen als separate Spalten.
- **Modifizierte Preise** -- Wählen Sie, ob die Basispreise oder die modifizierten Preise exportiert werden sollen.

## Nächste Schritte

- Erfahren Sie, wie Sie [Preise](../bedienung/preise) auf Produktebene pflegen.
- Konfigurieren Sie [Export-Jobs](./exportjobs) mit regionalen Preisfiltern.
- Nutzen Sie [PDF-Vorlagen](./pdf-vorlagen), um regionale Preislisten als Katalog zu erstellen.
