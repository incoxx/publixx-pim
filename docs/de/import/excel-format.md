---
title: Excel-Format
---

# Excel-Format

Diese Seite dokumentiert den Aufbau der Excel-Importdatei im Detail. Sie beschreibt die Struktur aller 14 Tabellenblätter (Tabs), deren Abhängigkeiten untereinander und die Spaltendefinitionen der wichtigsten Tabs.

## Übersicht: 14 Tabs mit Abhängigkeiten

Die Tabs werden in einer definierten Reihenfolge verarbeitet. Spätere Tabs können auf Daten früherer Tabs oder auf bereits im System vorhandene Daten verweisen.

| Nr. | Tab-Name | Identifikator | Abhängig von |
|---|---|---|---|
| 01 | `Sprachen` | ISO-639-1-Code | -- |
| 02 | `Attributgruppen` | Technischer Name | `01_Sprachen` |
| 03 | `Einheitengruppen` | Technischer Name | `01_Sprachen` |
| 04 | `Einheiten` | Technischer Name | `03_Einheitengruppen` |
| 05 | `Attribute` | Technischer Name | `02_Attributgruppen`, `03_Einheitengruppen`, `07_Wertelisten` |
| 06 | `Hierarchien` | Hierarchiename + Ebene | -- |
| 07 | `Wertelisten` | Technischer Name | `01_Sprachen` |
| 08 | `Produkte` | SKU | `06_Hierarchien` |
| 09 | `Produktwerte` | SKU + Attribut + Sprache + Index | `08_Produkte`, `05_Attribute`, `01_Sprachen` |
| 10 | `Varianten` | Varianten-SKU | `08_Produkte` |
| 11 | `Variantenwerte` | Varianten-SKU + Attribut + Sprache + Index | `10_Varianten`, `05_Attribute` |
| 12 | `Medien` | SKU + Medien-URL | `08_Produkte` |
| 13 | `Preise` | SKU + Preisart + Währung | `08_Produkte` |
| 14 | `Relationen` | Quell-SKU + Ziel-SKU + Typ | `08_Produkte` |

::: info Verarbeitungsreihenfolge
Obwohl die Tabs nummeriert sind, löst das System zirkuläre Abhängigkeiten (z. B. `05_Attribute` referenziert `07_Wertelisten`) automatisch auf, indem die Validierung in mehreren Durchläufen erfolgt.
:::

## Tab 05: Attribute

Der Attribute-Tab ist einer der komplexesten Tabs und definiert das gesamte Attribut-Schema des PIM. Jede Zeile beschreibt ein Attribut mit 19 Spalten.

| Spalte | Feldname | Pflicht | Datentyp | Beschreibung |
|---|---|---|---|---|
| A | `technical_name` | Ja | String | Eindeutiger technischer Bezeichner (z. B. `gewicht`, `farbe_ral`) |
| B | `name_de` | Ja | String | Anzeigename auf Deutsch |
| C | `name_en` | Nein | String | Anzeigename auf Englisch |
| D | `description` | Nein | String | Beschreibung des Attributs |
| E | `data_type` | Ja | Enum | Datentyp: `text`, `number`, `boolean`, `date`, `datetime`, `json`, `richtext` |
| F | `attribute_group` | Ja | Referenz | Technischer Name der Attributgruppe |
| G | `value_list` | Nein | Referenz | Technischer Name der Werteliste (nur für Auswahlattribute) |
| H | `unit_group` | Nein | Referenz | Technischer Name der Einheitengruppe |
| I | `default_unit` | Nein | Referenz | Technischer Name der Standardeinheit |
| J | `repeatable` | Nein | Boolean | Attribut kann mehrere Werte haben (`true`/`false`) |
| K | `max_repetitions` | Nein | Integer | Maximale Anzahl Wiederholungen (nur wenn `repeatable = true`) |
| L | `translatable` | Nein | Boolean | Attributwerte sind sprachabhängig (`true`/`false`) |
| M | `required` | Nein | Boolean | Pflichtfeld bei Produktanlage (`true`/`false`) |
| N | `unique` | Nein | Boolean | Wert muss systemweit eindeutig sein (`true`/`false`) |
| O | `searchable` | Nein | Boolean | Attribut wird in der Volltextsuche berücksichtigt (`true`/`false`) |
| P | `inheritable` | Nein | Boolean | Wert wird an Varianten vererbt (`true`/`false`) |
| Q | `parent_attribute` | Nein | Referenz | Technischer Name des übergeordneten Attributs (für verschachtelte Attribute) |
| R | `source_system` | Nein | String | Quellsystem-Kennung (z. B. `ERP`, `PIM`, `Webshop`) |
| S | `views` | Nein | String | Kommagetrennte Liste der Attributansichten (z. B. `basis,detail,export`) |

