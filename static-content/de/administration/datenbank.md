---
title: Datenbank
---

# Datenbank

Der Datenbank-Viewer bietet Administratoren einen schreibgeschützten Einblick in die Datenbankstruktur und -inhalte des anyPIM. Er dient der Fehleranalyse, dem Verständnis des Datenmodells und der Erstellung einfacher Abfragen, ohne dass ein externer Datenbank-Client benötigt wird.

## Übersicht

Den Datenbank-Viewer erreichen Sie über **Administration > Datenbank** in der Sidebar. Die Oberfläche gliedert sich in zwei Hauptbereiche:

| Bereich | Beschreibung |
|---|---|
| **Tabellenliste** | Seitenleiste mit allen Datenbanktabellen, alphabetisch sortiert |
| **Tabellenansicht** | Hauptbereich zur Anzeige von Struktur und Daten der gewählten Tabelle |

::: danger Achtung
Der Datenbank-Viewer ist ausschließlich für Benutzer mit der Rolle **Admin** zugänglich. Er bietet bewusst nur Lesezugriff -- Datenänderungen über diesen Viewer sind nicht möglich. Für Änderungen nutzen Sie die regulären Funktionen der Anwendung.
:::

## Tabellenstruktur anzeigen

Wählen Sie eine Tabelle aus der Seitenleiste aus, um deren Struktur einzusehen. Der Tab **Struktur** zeigt alle Spalten der Tabelle mit den folgenden Informationen:

| Spalte | Beschreibung |
|---|---|
| **Name** | Spaltenname |
| **Typ** | Datentyp (z. B. `varchar(255)`, `integer`, `timestamp`, `uuid`) |
| **Nullable** | Ob die Spalte NULL-Werte erlaubt |
| **Standard** | Standardwert der Spalte |
| **Index** | Vorhandene Indizes (Primary, Unique, Index) |

### Wichtige Tabellen

| Tabelle | Beschreibung |
|---|---|
| `products` | Stammdaten der Produkte |
| `product_attribute_values` | Attributwerte der Produkte |
| `attributes` | Attributdefinitionen |
| `attribute_groups` | Attributgruppen |
| `hierarchies` | Hierarchie-Definitionen |
| `hierarchy_nodes` | Einzelne Knoten innerhalb einer Hierarchie |
| `media` | Medien-Dateien (Bilder, Dokumente) |
| `prices` | Preisdaten |
| `users` | Benutzerkonten |

## Daten durchsuchen

Wechseln Sie zum Tab **Daten**, um die Inhalte einer Tabelle zu durchsuchen. Die Daten werden paginiert angezeigt, standardmäßig 50 Einträge pro Seite.

### Sortierung

Klicken Sie auf einen Spaltenkopf, um die Daten nach dieser Spalte zu sortieren. Ein erneuter Klick kehrt die Sortierrichtung um.

### Spaltenfilter

Über das Filtersymbol in jedem Spaltenkopf können Sie die Anzeige auf bestimmte Werte einschränken:

| Filtertyp | Beschreibung | Beispiel |
|---|---|---|
| **Enthält** | Teilzeichenkette im Wert | Name enthält "Bohrer" |
| **Gleich** | Exakter Wert | Status gleich "active" |
| **Nicht gleich** | Werte ausschließen | Typ nicht gleich "variant" |
| **Größer/Kleiner** | Numerische Vergleiche | Preis größer 100 |
| **Null/Nicht Null** | NULL-Werte filtern | Ablaufdatum ist Null |

## Einfache Abfragen

Der Tab **Abfrage** bietet einen SQL-Editor für einfache SELECT-Abfragen. Sie können damit gezielt Daten aus einer oder mehreren Tabellen abfragen.

::: warning Warnung
Nur `SELECT`-Anweisungen sind erlaubt. Schreibende Anweisungen (`INSERT`, `UPDATE`, `DELETE`, `DROP` usw.) werden vom System blockiert. Abfragen haben ein Zeitlimit von 30 Sekunden.
:::

### Beispielabfragen

**Alle aktiven Produkte mit SKU:**

```sql
SELECT id, sku, created_at
FROM products
WHERE status = 'active'
ORDER BY created_at DESC
LIMIT 100
```

**Produkte mit einem bestimmten Attributwert:**

```sql
SELECT p.sku, pav.value
FROM products p
JOIN product_attribute_values pav ON p.id = pav.product_id
JOIN attributes a ON pav.attribute_id = a.id
WHERE a.code = 'farbe' AND pav.value = 'Rot'
LIMIT 50
```

**Anzahl Produkte pro Hierarchieknoten:**

```sql
SELECT hn.name, COUNT(phn.product_id) AS product_count
FROM hierarchy_nodes hn
LEFT JOIN product_hierarchy_node phn ON hn.id = phn.hierarchy_node_id
GROUP BY hn.id, hn.name
ORDER BY product_count DESC
```

## Ergebnisse exportieren

Sowohl die Datenansicht als auch die Ergebnisse von Abfragen können exportiert werden:

1. Klicken Sie auf **Exportieren** oberhalb der Ergebnistabelle.
2. Wählen Sie das Format:

| Format | Beschreibung |
|---|---|
| **CSV** | Kommagetrennte Werte |
| **JSON** | Strukturiertes JSON-Array |

3. Der Download startet automatisch.

::: tip Hinweis
Der Export ist auf maximal 10.000 Zeilen begrenzt. Für größere Datenmengen verwenden Sie die Filterung oder schränken Sie Ihre Abfrage mit `LIMIT` ein.
:::

## Best Practices

- **Fehleranalyse** -- Nutzen Sie den Datenbank-Viewer zur Analyse von Dateninkonsistenzen, bevor Sie Änderungen über die Anwendungsoberfläche vornehmen.
- **Datenmodell verstehen** -- Erkunden Sie die Tabellenstrukturen, um das Datenmodell des anyPIM besser zu verstehen, insbesondere vor der Entwicklung von Integrationen.
- **Performance beachten** -- Vermeiden Sie Abfragen ohne `LIMIT` auf großen Tabellen, um die Systemleistung nicht zu beeinträchtigen.
- **Sensible Daten** -- Beachten Sie, dass die Benutzertabelle Passwort-Hashes enthält. Geben Sie Abfrageergebnisse mit sensiblen Daten nicht weiter.

## Nächste Schritte

- Erfahren Sie mehr über das [Datenmodell](../architektur/datenmodell), um die Zusammenhänge zwischen den Tabellen zu verstehen.
- Nutzen Sie den [API-Tester](./api-tester) für strukturierte Datenzugriffe über die REST-API.
- Kehren Sie zur [Übersicht](../bedienung/index) zurück, um andere Funktionsbereiche zu erkunden.
