---
title: Preise
---

# Preise

Die Preisverwaltung des Publixx PIM ermöglicht die zentrale Pflege von Produktpreisen in verschiedenen Preisarten und Währungen. Dieses Kapitel beschreibt die Konfiguration von Preisarten, die Erfassung von Preisen pro Produkt sowie die Verwaltung von Gültigkeitszeiträumen.

## Preisarten

Preisarten definieren die verschiedenen Kategorien von Preisen, die im System gepflegt werden können. Die Verwaltung der Preisarten erreichen Sie über den Menüpunkt **Preise** in der Sidebar.

### Standardmäßige Preisarten

Das System unterstützt beliebig viele Preisarten. Typische Konfigurationen umfassen:

| Preisart | Beschreibung | Typische Verwendung |
|---|---|---|
| **Listenpreis** (UVP) | Unverbindliche Preisempfehlung | Katalog, Online-Shop |
| **Nettopreis** | Preis ohne Mehrwertsteuer | B2B-Handel, Großkunden |
| **Bruttopreis** | Preis inkl. Mehrwertsteuer | B2C-Endkundenpreise |
| **Aktionspreis** | Zeitlich begrenzter Sonderpreis | Werbeaktionen, Sales |
| **Staffelpreis** | Mengenabhängiger Preis | Großbestellungen |
| **Einkaufspreis** | Interner Beschaffungspreis | Kalkulation, Controlling |

### Preisart anlegen

1. Navigieren Sie zur Preisarten-Verwaltung.
2. Klicken Sie auf **+ Neue Preisart**.
3. Vergeben Sie einen technischen Namen und Anzeigenamen (DE/EN).
4. Speichern Sie die Preisart.

::: tip Hinweis
Preisarten sind systemweit definiert und gelten für alle Produkte. Die tatsächliche Preispflege erfolgt auf Produktebene.
:::

## Währungen

Das Publixx PIM unterstützt **mehrere Währungen** gleichzeitig. Für jeden Preis wird die Währung als ISO-4217-Code gespeichert (z.B. EUR, USD, CHF, GBP).

### Verfügbare Währungen

Die im System konfigurierten Währungen werden bei der Preiserfassung als Auswahlfeld angeboten. Typische Konfigurationen:

| Code | Währung | Symbol |
|---|---|---|
| EUR | Euro | EUR |
| USD | US-Dollar | $ |
| CHF | Schweizer Franken | CHF |
| GBP | Britisches Pfund | £ |

Die verfügbaren Währungen werden in der Systemkonfiguration definiert.

## Preispflege pro Produkt

Die Preiserfassung erfolgt in der **Produktdetailansicht** im Tab **Preise**. Dort sehen Sie eine tabellarische Übersicht aller erfassten Preise für das aktuelle Produkt.

### Preis anlegen

1. Öffnen Sie die Produktdetailansicht und wechseln Sie zum Tab **Preise**.
2. Klicken Sie auf **+ Preis hinzufügen**.
3. Füllen Sie das Preisformular aus:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Preisart** | Auswahl der Preisart (z.B. Listenpreis) | Ja |
| **Betrag** | Numerischer Preiswert | Ja |
| **Währung** | ISO-4217-Währungscode | Ja |
| **Gültig ab** | Beginn der Gültigkeit | Nein |
| **Gültig bis** | Ende der Gültigkeit | Nein |

4. Speichern Sie den Preis.

### Gültigkeitszeiträume

Preise können mit einem **Gültigkeitszeitraum** versehen werden, der über die Felder „Gültig ab" und „Gültig bis" definiert wird:

- **Ohne Gültigkeit** -- Der Preis gilt unbefristet.
- **Mit Startdatum** -- Der Preis gilt ab dem angegebenen Datum.
- **Mit Enddatum** -- Der Preis gilt bis zum angegebenen Datum.
- **Mit Start- und Enddatum** -- Der Preis gilt nur innerhalb des definierten Zeitraums.

Gültigkeitszeiträume sind besonders nützlich für **Aktionspreise** und saisonale Preisanpassungen. Beim Export können Sie steuern, ob nur aktuell gültige Preise oder alle Preise exportiert werden.

::: info Beispiel
Ein Produkt hat einen Listenpreis von 49,99 EUR (ohne Gültigkeit) und einen Aktionspreis von 39,99 EUR (gültig vom 01.12. bis 24.12.). Beim Export am 15.12. werden beide Preise geliefert. Der Aktionspreis kann vom Zielsystem als vorrangig behandelt werden.
:::

### Preis bearbeiten

Klicken Sie auf einen bestehenden Preis in der Tabelle, um ihn zu bearbeiten. Sie können Betrag, Währung und Gültigkeitszeitraum ändern. Die Preisart ist nach der Anlage nicht mehr änderbar -- löschen Sie in diesem Fall den Preis und legen Sie einen neuen an.

### Preis löschen

Klicken Sie auf das Löschen-Symbol neben einem Preis, um ihn zu entfernen. Die Löschung erfolgt nach einer Bestätigungsabfrage.

## Preisübersicht (Preisverwaltung)

Über den Menüpunkt **Preise** in der Sidebar erreichen Sie die globale Preisübersicht. Diese zeigt eine produktübergreifende Tabelle aller erfassten Preise und bietet folgende Funktionen:

- **Filtern** nach Preisart, Währung oder Gültigkeitsstatus
- **Suchen** nach Produkt-SKU oder Produktname
- **Sortieren** nach Betrag, Preisart, Produkt oder Gültigkeitsdatum
- **Export** der Preistabelle für externe Verarbeitung

## Preise und Varianten

Varianten erben Preise **nicht** vom Elternprodukt. Jede Variante hat ihre eigene Preisstruktur, die separat gepflegt wird. Dies ermöglicht unterschiedliche Preise je nach Variante (z.B. verschiedene Preise für verschiedene Größen oder Ausführungen).

## Best Practices

- **Konsistente Preisarten** -- Verwenden Sie in Ihrem System einheitliche Preisarten. Definieren Sie vor der ersten Preispflege, welche Preisarten Sie benötigen.
- **Gültigkeitszeiträume** -- Nutzen Sie Gültigkeitszeiträume für temporäre Preisänderungen, anstatt bestehende Preise zu überschreiben. So behalten Sie die Preishistorie.
- **Währungskonventenz** -- Pflegen Sie Preise in allen Währungen, die Ihre Exportkanäle benötigen. Verlassen Sie sich nicht auf automatische Umrechnung.

## Nächste Schritte

- Erfahren Sie, wie [Produkte](./produkte) angelegt und verwaltet werden.
- Lernen Sie den [Export](/de/export/) kennen, um Preise in externe Systeme zu übertragen.