### Beispielzeile

| technical_name | name_de | name_en | data_type | attribute_group | translatable | required | searchable | inheritable |
|---|---|---|---|---|---|---|---|---|
| `gewicht_netto` | Nettogewicht | Net Weight | `number` | `technische_daten` | `false` | `true` | `true` | `true` |

### Hinweise

- **Boolean-Werte**: Akzeptiert werden `true`/`false`, `1`/`0`, `ja`/`nein` und `yes`/`no`.
- **Referenzen**: Alle Referenzen verwenden den technischen Namen der Zielentität. Die Auflösung zu UUIDs erfolgt automatisch.
- **Standardwerte**: Nicht ausgefüllte optionale Boolean-Felder werden als `false` interpretiert.

## Tab 06: Hierarchien

Hierarchien werden als flache Tabelle mit bis zu sechs Ebenen importiert. Jede Zeile repräsentiert einen Pfad vom Wurzelknoten bis zum tiefsten angegebenen Knoten.

| Spalte | Feldname | Pflicht | Datentyp | Beschreibung |
|---|---|---|---|---|
| A | `hierarchy_name` | Ja | String | Name der Hierarchie |
| B | `type` | Ja | Enum | Hierarchietyp: `master` oder `output` |
| C | `level_1` | Ja | String | Knoten auf Ebene 1 (Wurzel) |
| D | `level_2` | Nein | String | Knoten auf Ebene 2 |
| E | `level_3` | Nein | String | Knoten auf Ebene 3 |
| F | `level_4` | Nein | String | Knoten auf Ebene 4 |
| G | `level_5` | Nein | String | Knoten auf Ebene 5 |
| H | `level_6` | Nein | String | Knoten auf Ebene 6 (Blatt) |

### Beispielzeilen

| hierarchy_name | type | level_1 | level_2 | level_3 |
|---|---|---|---|---|
| Produktkatalog | master | Werkzeuge | Elektrowerkzeuge | Bohrmaschinen |
| Produktkatalog | master | Werkzeuge | Elektrowerkzeuge | Schleifmaschinen |
| Produktkatalog | master | Werkzeuge | Handwerkzeuge | Schraubendreher |
| Webshop | output | Heimwerken | Bohren & Schrauben | -- |

### Hinweise

- **Zusammenführung**: Zeilen mit identischem Präfix (gleiche obere Ebenen) werden automatisch zu einem Baum zusammengeführt.
- **Master vs. Output**: `master`-Hierarchien dienen der internen Produktklassifizierung, `output`-Hierarchien der Strukturierung von Exporten.
- **Leere Ebenen**: Leere Zellen am Ende einer Zeile zeigen an, dass der Pfad auf der letzten befüllten Ebene endet.

## Tab 08: Produkte

Der Produkte-Tab enthält die Stammdaten jedes Produkts.

| Spalte | Feldname | Pflicht | Datentyp | Beschreibung |
|---|---|---|---|---|
| A | `sku` | Ja | String | Eindeutige Artikelnummer (Stock Keeping Unit) |
| B | `name_de` | Ja | String | Produktname auf Deutsch |
| C | `name_en` | Nein | String | Produktname auf Englisch |
| D | `product_type` | Ja | Enum | Produkttyp: `simple`, `configurable` |
| E | `ean` | Nein | String | European Article Number (EAN/GTIN) |
| F | `status` | Nein | Enum | Status: `draft`, `active`, `inactive` (Standard: `draft`) |

### Beispielzeilen

| sku | name_de | name_en | product_type | ean | status |
|---|---|---|---|---|---|
| `BM-2000-PRO` | Bohrmaschine Pro 2000 | Drill Machine Pro 2000 | `simple` | `4012345678901` | `active` |
| `ABS-SERIE-X` | Akkubohrschrauber Serie X | Cordless Drill Series X | `configurable` | -- | `draft` |

