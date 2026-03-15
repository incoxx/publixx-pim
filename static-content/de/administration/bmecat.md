---
title: BMEcat Import/Export
---

# BMEcat Import/Export

Das anyPIM unterstützt den Import und Export von Produktdaten im BMEcat-Format, dem in Europa führenden Standard für den elektronischen Austausch von Produktkatalogen. Es werden sowohl BMEcat 1.2 als auch BMEcat 2005 unterstützt. Die BMEcat-Schnittstelle ermöglicht den Datenaustausch mit ERP-Systemen, E-Procurement-Plattformen und Handelspartnern.

## Übersicht

Die BMEcat-Funktionen erreichen Sie über **Administration > BMEcat** in der Sidebar. Die Oberfläche bietet zwei Hauptbereiche:

| Bereich | Beschreibung |
|---|---|
| **Import** | Einlesen von BMEcat-XML-Dateien in das anyPIM |
| **Export** | Erzeugen von BMEcat-XML-Dateien aus dem anyPIM |

::: tip Hinweis
Für den Zugriff auf die BMEcat-Funktionen benötigen Sie die Rolle **Admin** oder die Berechtigungen `imports.create` bzw. `exports.create`.
:::

## Unterstützte Versionen

| Version | Standard | Unterstützte Transaktionen |
|---|---|---|
| **BMEcat 1.2** | DIN/ISO | `T_NEW_CATALOG`, `T_UPDATE_PRODUCTS`, `T_UPDATE_PRICES` |
| **BMEcat 2005** | BME e.V. | `T_NEW_CATALOG`, `T_UPDATE_PRODUCTS`, `T_UPDATE_PRICES` |

### Transaktionstypen

| Transaktion | Beschreibung |
|---|---|
| `T_NEW_CATALOG` | Vollständiger neuer Katalog mit allen Produkten |
| `T_UPDATE_PRODUCTS` | Aktualisierung bestehender Produktdaten |
| `T_UPDATE_PRICES` | Aktualisierung von Preisen ohne Produktdatenänderung |

## BMEcat-Import

### Import starten

1. Navigieren Sie zu **Administration > BMEcat > Import**.
2. Klicken Sie auf **Datei auswählen** und laden Sie eine BMEcat-XML-Datei hoch.
3. Das System analysiert die Datei und zeigt eine Vorschau an.

### Importvorschau

Die Vorschau zeigt eine Zusammenfassung der zu importierenden Daten:

| Information | Beschreibung |
|---|---|
| **Katalogname** | Name des BMEcat-Katalogs |
| **Version** | Erkannte BMEcat-Version |
| **Transaktion** | Art der Transaktion |
| **Lieferant** | Lieferanteninformationen aus dem Header |
| **Artikelanzahl** | Anzahl der Produkte in der Datei |
| **Neue Produkte** | Anzahl der neu anzulegenden Produkte |
| **Aktualisierungen** | Anzahl der zu aktualisierenden Produkte |

### Katalogstruktur-Mapping

Das anyPIM bildet die BMEcat-Katalogstruktur auf die interne Hierarchie ab. Im Mapping-Dialog ordnen Sie die Kataloggruppen des BMEcat den Hierarchieknoten im anyPIM zu:

| BMEcat-Element | anyPIM-Zuordnung |
|---|---|
| `CATALOG_GROUP_SYSTEM` | Hierarchie |
| `CATALOG_GROUP` | Hierarchieknoten |
| `GROUP_PRODUCT_ORDER` | Produkt-Knoten-Zuordnung |

::: warning Warnung
Beim Import eines vollständigen Katalogs (`T_NEW_CATALOG`) können bestehende Zuordnungen überschrieben werden. Erstellen Sie vor dem Import eine Sicherung über die reguläre Exportfunktion.
:::

### Klassifikations-Mapping

BMEcat-Dateien enthalten häufig Klassifikationsinformationen nach eCl@ss oder ETIM. Das anyPIM bietet ein Mapping dieser Klassifikationen auf interne Attribute:

| Klassifikation | Unterstützte Versionen |
|---|---|
| **eCl@ss** | 5.1, 7.0, 8.0, 9.0, 10.0, 11.0, 12.0, 13.0, 14.0 |
| **ETIM** | 6.0, 7.0, 8.0, 9.0 |
| **UNSPSC** | Alle Versionen |

Für jedes Klassifikationsmerkmal können Sie festlegen, welchem anyPIM-Attribut es zugeordnet wird. Nicht zugeordnete Merkmale können automatisch als neue Attribute angelegt werden.

### Medienreferenzen

BMEcat-Dateien referenzieren Medien (Bilder, Datenblätter, Sicherheitsdatenblätter) über das Element `MIME`. Das anyPIM verarbeitet Medienreferenzen wie folgt:

