---
title: Hierarchies API
---

# Hierarchies API

The Hierarchies API enables the management of hierarchies, tree structures, nodes, and node-specific attribute assignments.

## Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/hierarchies` | List hierarchies |
| `POST` | `/api/v1/hierarchies` | Create hierarchy |
| `GET` | `/api/v1/hierarchies/{id}` | Retrieve hierarchy |
| `PUT` | `/api/v1/hierarchies/{id}` | Update hierarchy |
| `DELETE` | `/api/v1/hierarchies/{id}` | Delete hierarchy |
| `GET` | `/api/v1/hierarchies/{id}/tree` | Retrieve tree structure |
| `GET` | `/api/v1/hierarchies/{id}/nodes` | List nodes |
| `POST` | `/api/v1/hierarchies/{id}/nodes` | Create node |
| `GET` | `/api/v1/hierarchy-nodes/{id}` | Retrieve node |
| `PUT` | `/api/v1/hierarchy-nodes/{id}` | Update node |
| `DELETE` | `/api/v1/hierarchy-nodes/{id}` | Delete node |
| `PUT` | `/api/v1/hierarchy-nodes/{id}/move` | Move node |
| `GET` | `/api/v1/hierarchy-nodes/{id}/products` | Products of a node |
| `POST` | `/api/v1/hierarchy-nodes/{id}/products` | Assign product |
| `DELETE` | `/api/v1/hierarchy-nodes/{id}/products/{productId}` | Remove product assignment |
| `GET` | `/api/v1/hierarchy-nodes/{id}/attributes` | Retrieve node attributes |
| `PUT` | `/api/v1/hierarchy-nodes/{id}/attributes` | Update node attributes |

## List Hierarchies

```
GET /api/v1/hierarchies
```

### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `filter[type]` | String | -- | Filter by hierarchy type |
| `sort` | String | `name` | Sort field |
| `search` | String | -- | Full-text search (name, code) |
| `page` | Integer | `1` | Page number |
| `per_page` | Integer | `25` | Entries per page (max. 100) |
| `lang` | String | -- | Language for translatable fields |

### Example Request

```bash
curl -X GET "https://pim.example.com/api/v1/hierarchies?lang=de" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Example Response (200 OK)

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

## Retrieve Tree Structure

Returns the complete tree structure of a hierarchy:

```
GET /api/v1/hierarchies/{id}/tree
```

### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `depth` | Integer | -- | Maximum depth (default: all levels) |
| `lang` | String | -- | Language for node names |

### Example Response (200 OK)

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

## Create Node

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

### Response (201 Created)

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

## Move Node

Moves a node to a new position in the tree:

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

For more information on working with hierarchies in the user interface, see [Hierarchies (Usage)](/en/usage/hierarchies).
