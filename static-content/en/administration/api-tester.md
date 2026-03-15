---
title: API Tester
---

# API Tester

The API Tester is a built-in tool within anyPIM for testing and exploring the REST API. It enables administrators and developers to call API endpoints directly from the application interface without needing external tools such as Postman or cURL. Authentication is handled automatically.

## Overview

Access the API Tester via **Administration > API Tester** in the sidebar. The interface is divided into three areas:

| Area | Description |
|---|---|
| **Endpoint Selection** | Dropdown to select the API endpoint with HTTP method |
| **Parameter Input** | Form fields for path, query, and body parameters |
| **Response Display** | Presentation of the JSON response with syntax highlighting |

::: tip Note
The API Tester uses the current user session for authentication. All requests are executed with the permissions of the logged-in user. No separate API token is required.
:::

## Selecting an Endpoint

The endpoint area provides a categorized dropdown list of all available API endpoints:

### Available Endpoint Groups

| Group | Endpoints | Description |
|---|---|---|
| **Products** | `GET /api/v1/products`, `POST /api/v1/products`, `PUT /api/v1/products/{id}`, `DELETE /api/v1/products/{id}` | CRUD operations for products |
| **Attributes** | `GET /api/v1/attributes`, `POST /api/v1/attributes`, `PUT /api/v1/attributes/{id}` | Attribute management |
| **Hierarchies** | `GET /api/v1/hierarchies`, `GET /api/v1/hierarchies/{id}/nodes` | Hierarchy and node queries |
| **Media** | `GET /api/v1/media`, `POST /api/v1/media`, `DELETE /api/v1/media/{id}` | Media management |
| **Prices** | `GET /api/v1/prices`, `PUT /api/v1/prices/{id}` | Price queries and updates |
| **Export** | `GET /api/v1/export/products`, `POST /api/v1/export/products/bulk` | Export endpoints |
| **PQL** | `POST /api/v1/pql/query` | PQL queries |

Select an endpoint from the dropdown or use the search function to quickly locate a specific endpoint.

## Configuring Parameters

After selecting an endpoint, the available parameters are displayed automatically.

### Path Parameters

Path parameters such as `{id}` are rendered as input fields above the query parameters. They are required for endpoint execution.

### Query Parameters

Query parameters are displayed as optional input fields. They control filters, pagination, and other options.

| Parameter | Type | Description |
|---|---|---|
| `page` | Integer | Page number for paginated results |
| `per_page` | Integer | Number of results per page |
| `include` | String | Comma-separated list of relations to include |
| `filter[...]` | String | Filter criteria |

### Request Body

For `POST` and `PUT` endpoints, a JSON editor is displayed where you can enter the request body. The editor provides syntax highlighting and validation.

::: warning Warning
Write operations (`POST`, `PUT`, `DELETE`) modify actual data in your anyPIM instance. Use these functions carefully, especially in production environments.
:::

## Executing a Request

1. Select the desired endpoint.
2. Fill in the required parameters.
3. Click **Execute**.
4. The response is displayed in the lower area.

### Response Display

The response is presented across several tabs:

| Tab | Content |
|---|---|
| **Body** | JSON response with syntax highlighting and indentation |
| **Headers** | HTTP response headers |
| **Status** | HTTP status code and response time |

### Status Code Reference

| Code | Meaning |
|---|---|
| `200 OK` | Request successful |
| `201 Created` | Resource successfully created |
| `400 Bad Request` | Invalid parameters or request body |
| `401 Unauthorized` | Missing or invalid authentication |
| `403 Forbidden` | No permission for this action |
| `404 Not Found` | Resource not found |
| `422 Unprocessable Entity` | Validation error |
| `429 Too Many Requests` | Rate limit reached |
| `500 Internal Server Error` | Server-side error |

## Request History

The API Tester stores the last 50 requests within the current session. Use the **History** button to view and re-execute previous requests.

For each history entry, the following is saved:

- HTTP method and endpoint
- Parameters and request body used
- Response status code
- Execution timestamp

::: tip Note
The request history is stored in browser storage and is lost when the browser is closed. For permanent documentation, consider exporting your requests.
:::

## Best Practices

- **Read First** -- Start with `GET` requests to understand the data structure before executing write operations.
- **Use Test Data** -- Whenever possible, use a test environment to try out write operations.
- **Document Parameters** -- Take note of working parameter combinations for later use in your integrations.
- **Test Error Handling** -- Deliberately send invalid requests to familiarize yourself with the API's error responses.

## Next Steps

- Read the full [API documentation](../api/index) to learn about all available endpoints in detail.
- Learn more about [API authentication](../api/authentifizierung) for integrating external systems.
- Return to the [overview](../bedienung/index) to explore other functional areas.
