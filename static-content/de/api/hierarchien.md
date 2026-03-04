---
title: Hierarchien API
---

# Hierarchien API

Die Hierarchien-API ermöglicht die Verwaltung von Hierarchien, Baumstrukturen, Knoten und knotenspezifischen Attributzuordnungen.

## Endpunkte

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| `GET` | `/api/v1/hierarchies` | Hierarchien auflisten |
| `POST` | `/api/v1/hierarchies` | Hierarchie erstellen |
| `GET` | `/api/v1/hierarchies/{id}` | Hierarchie abrufen |
| `PUT` | `/api/v1/hierarchies/{id}` | Hierarchie aktualisieren |
| `DELETE` | `/api/v1/hierarchies/{id}` | Hierarchie löschen |
| `GET` | `/api/v1/hierarchies/{id}/tree` | Baumstruktur abrufen |
| `GET` | `/api/v1/hierarchies/{id}/nodes` | Knoten auflisten |
| `POST` | `/api/v1/hierarchies/{id}/nodes` | Knoten erstellen |
| `GET` | `/api/v1/hierarchy-nodes/{id}` | Knoten abrufen |
| `PUT` | `/api/v1/hierarchy-nodes/{id}` | Knoten aktualisieren |
| `DELETE` | `/api/v1/hierarchy-nodes/{id}` | Knoten löschen |
| `PUT` | `/api/v1/hierarchy-nodes/{id}/move` | Knoten verschieben |
| `GET` | `/api/v1/hierarchy-nodes/{id}/products` | Produkte eines Knotens |
| `POST` | `/api/v1/hierarchy-nodes/{id}/products` | Produkt zuordnen |
| `DELETE` | `/api/v1/hierarchy-nodes/{id}/products/{productId}` | Produktzuordnung entfernen |
| `GET` | `/api/v1/hierarchy-nodes/{id}/attributes` | Knotenattribute abrufen |
| `PUT` | `/api/v1/hierarchy-nodes/{id}/attributes` | Knotenattribute aktualisieren |

## Hierarchien auflisten

```
GET /api/v1/hierarchies
```

### Query-Parameter

| Parameter | Typ | Standard | Beschreibung |
|---|---|---|---|
| `filter[type]` | String | -- | Nach Hierarchietyp filtern |
| `sort` | String | `name` | Sortierfeld |
| `search` | String | -- | Freitextsuche (Name, Code) |
| `page` | Integer | `1` | Seitennummer |
| `per_page` | Integer | `25` | Einträge pro Seite (max. 100) |
| `lang` | String | -- | Sprache für übersetzbare Felder |

### Beispiel-Anfrage

```bash
curl -X GET "https://pim.example.com/api/v1/hierarchies?lang=de" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Beispiel-Antwort (200 OK)

```json
{
  "data": [
    {
      "id": "uuid-1",
      "code": "product_categories",
      "name": "Produktkategorien",
      "type": "category",
      "nodes_count": 156
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 3
  }
}
```

## Baumstruktur abrufen

Gibt die vollständige Baumstruktur einer Hierarchie zurück:

```
GET /api/v1/hierarchies/{id}/tree
```

### Query-Parameter

| Parameter | Typ | Standard | Beschreibung |
|---|---|---|---|
| `depth` | Integer | -- | Maximale Tiefe (Standard: alle Ebenen) |
| `lang` | String | -- | Sprache für Knotennamen |

### Beispiel-Antwort (200 OK)

```json
{
  "data": {
    "id": "uuid-root",
    "name": "Produktkategorien",
    "children": [
      {
        "id": "uuid-child-1",
        "name": "Werkzeuge",
        "position": 1,
        "children": [
          {
            "id": "uuid-grandchild-1",
            "name": "Elektrowerkzeuge",
            "position": 1,
            "children": []
          }
        ]
      }
    ]
  }
}
```

## Knoten erstellen

```
POST /api/v1/hierarchies/{id}/nodes
```

**Request Body:**

```json
{
  "parent_id": "uuid-parent-node",
  "name": {
    "de": "Neue Kategorie",
    "en": "New Category"
  },
  "code": "new_category",
  "position": 3
}
```

### Antwort (201 Created)

```json
{
  "data": {
    "id": "uuid-new-node",
    "hierarchy_id": "uuid-hierarchy",
    "parent_id": "uuid-parent-node",
    "name": "Neue Kategorie",
    "code": "new_category",
    "position": 3,
    "depth": 2
  }
}
```

## Knoten verschieben

Verschiebt einen Knoten an eine neue Position im Baum:

```
PUT /api/v1/hierarchy-nodes/{id}/move
```

**Request Body:**

```json
{
  "parent_id": "uuid-new-parent",
  "position": 1
}
```

Weitere Informationen zur Arbeit mit Hierarchien in der Benutzeroberfläche finden Sie unter [Hierarchien (Bedienung)](/de/bedienung/hierarchien).
