---
title: Authentication
---

# Authentication

The anyPIM API uses **Bearer token authentication** via Laravel Sanctum. Every API access requires a valid token that is issued upon login.

## Endpoint Overview

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/auth/login` | Login and token creation |
| `POST` | `/api/v1/auth/logout` | Logout and token invalidation |
| `GET` | `/api/v1/auth/me` | Retrieve current user |
| `POST` | `/api/v1/auth/refresh` | Refresh token |

## Login

Authenticates a user with email and password and returns a Bearer token.

### Request

```
POST /api/v1/auth/login
```

**Request Body:**

```json
{
  "email": "admin@example.com",
  "password": "your_password"
}
```

### Response (200 OK)

```json
{
  "data": {
    "token": "1|a3b5c7d9e1f3g5h7i9j1k3l5m7n9o1p3q5r7s9t1",
    "token_type": "Bearer",
    "expires_at": "2025-07-16T14:30:00Z"
  }
}
```

### Error (401 Unauthorized)

```json
{
  "type": "https://pim.example.com/docs/errors/authentication-error",
  "title": "Authentication failed",
  "status": 401,
  "detail": "The provided credentials are invalid."
}
```

### cURL Example

```bash
curl -X POST "https://pim.example.com/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "your_password"
  }'
```

## Logout

Invalidates the current token. After logout, the token can no longer be used for API access.

### Request

```
POST /api/v1/auth/logout
```

**Header:**

```
Authorization: Bearer {token}
```

### Response (200 OK)

```json
{
  "message": "Successfully logged out."
}
```

### cURL Example

```bash
curl -X POST "https://pim.example.com/api/v1/auth/logout" \
  -H "Authorization: Bearer 1|a3b5c7d9e1f3g5h7i9j1k3l5m7n9o1p3q5r7s9t1" \
  -H "Accept: application/json"
```

## Current User

Returns information about the currently authenticated user, including assigned roles and permissions.

### Request

```
GET /api/v1/auth/me
```

**Header:**

```
Authorization: Bearer {token}
```

### Response (200 OK)

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Max Mustermann",
    "email": "admin@example.com",
    "language": "de",
    "created_at": "2025-01-15T10:30:00Z",
    "roles": [
      {
        "id": "role-uuid-admin",
        "name": "Administrator",
        "slug": "admin"
      }
    ],
    "permissions": [
      "products.create",
      "products.read",
      "products.update",
      "products.delete",
      "attributes.manage",
      "hierarchies.manage",
      "users.manage",
      "import.execute",
      "export.execute"
    ]
  }
}
```

### cURL Example

```bash
curl -X GET "https://pim.example.com/api/v1/auth/me" \
  -H "Authorization: Bearer 1|a3b5c7d9e1f3g5h7i9j1k3l5m7n9o1p3q5r7s9t1" \
  -H "Accept: application/json"
```

## Refresh Token

Creates a new token and invalidates the current one. This extends the session without requiring the user to log in again.

### Request

```
POST /api/v1/auth/refresh
```

**Header:**

```
Authorization: Bearer {token}
```

### Response (200 OK)

```json
{
  "data": {
    "token": "2|x1y2z3a4b5c6d7e8f9g0h1i2j3k4l5m6n7o8p9q0",
    "token_type": "Bearer",
    "expires_at": "2025-07-17T14:30:00Z"
  }
}
```

### cURL Example

```bash
curl -X POST "https://pim.example.com/api/v1/auth/refresh" \
  -H "Authorization: Bearer 1|a3b5c7d9e1f3g5h7i9j1k3l5m7n9o1p3q5r7s9t1" \
  -H "Accept: application/json"
```

## Token Configuration

### Validity Period

Tokens have a default validity of **24 hours** from the time of creation. After expiration, the user must log in again or use the `refresh` endpoint before the token expires.

The validity period can be adjusted in the `.env` configuration:

```dotenv
SANCTUM_TOKEN_EXPIRATION=1440   # Minutes (default: 1440 = 24 hours)
```

### Rate Limiting

The login endpoint is additionally protected by its own rate limit to mitigate brute-force attacks:

| Endpoint | Limit |
|---|---|
| `POST /auth/login` | 5 attempts per minute per IP |
| `POST /auth/refresh` | 10 attempts per minute per token |
| All other endpoints | 60 attempts per minute per token |

When the rate limit is exceeded, a `429 Too Many Requests` status is returned:

```json
{
  "type": "https://pim.example.com/docs/errors/rate-limit-exceeded",
  "title": "Rate limit exceeded",
  "status": 429,
  "detail": "Too many requests. Please try again in 45 seconds."
}
```

## Using Tokens in API Clients

### cURL

```bash
curl -X GET "https://pim.example.com/api/v1/products" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### JavaScript (Fetch)

```javascript
const response = await fetch('https://pim.example.com/api/v1/products', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});
const data = await response.json();
```

### PHP (Guzzle)

```php
$client = new \GuzzleHttp\Client();
$response = $client->get('https://pim.example.com/api/v1/products', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ],
]);
$data = json_decode($response->getBody(), true);
```

## Security Notes

- **Do not store tokens in frontend code**: Use `httpOnly` cookies or secure token storage.
- **Use HTTPS**: Never transmit tokens over unencrypted HTTP connections.
- **Refresh tokens regularly**: Use the `refresh` endpoint to minimize the attack surface in case of compromised tokens.
- **Logout on inactivity**: Implement automatic logout after a defined period of inactivity.