### Hinweise

- **Upsert**: Existiert bereits ein Produkt mit der gleichen SKU, wird es aktualisiert.
- **Produkttyp `configurable`**: Produkte dieses Typs können Varianten haben, die im Tab `10_Varianten` definiert werden.
- **Status**: Der Status `draft` ist der Standardwert. Nur Produkte im Status `active` werden beim Export berücksichtigt.

## Tab 09: Produktwerte

Der Produktwerte-Tab ordnet Attributwerte den Produkten zu. Die Kombination aus SKU, Attribut, Sprache und Index bildet den eindeutigen Schlüssel.

| Spalte | Feldname | Pflicht | Datentyp | Beschreibung |
|---|---|---|---|---|
| A | `sku` | Ja | Referenz | SKU des Produkts (aus Tab `08_Produkte`) |
| B | `attribute` | Ja | Referenz | Technischer Name des Attributs (aus Tab `05_Attribute`) |
| C | `value` | Ja | Mixed | Der Attributwert (Typ hängt vom Attribut ab) |
| D | `unit` | Nein | Referenz | Technischer Name der Einheit (für Attribute mit Einheitengruppe) |
| E | `language` | Nein | String | ISO-639-1-Sprachcode (nur für übersetzbare Attribute, z. B. `de`, `en`) |
| F | `index` | Nein | Integer | Wiederholungsindex (nur für wiederholbare Attribute, Standard: `1`) |

### Beispielzeilen

| sku | attribute | value | unit | language | index |
|---|---|---|---|---|---|
| `BM-2000-PRO` | `gewicht_netto` | `2.5` | `kg` | -- | -- |
| `BM-2000-PRO` | `beschreibung` | Leistungsstarke Bohrmaschine | -- | `de` | -- |
| `BM-2000-PRO` | `beschreibung` | Powerful drill machine | -- | `en` | -- |
| `BM-2000-PRO` | `zertifikat` | CE | -- | -- | `1` |
| `BM-2000-PRO` | `zertifikat` | GS | -- | -- | `2` |

### Hinweise

- **Sprache**: Wird nur bei Attributen benötigt, die als `translatable` konfiguriert sind. Für nicht-übersetzbare Attribute bleibt die Spalte leer.
- **Index**: Wird nur bei Attributen benötigt, die als `repeatable` konfiguriert sind. Der Index beginnt bei `1`.
- **Einheit**: Wird nur bei Attributen benötigt, die einer Einheitengruppe zugeordnet sind.
- **Upsert**: Existiert bereits ein Wert mit derselben Kombination aus SKU, Attribut, Sprache und Index, wird er aktualisiert.

## Allgemeine Regeln

### Upsert-Logik im Detail

Für jeden Tab gilt folgende Upsert-Logik:

| Situation | Verhalten |
|---|---|
| Identifikator existiert **nicht** im System | Datensatz wird **angelegt** |
| Identifikator existiert **bereits** im System | Datensatz wird **aktualisiert** (nur befüllte Felder) |
| Identifikator existiert in **derselben Datei** doppelt | Wird als Fehler gemeldet |

### Encoding und Formatierung

- **Zeichensatz**: UTF-8 (Standard für .xlsx-Dateien)
- **Datumsformat**: ISO 8601 (`YYYY-MM-DD`) oder deutsches Format (`DD.MM.YYYY`)
- **Dezimaltrennzeichen**: Punkt (`.`) als Dezimaltrennzeichen (z. B. `12.50`)
- **Boolean-Werte**: `true`/`false`, `1`/`0`, `ja`/`nein`, `yes`/`no`
- **Leere Zellen**: Werden als "nicht angegeben" interpretiert und überschreiben bestehende Werte **nicht**

::: warning Hinweis
Wenn Sie einen bestehenden Wert explizit löschen möchten, verwenden Sie den speziellen Platzhalter `__NULL__` als Zellwert.
:::

## Weiterführende Dokumentation

- [Import-Übersicht](/de/import/) -- Prozessübersicht und Konzept
- [Validierung](/de/import/validierung) -- Validierungsregeln und Fehlermeldungen
