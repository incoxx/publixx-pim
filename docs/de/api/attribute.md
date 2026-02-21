---
title: Attribute API
---

# Attribute API

Die Attribute-API ermöglicht die Verwaltung von Attributen, Attributtypen, Einheitengruppen, Einheiten, Wertelisten, Wertelisteneinträgen und Attributansichten.

## Endpunkte

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| `GET` | `/api/v1/attributes` | Attribute auflisten |
| `POST` | `/api/v1/attributes` | Attribut erstellen |
| `GET` | `/api/v1/attributes/{id}` | Attribut abrufen |
| `PUT` | `/api/v1/attributes/{id}` | Attribut aktualisieren |
| `DELETE` | `/api/v1/attributes/{id}` | Attribut löschen |
| `GET` | `/api/v1/attribute-types` | Attributtypen auflisten |
| `GET` | `/api/v1/unit-groups` | Einheitengruppen auflisten |
| `POST` | `/api/v1/unit-groups` | Einheitengruppe erstellen |
| `GET` | `/api/v1/unit-groups/{id}` | Einheitengruppe abrufen |
| `PUT` | `/api/v1/unit-groups/{id}` | Einheitengruppe aktualisieren |
| `DELETE` | `/api/v1/unit-groups/{id}` | Einheitengruppe löschen |
| `GET` | `/api/v1/unit-groups/{id}/units` | Einheiten einer Gruppe auflisten |
| `POST` | `/api/v1/unit-groups/{id}/units` | Einheit erstellen |
| `GET` | `/api/v1/value-lists` | Wertelisten auflisten |
| `POST` | `/api/v1/value-lists` | Werteliste erstellen |
| `GET` | `/api/v1/value-lists/{id}` | Werteliste abrufen |
| `PUT` | `/api/v1/value-lists/{id}` | Werteliste aktualisieren |
| `DELETE` | `/api/v1/value-lists/{id}` | Werteliste löschen |
| `GET` | `/api/v1/value-lists/{id}/entries` | Wertelisteneinträge auflisten |
| `POST` | `/api/v1/value-lists/{id}/entries` | Wertelisteneintrag erstellen |
| `GET` | `/api/v1/attribute-views` | Attributansichten auflisten |
| `POST` | `/api/v1/attribute-views` | Attributansicht erstellen |
| `GET` | `/api/v1/attribute-views/{id}` | Attributansicht abrufen |
| `PUT` | `/api/v1/attribute-views/{id}` | Attributansicht aktualisieren |
| `DELETE` | `/api/v1/attribute-views/{id}` | Attributansicht löschen |

## Attribute auflisten

```
GET /api/v1/attributes
```

### Query-Parameter

| Parameter | Typ | Standard | Beschreibung |
|---|---|---|---|
| `filter[type]` | String | -- | Nach Attributtyp filtern |
| `filter[searchable]` | Boolean | -- | Nur suchbare Attribute |
| `filter[required]` | Boolean | -- | Nur Pflichtattribute |
| `sort` | String | `position` | Sortierfeld |
| `search` | String | -- | Freitextsuche (Name, Code) |
| `page` | Integer | `1` | Seitennummer |
| `per_page` | Integer | `25` | Einträge pro Seite (max. 100) |
| `lang` | String | -- | Sprache für übersetzbare Felder |

### Beispiel-Anfrage

```bash
curl -X GET "https://pim.example.com/api/v1/attributes?filter[type]=text&sort=name&lang=de" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Beispiel-Antwort (200 OK)

```json
{
  "data": [
    {
      "id": "uuid-1",
      "code": "product_name",
      "type": "text",
      "name": "Produktname",
      "required": true,
      "searchable": true,
      "translatable": true,
      "position": 1
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 42
  }
}
```

## Attribut erstellen

```
POST /api/v1/attributes
```

**Request Body:**

```json
{
  "code": "weight",
  "type": "number",
  "name": {
    "de": "Gewicht",
    "en": "Weight"
  },
  "required": false,
  "searchable": true,
  "translatable": false,
  "unit_group_id": "uuid-unit-group",
  "default_unit_id": "uuid-unit-kg"
}
```

### Antwort (201 Created)

```json
{
  "data": {
    "id": "uuid-new",
    "code": "weight",
    "type": "number",
    "name": "Gewicht",
    "required": false,
    "searchable": true,
    "translatable": false,
    "unit_group_id": "uuid-unit-group",
    "default_unit_id": "uuid-unit-kg"
  }
}
```

## Attributtypen

Die verfügbaren Attributtypen können über folgenden Endpunkt abgerufen werden:

```
GET /api/v1/attribute-types
```

### Beispiel-Antwort

```json
{
  "data": [
    { "code": "text", "label": "Einzeiliger Text" },
    { "code": "textarea", "label": "Mehrzeiliger Text" },
    { "code": "number", "label": "Zahl" },
    { "code": "boolean", "label": "Ja/Nein" },
    { "code": "date", "label": "Datum" },
    { "code": "select", "label": "Einfachauswahl" },
    { "code": "multiselect", "label": "Mehrfachauswahl" },
    { "code": "media", "label": "Medien" }
  ]
}
```

Weitere Informationen zur Arbeit mit Attributen in der Benutzeroberfläche finden Sie unter [Attribute (Bedienung)](/de/bedienung/attribute).
