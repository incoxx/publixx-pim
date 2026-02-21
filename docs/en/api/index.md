---
title: API Reference
---

# API Reference

The Publixx PIM provides a complete REST API with over 90 endpoints that make all system functions programmatically accessible. The API follows consistent conventions and uses JSON as data format.

## Base URL

All API endpoints are located under:

```
https://pim.your-domain.com/api/v1
```

For local development:

```
http://localhost:8000/api/v1
```

## Authentication

The API uses **Bearer token authentication** via Laravel Sanctum. Every API call requires a valid token in the `Authorization` header:

```
Authorization: Bearer {token}
```

## Conventions

### Data Format

All requests and responses use **JSON**. Set the appropriate headers:

```
Content-Type: application/json
Accept: application/json
```

### Pagination

List endpoints support pagination via query parameters:

| Parameter | Type | Default | Description |
|---|---|---|---|
| `page` | Integer | `1` | Current page |
| `per_page` | Integer | `25` | Items per page (max. 100) |

### Sorting

Sorting is controlled via the `sort` parameter. Ascending is default, a minus prefix sorts descending:

```
GET /api/v1/products?sort=name          # Ascending by name
GET /api/v1/products?sort=-created_at   # Descending by creation date
```

### Filtering

Filters are passed as query parameters:

```
GET /api/v1/products?filter[status]=active&filter[product_type]=simple
```

### Includes (Eager Loading)

Related resources can be loaded via the `include` parameter:

```
GET /api/v1/products?include=variants,media,prices
```

## Endpoint Groups

The API is organized into the following areas:

### Authentication

Login, logout, token management, and user information.

### Products

CRUD operations for products, attribute values, variants, media, prices, and relations.

### Attributes

Management of attributes, attribute types, unit groups, units, value lists, value list entries, and attribute views.

### Hierarchies

Management of hierarchies, tree structures, nodes, and node-specific attribute assignments.

### PQL

Execute, validate, and analyze PQL queries.

### Export

Export endpoints for JSON and Publixx format.

### Import

Import endpoints for upload, validation, and execution.

### Users

CRUD operations for users, roles, and permissions.

### Languages

Management of available content languages.

### Media

Upload, management, and assignment of media.

### Prices

Management of price types, currencies, and product prices.

::: tip
The German API documentation includes detailed endpoint descriptions with examples. See the [German API reference](/de/api/) for comprehensive documentation.
:::
