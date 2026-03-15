---
title: Journal
---

# Journal

Das Journal ist das systemweite Änderungsprotokoll des anyPIM. Es zeichnet sämtliche Datenänderungen auf -- vom Anlegen über das Bearbeiten bis zum Löschen von Datensätzen. Für jeden Vorgang werden die alten und neuen Werte gespeichert, sodass Änderungen jederzeit nachvollzogen und bei Bedarf zurückverfolgt werden können.

## Übersicht

Das Journal erreichen Sie über **Administration > Journal** in der Sidebar. Die Hauptansicht zeigt eine chronologische Liste aller Datenänderungen im System.

| Spalte | Beschreibung |
|---|---|
| **Zeitpunkt** | Datum und Uhrzeit der Änderung |
| **Benutzer** | Name des Benutzers, der die Änderung vorgenommen hat |
| **Entität** | Typ des geänderten Datensatzes (Produkt, Attribut, Hierarchie usw.) |
| **Aktion** | Art der Änderung (Erstellt, Aktualisiert, Gelöscht) |
| **Bezeichnung** | Name oder Kennung des betroffenen Datensatzes |
| **Felder** | Anzahl der geänderten Felder |

::: tip Hinweis
Das Journal unterscheidet sich vom [Benutzer-Audit](./benutzer-audit), das Benutzeraktionen wie Anmeldungen protokolliert. Das Journal konzentriert sich ausschließlich auf inhaltliche Datenänderungen.
:::

## Protokollierte Entitäten

Das Journal zeichnet Änderungen an den folgenden Entitätstypen auf:

| Entität | Beschreibung | Beispiel |
|---|---|---|
| **Produkt** | Produktstammdaten und Attributwerte | SKU, Name, Status, alle Attributwerte |
| **Attribut** | Attributdefinitionen | Name, Typ, Validierungsregeln |
| **Attributgruppe** | Gruppierung von Attributen | Name, Reihenfolge |
| **Hierarchie** | Hierarchie-Definitionen | Name, Typ |
| **Hierarchieknoten** | Knoten innerhalb einer Hierarchie | Name, Elternknoten, Reihenfolge |
| **Medium** | Medien-Dateien | Dateiname, Typ, Zuordnungen |
| **Preis** | Preisdaten | Betrag, Währung, Gültigkeit |
| **Benutzer** | Benutzerkontodaten | Name, E-Mail, Rolle |
| **Werteliste** | Vordefinierte Auswahlwerte | Einträge, Reihenfolge |

## Aktionstypen

Jeder Journal-Eintrag ist einem der drei Aktionstypen zugeordnet:

### Erstellt

Zeichnet das Anlegen eines neuen Datensatzes auf. Alle initialen Werte werden als "neue Werte" protokolliert.

### Aktualisiert

Zeichnet die Bearbeitung eines bestehenden Datensatzes auf. Sowohl die alten als auch die neuen Werte werden gespeichert, sodass jede Änderung im Detail nachvollzogen werden kann.

### Gelöscht

Zeichnet das Löschen eines Datensatzes auf. Die letzten Werte vor der Löschung werden als "alte Werte" protokolliert.

## Detailansicht

Klicken Sie auf einen Journal-Eintrag, um die vollständigen Änderungsdetails einzusehen. Die Detailansicht zeigt alle geänderten Felder in einer Vergleichsdarstellung:

| Spalte | Beschreibung |
|---|---|
| **Feld** | Name des geänderten Feldes |
| **Alter Wert** | Wert vor der Änderung |
| **Neuer Wert** | Wert nach der Änderung |

Geänderte Werte werden farblich hervorgehoben: Entfernte Inhalte in Rot, hinzugefügte Inhalte in Grün.

::: tip Hinweis
Bei umfangreichen Textänderungen (z. B. Produktbeschreibungen) zeigt die Detailansicht eine Diff-Darstellung, die hinzugefügte und entfernte Textpassagen auf Wortebene hervorhebt.
:::

## Filteroptionen

Die Journal-Liste kann über mehrere Kriterien gefiltert werden:

| Filter | Beschreibung | Beispiel |
|---|---|---|
| **Entitätstyp** | Filtern nach Art des Datensatzes | Nur Produkte |
| **Aktion** | Filtern nach Änderungstyp | Nur Löschungen |
| **Benutzer** | Filtern nach dem ändernden Benutzer | Max Mustermann |
| **Zeitraum** | Filtern nach Datum von/bis | 01.03.2026 -- 15.03.2026 |
| **Suchbegriff** | Volltextsuche in Bezeichnung und Werten | "ABS-100-PRO" |

Die Filter können beliebig kombiniert werden. Die Ergebnisliste wird in Echtzeit aktualisiert.

### Schnellfilter

Über den Entitätsnamen in der Journal-Liste gelangen Sie direkt zu einer gefilterten Ansicht aller Änderungen an diesem konkreten Datensatz. So können Sie die vollständige Änderungshistorie eines einzelnen Produkts, Attributs oder anderen Datensatzes einsehen.

## Suche

Das Journal bietet eine Volltextsuche, die folgende Felder durchsucht:

- Bezeichnung des geänderten Datensatzes
- Alte und neue Feldwerte
- Benutzername des Ändernden
- Feldnamen

Geben Sie den Suchbegriff in das Suchfeld ein. Die Ergebnisse werden nach Relevanz sortiert angezeigt.

## Aufbewahrung

Journal-Einträge werden standardmäßig unbegrenzt aufbewahrt. In den Systemeinstellungen kann ein maximaler Aufbewahrungszeitraum konfiguriert werden:

| Einstellung | Standardwert | Beschreibung |
|---|---|---|
| `journal.retention_days` | 0 (unbegrenzt) | Aufbewahrungszeitraum in Tagen |
| `journal.archive_enabled` | false | Automatische Archivierung alter Einträge |

::: warning Warnung
Das Verkürzen des Aufbewahrungszeitraums führt zur Löschung älterer Einträge. Diese Aktion kann nicht rückgängig gemacht werden.
:::

## Best Practices

- **Regelmäßige Kontrolle** -- Prüfen Sie das Journal regelmäßig, um unbeabsichtigte Änderungen frühzeitig zu erkennen.
- **Vor Löschungen prüfen** -- Sehen Sie sich vor einer geplanten Datenlöschung den Journal-Eintrag an, um sicherzustellen, dass keine wichtigen Daten verloren gehen.
- **Schulung** -- Machen Sie Ihr Team mit dem Journal vertraut, damit Benutzer eigenständig nachvollziehen können, wer welche Änderungen vorgenommen hat.
- **Aufbewahrungsrichtlinie** -- Definieren Sie eine Aufbewahrungsrichtlinie, die Ihren regulatorischen Anforderungen entspricht.

## Nächste Schritte

- Nutzen Sie das [Benutzer-Audit](./benutzer-audit), um Anmeldevorgänge und sicherheitsrelevante Aktionen zu überwachen.
- Erfahren Sie mehr über die [Rollen & Berechtigungen](./rollen), um festzulegen, wer Daten ändern darf.
- Kehren Sie zur [Übersicht](../bedienung/index) zurück, um andere Funktionsbereiche zu erkunden.
