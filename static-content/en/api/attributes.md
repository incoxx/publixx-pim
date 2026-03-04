---
title: Attributes API
---

# Attributes API

The Attributes API enables the management of attributes, attribute types, unit groups, units, value lists, value list entries, and attribute views.

## Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/attributes` | List attributes |
| `POST` | `/api/v1/attributes` | Create attribute |
| `GET` | `/api/v1/attributes/{id}` | Retrieve attribute |
| `PUT` | `/api/v1/attributes/{id}` | Update attribute |
| `DELETE` | `/api/v1/attributes/{id}` | Delete attribute |
| `GET` | `/api/v1/attribute-types` | List attribute types |
| `GET` | `/api/v1/unit-groups` | List unit groups |
| `POST` | `/api/v1/unit-groups` | Create unit group |
| `GET` | `/api/v1/unit-groups/{id}` | Retrieve unit group |
| `PUT` | `/api/v1/unit-groups/{id}` | Update unit group |
| `DELETE` | `/api/v1/unit-groups/{id}` | Delete unit group |
| `GET` | `/api/v1/unit-groups/{id}/units` | List units of a group |
| `POST` | `/api/v1/unit-groups/{id}/units` | Create unit |
| `GET` | `/api/v1/value-lists` | List value lists |
| `POST` | `/api/v1/value-lists` | Create value list |
| `GET` | `/api/v1/value-lists/{id}` | Retrieve value list |
| `PUT` | `/api/v1/value-lists/{id}` | Update value list |
| `DELETE` | `/api/v1/value-lists/{id}` | Delete value list |
| `GET` | `/api/v1/value-lists/{id}/entries` | List value list entries |
| `POST` | `/api/v1/value-lists/{id}/entries` | Create value list entry |
| `GET` | `/api/v1/attribute-views` | List attribute views |
| `POST` | `/api/v1/attribute-views` | Create attribute view |
| `GET` | `/api/v1/attribute-views/{id}` | Retrieve attribute view |
| `PUT` | `/api/v1/attribute-views/{id}` | Update attribute view |
| `DELETE` | `/api/v1/attribute-views/{id}` | Delete attribute view |

## List Attributes

```
GET /api/v1/attributes
```

### Query Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `filter[type]` | String | -- | Filter by attribute type |
| `filter[searchable]` | Boolean | -- | Only searchable attributes |
| `filter[required]` | Boolean | -- | Only required attributes |
| `sort` | String | `position` | Sort field |
| `search` | String | -- | Full-text search (name, code) |
| `page` | Integer | `1` | Page number |
| `per_page` | Integer | `25` | Entries per page (max. 100) |
| `lang` | String | -- | Language for translatable fields |

### Example Request

```bash
curl -X GET "https://pim.example.com/api/v1/attributes?filter[type]=text&sort=name&lang=de" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Example Response (200 OK)

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

## Create Attribute

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

### Response (201 Created)

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

## Attribute Types

The available attribute types can be retrieved via the following endpoint:

```
GET /api/v1/attribute-types
```

### Example Response

```json
{
  "data": [
    { "code": "text", "label": "Single-line text" },
    { "code": "textarea", "label": "Multi-line text" },
    { "code": "number", "label": "Number" },
    { "code": "boolean", "label": "Yes/No" },
    { "code": "date", "label": "Date" },
    { "code": "select", "label": "Single select" },
    { "code": "multiselect", "label": "Multi select" },
    { "code": "media", "label": "Media" }
  ]
}
```

For more information on working with attributes in the user interface, see [Attributes (Usage)](/en/usage/attributes).