| Szenario | Verhalten |
|---|---|
| **URL-Referenz** | Medium wird von der angegebenen URL heruntergeladen |
| **Dateireferenz** | Medium wird aus einem begleitenden ZIP-Archiv geladen |
| **Bereits vorhanden** | Vorhandene Medien werden anhand des Dateinamens erkannt und verknüpft |

### Preisverarbeitung

BMEcat-Preise werden anhand des Preistyps auf die Preisarten im anyPIM abgebildet:

| BMEcat-Preistyp | Beschreibung | Mapping |
|---|---|---|
| `net_list` | Listenpreis netto | Konfigurierbar |
| `net_customer` | Kundenpreis netto | Konfigurierbar |
| `nrp` | Unverbindliche Preisempfehlung | Konfigurierbar |
| `gros_list` | Listenpreis brutto | Konfigurierbar |

Das Mapping der Preistypen konfigurieren Sie im Import-Dialog unter **Preiszuordnung**.

### Validierung

Vor dem endgültigen Import führt das System eine Validierung durch:

| Prüfung | Beschreibung |
|---|---|
| **XML-Validierung** | Prüfung der XML-Struktur gegen das BMEcat-Schema |
| **Pflichtfelder** | Prüfung, ob alle erforderlichen BMEcat-Elemente vorhanden sind |
| **Datentypen** | Prüfung der Werte gegen die definierten Attributtypen |
| **Referenzen** | Prüfung, ob referenzierte Kataloggruppen und Klassifikationen existieren |
| **Duplikate** | Erkennung doppelter Artikelnummern |

Validierungsfehler werden in einer übersichtlichen Liste angezeigt. Sie können Fehler beheben, bevor Sie den Import ausführen.

::: danger Achtung
Führen Sie den Import bei großen Dateien (mehr als 10.000 Artikel) außerhalb der Hauptarbeitszeiten durch, da der Vorgang erhebliche Systemressourcen beanspruchen kann.
:::

## BMEcat-Export

### Export konfigurieren

1. Navigieren Sie zu **Administration > BMEcat > Export**.
2. Konfigurieren Sie die Exportparameter:

| Parameter | Beschreibung |
|---|---|
| **Version** | BMEcat 1.2 oder BMEcat 2005 |
| **Transaktion** | Transaktionstyp (T_NEW_CATALOG, T_UPDATE_PRODUCTS, T_UPDATE_PRICES) |
| **Hierarchie** | Auszugebende Hierarchie als Katalogstruktur |
| **Sprache** | Exportsprache |
| **Preisarten** | Zu exportierende Preisarten mit BMEcat-Preistyp-Zuordnung |
| **Klassifikation** | Optionale Klassifikation (eCl@ss, ETIM) |
| **Medien einschließen** | Medienreferenzen im Export einschließen |

3. Klicken Sie auf **Export starten**.
4. Der Export wird als Hintergrundaufgabe verarbeitet. Sie erhalten eine Benachrichtigung, sobald die Datei bereitsteht.

### Exportergebnis

Das Exportergebnis umfasst:

| Datei | Beschreibung |
|---|---|
| **BMEcat-XML** | Die eigentliche BMEcat-Datei |
| **Medien-ZIP** | Optionales ZIP-Archiv mit allen referenzierten Mediendateien |
| **Protokoll** | Exportprotokoll mit Statistiken und eventuellen Warnungen |

## Best Practices

- **Validierung ernst nehmen** -- Beheben Sie alle Validierungsfehler vor dem Import. Ungültige Daten können zu Inkonsistenzen führen.
- **Testlauf** -- Nutzen Sie die Vorschaufunktion und führen Sie bei großen Importen zunächst einen Testlauf mit einer reduzierten Datei durch.
- **Mapping dokumentieren** -- Halten Sie die Zuordnungen zwischen BMEcat-Feldern und anyPIM-Attributen schriftlich fest, um Konsistenz bei wiederholten Importen sicherzustellen.
- **Versionswahl** -- Verwenden Sie BMEcat 2005, sofern Ihr Handelspartner diese Version unterstützt. Sie bietet erweiterte Möglichkeiten gegenüber Version 1.2.
- **Regelmäßige Updates** -- Nutzen Sie `T_UPDATE_PRODUCTS` für inkrementelle Aktualisierungen statt jedes Mal einen vollständigen Katalog zu importieren.

## Nächste Schritte

- Erfahren Sie mehr über den [JSON-Export](../export/json-export), um Produktdaten über die REST-API bereitzustellen.
- Lernen Sie die [Import-Funktionen](../import/index) kennen, um Daten aus anderen Formaten zu importieren.
- Kehren Sie zur [Übersicht](../bedienung/index) zurück, um andere Funktionsbereiche zu erkunden.
