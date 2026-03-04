---
title: Authentifizierung
---

# Authentifizierung

Die Publixx PIM API verwendet **Bearer-Token-Authentifizierung** über Laravel Sanctum. Jeder API-Zugriff erfordert ein gültiges Token, das beim Login ausgestellt wird.

## Übersicht der Endpunkte

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| `POST` | `/api/v1/auth/login` | Anmeldung und Token-Erstellung |
| `POST` | `/api/v1/auth/logout` | Abmeldung und Token-Invalidierung |
| `GET` | `/api/v1/auth/me` | Aktuellen Benutzer abrufen |
| `POST` | `/api/v1/auth/refresh` | Token erneuern |

## Login

Authentifiziert einen Benutzer mit E-Mail und Passwort und gibt ein Bearer-Token zurück.

### Anfrage

```
POST /api/v1/auth/login
```

**Request Body:**

```json
{
  "email": "admin@example.com",
  "password": "ihr_passwort"
}
```

### Antwort (200 OK)

```json
{
  "data": {
    "token": "1|a3b5c7d9e1f3g5h7i9j1k3l5m7n9o1p3q5r7s9t1",
    "token_type": "Bearer",
    "expires_at": "2025-07-16T14:30:00Z"
  }
}
```

### Fehler (401 Unauthorized)

```json
{
  "type": "https://pim.example.com/docs/errors/authentication-error",
  "title": "Authentifizierung fehlgeschlagen",
  "status": 401,
  "detail": "Die angegebenen Zugangsdaten sind ungültig."
}
```

### cURL-Beispiel

```bash
curl -X POST "https://pim.example.com/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "ihr_passwort"
  }'
```

## Logout

Invalidiert das aktuelle Token. Nach dem Logout kann das Token nicht mehr für API-Zugriffe verwendet werden.

### Anfrage

```
POST /api/v1/auth/logout
```

**Header:**

```
Authorization: Bearer {token}
```

### Antwort (200 OK)

```json
{
  "message": "Erfolgreich abgemeldet."
}
```

### cURL-Beispiel

```bash
curl -X POST "https://pim.example.com/api/v1/auth/logout" \
  -H "Authorization: Bearer 1|a3b5c7d9e1f3g5h7i9j1k3l5m7n9o1p3q5r7s9t1" \
  -H "Accept: application/json"
```

## Aktueller Benutzer

Gibt die Informationen des aktuell authentifizierten Benutzers zurück, einschliesslich der zugewiesenen Rollen und Berechtigungen.

### Anfrage

```
GET /api/v1/auth/me
```

**Header:**

```
Authorization: Bearer {token}
```

### Antwort (200 OK)

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

### cURL-Beispiel

```bash
curl -X GET "https://pim.example.com/api/v1/auth/me" \
  -H "Authorization: Bearer 1|a3b5c7d9e1f3g5h7i9j1k3l5m7n9o1p3q5r7s9t1" \
  -H "Accept: application/json"
```

## Token erneuern

Erstellt ein neues Token und invalidiert das aktuelle. Dies verlängert die Sitzung, ohne dass der Benutzer sich erneut anmelden muss.

### Anfrage

```
POST /api/v1/auth/refresh
```

**Header:**

```
Authorization: Bearer {token}
```

### Antwort (200 OK)

```json
{
  "data": {
    "token": "2|x1y2z3a4b5c6d7e8f9g0h1i2j3k4l5m6n7o8p9q0",
    "token_type": "Bearer",
    "expires_at": "2025-07-17T14:30:00Z"
  }
}
```

### cURL-Beispiel

```bash
curl -X POST "https://pim.example.com/api/v1/auth/refresh" \
  -H "Authorization: Bearer 1|a3b5c7d9e1f3g5h7i9j1k3l5m7n9o1p3q5r7s9t1" \
  -H "Accept: application/json"
```

## Token-Konfiguration

### Gültigkeitsdauer

Tokens haben eine Standardgültigkeit von **24 Stunden** ab dem Zeitpunkt der Erstellung. Nach Ablauf muss sich der Benutzer erneut anmelden oder den `refresh`-Endpunkt nutzen, bevor das Token abläuft.

Die Gültigkeitsdauer kann in der `.env`-Konfiguration angepasst werden:

```dotenv
SANCTUM_TOKEN_EXPIRATION=1440   # Minuten (Standard: 1440 = 24 Stunden)
```

### Rate Limiting

Der Login-Endpunkt ist zusätzlich durch ein eigenes Rate Limit geschützt, um Brute-Force-Angriffe zu erschweren:

| Endpunkt | Limit |
|---|---|
| `POST /auth/login` | 5 Versuche pro Minute pro IP |
| `POST /auth/refresh` | 10 Versuche pro Minute pro Token |
| Alle anderen Endpunkte | 60 Versuche pro Minute pro Token |

Bei Überschreitung des Rate Limits wird ein `429 Too Many Requests`-Status zurückgegeben:

```json
{
  "type": "https://pim.example.com/docs/errors/rate-limit-exceeded",
  "title": "Rate Limit überschritten",
  "status": 429,
  "detail": "Zu viele Anfragen. Bitte versuchen Sie es in 45 Sekunden erneut."
}
```

## Token in API-Clients verwenden

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

## Sicherheitshinweise

- **Tokens nicht im Frontend-Code speichern**: Verwenden Sie `httpOnly`-Cookies oder sichere Token-Speicher.
- **HTTPS verwenden**: Übertragen Sie Tokens niemals über unverschlüsselte HTTP-Verbindungen.
- **Tokens regelmässig erneuern**: Nutzen Sie den `refresh`-Endpunkt, um die Angriffsfläche bei kompromittierten Tokens zu minimieren.
- **Logout bei Inaktivität**: Implementieren Sie eine automatische Abmeldung nach einer definierten Inaktivitätsperiode.
