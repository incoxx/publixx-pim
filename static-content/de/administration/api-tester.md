---
title: API-Tester
---

# API-Tester

Der API-Tester ist ein in das anyPIM integriertes Werkzeug zum Testen und Erkunden der REST-API. Er ermöglicht es Administratoren und Entwicklern, API-Endpunkte direkt aus der Anwendungsoberfläche heraus aufzurufen, ohne externe Tools wie Postman oder cURL verwenden zu müssen. Die Authentifizierung wird dabei automatisch gehandhabt.

## Übersicht

Den API-Tester erreichen Sie über **Administration > API-Tester** in der Sidebar. Die Oberfläche gliedert sich in drei Bereiche:

| Bereich | Beschreibung |
|---|---|
| **Endpunkt-Auswahl** | Dropdown zur Auswahl des API-Endpunkts mit HTTP-Methode |
| **Parameter-Eingabe** | Formularfelder für Pfad-, Query- und Body-Parameter |
| **Antwort-Anzeige** | Darstellung der JSON-Antwort mit Syntax-Hervorhebung |

::: tip Hinweis
Der API-Tester nutzt die aktuelle Benutzersitzung für die Authentifizierung. Alle Anfragen werden mit den Berechtigungen des angemeldeten Benutzers ausgeführt. Es ist kein separater API-Token erforderlich.
:::

## Endpunkt auswählen

Der Endpunkt-Bereich bietet eine nach Funktionsgruppen geordnete Auswahlliste aller verfügbaren API-Endpunkte:

### Verfügbare Endpunktgruppen

| Gruppe | Endpunkte | Beschreibung |
|---|---|---|
| **Produkte** | `GET /api/v1/products`, `POST /api/v1/products`, `PUT /api/v1/products/{id}`, `DELETE /api/v1/products/{id}` | CRUD-Operationen für Produkte |
| **Attribute** | `GET /api/v1/attributes`, `POST /api/v1/attributes`, `PUT /api/v1/attributes/{id}` | Attributverwaltung |
| **Hierarchien** | `GET /api/v1/hierarchies`, `GET /api/v1/hierarchies/{id}/nodes` | Hierarchie- und Knotenabfragen |
| **Medien** | `GET /api/v1/media`, `POST /api/v1/media`, `DELETE /api/v1/media/{id}` | Medienverwaltung |
| **Preise** | `GET /api/v1/prices`, `PUT /api/v1/prices/{id}` | Preisabfragen und -aktualisierung |
| **Export** | `GET /api/v1/export/products`, `POST /api/v1/export/products/bulk` | Export-Endpunkte |
| **PQL** | `POST /api/v1/pql/query` | PQL-Abfragen |

Wählen Sie einen Endpunkt aus der Dropdown-Liste oder nutzen Sie die Suchfunktion, um einen bestimmten Endpunkt schnell zu finden.

## Parameter konfigurieren

Nach der Auswahl eines Endpunkts werden die verfügbaren Parameter automatisch angezeigt.

### Pfadparameter

Pfadparameter wie `{id}` werden als Eingabefelder oberhalb der Query-Parameter dargestellt. Sie sind für die Ausführung des Endpunkts erforderlich.

### Query-Parameter

Query-Parameter werden als optionale Eingabefelder angezeigt. Sie können Filter, Paginierung und weitere Optionen steuern.

| Parameter | Typ | Beschreibung |
|---|---|---|
| `page` | Integer | Seitennummer für paginierte Ergebnisse |
| `per_page` | Integer | Anzahl der Ergebnisse pro Seite |
| `include` | String | Kommagetrennte Liste einzuschließender Relationen |
| `filter[...]` | String | Filterkriterien |

### Request-Body

Für `POST`- und `PUT`-Endpunkte wird ein JSON-Editor angezeigt, in dem Sie den Request-Body eingeben können. Der Editor bietet Syntax-Hervorhebung und Validierung.

::: warning Warnung
Schreibende API-Aufrufe (`POST`, `PUT`, `DELETE`) verändern tatsächliche Daten in Ihrer anyPIM-Instanz. Verwenden Sie diese Funktionen mit Bedacht, insbesondere in Produktivsystemen.
:::

## Anfrage ausführen

1. Wählen Sie den gewünschten Endpunkt aus.
2. Füllen Sie die erforderlichen Parameter aus.
3. Klicken Sie auf **Ausführen**.
4. Die Antwort wird im unteren Bereich angezeigt.

### Antwort-Anzeige

Die Antwort wird in mehreren Tabs dargestellt:

| Tab | Inhalt |
|---|---|
| **Body** | JSON-Antwort mit Syntax-Hervorhebung und Einrückung |
| **Header** | HTTP-Response-Header |
| **Status** | HTTP-Statuscode und Antwortzeit |

### Statuscode-Referenz

| Code | Bedeutung |
|---|---|
| `200 OK` | Anfrage erfolgreich |
| `201 Created` | Ressource erfolgreich erstellt |
| `400 Bad Request` | Ungültige Parameter oder Request-Body |
| `401 Unauthorized` | Fehlende oder ungültige Authentifizierung |
| `403 Forbidden` | Keine Berechtigung für diese Aktion |
| `404 Not Found` | Ressource nicht gefunden |
| `422 Unprocessable Entity` | Validierungsfehler |
| `429 Too Many Requests` | Rate-Limit erreicht |
| `500 Internal Server Error` | Serverseitiger Fehler |

## Anfragenverlauf

Der API-Tester speichert die letzten 50 Anfragen in der aktuellen Sitzung. Über die Schaltfläche **Verlauf** können Sie frühere Anfragen einsehen und erneut ausführen.

Für jeden Verlaufseintrag werden gespeichert:

- HTTP-Methode und Endpunkt
- Verwendete Parameter und Request-Body
- Antwort-Statuscode
- Zeitpunkt der Ausführung

::: tip Hinweis
Der Anfragenverlauf wird im Browser-Speicher abgelegt und geht beim Schließen des Browsers verloren. Für eine dauerhafte Dokumentation empfiehlt sich der Export der Anfragen.
:::

## Best Practices

- **Lesende Anfragen zuerst** -- Beginnen Sie mit `GET`-Anfragen, um die Datenstruktur zu verstehen, bevor Sie schreibende Operationen ausführen.
- **Testdaten verwenden** -- Nutzen Sie nach Möglichkeit eine Testumgebung, um schreibende Operationen zu erproben.
- **Parameter dokumentieren** -- Notieren Sie sich funktionierende Parameter-Kombinationen für die spätere Verwendung in Ihren Integrationen.
- **Fehlerbehandlung prüfen** -- Testen Sie bewusst ungültige Anfragen, um die Fehlerantworten der API kennenzulernen.

## Nächste Schritte

- Lesen Sie die vollständige [API-Dokumentation](../api/index), um alle verfügbaren Endpunkte im Detail kennenzulernen.
- Erfahren Sie mehr über die [API-Authentifizierung](../api/authentifizierung) für die Integration externer Systeme.
- Kehren Sie zur [Übersicht](../bedienung/index) zurück, um andere Funktionsbereiche zu erkunden.
