---
title: API-Keys
---

# API-Keys

API-Keys sind **langlebige Bearer-Tokens**, die an einen Benutzer gebunden sind. Sie eignen sich für externe Integrationen, Skripte, den API-Tester oder die anyPIM-Synchronisation.

## Konzept

Im Gegensatz zum Login-Token (24 Stunden gültig, an eine Browser-Session gebunden) sind API-Keys:

- **Langlebig** — optional mit Ablaufdatum, sonst unbegrenzt gültig
- **Benutzergebunden** — erben automatisch die Berechtigungen (Rolle) des Benutzers
- **Für Machine-to-Machine** — ideal für Integrationen, Cronjobs und externe Systeme
- **Unabhängig vom Login** — werden beim Anmelden nicht gelöscht

```
┌──────────────────────────────────────────────┐
│  Benutzer: "Integration ERP"                 │
│  Rolle: "API Vollzugriff"                    │
│                                              │
│  ├── Session-Token (Login, 24h)              │
│  ├── API-Key "SAP Anbindung" (unbegrenzt)    │
│  └── API-Key "Cronjob Export" (bis 31.12.)   │
│                                              │
│  Berechtigungen = Rolle des Benutzers        │
└──────────────────────────────────────────────┘
```

## Endpunkte

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| `GET` | `/api/v1/user/api-keys` | Eigene API-Keys auflisten |
| `POST` | `/api/v1/user/api-keys` | Neuen API-Key erstellen |
| `DELETE` | `/api/v1/user/api-keys/{id}` | API-Key widerrufen |
| `GET` | `/api/v1/admin/api-keys` | Alle API-Keys auflisten (Admin) |
| `POST` | `/api/v1/admin/users/{userId}/api-keys` | Key für anderen User erstellen (Admin) |
| `DELETE` | `/api/v1/admin/api-keys/{id}` | Beliebigen Key löschen (Admin) |

## API-Key erstellen

### Anfrage

```
POST /api/v1/user/api-keys
Authorization: Bearer {session-token}
```

**Request Body:**

```json
{
  "name": "SAP Integration",
  "description": "Produktdaten-Export für SAP",
  "expires_at": "2027-12-31"
}
```

| Feld | Typ | Pflicht | Beschreibung |
|---|---|---|---|
| `name` | String | Ja | Name des API-Keys (max. 255 Zeichen) |
| `description` | String | Nein | Beschreibung des Verwendungszwecks |
| `expires_at` | Date | Nein | Ablaufdatum (ISO 8601). Ohne Angabe: unbegrenzt |

### Antwort (201 Created)

```json
{
  "data": {
    "id": 42,
    "name": "SAP Integration",
    "description": "Produktdaten-Export für SAP",
    "token": "3|a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6",
    "expires_at": "2027-12-31T00:00:00+00:00"
  },
  "warning": "Der API-Key wird nur einmalig angezeigt. Bitte sicher aufbewahren."
}
```

::: warning Wichtig
Das `token`-Feld wird **nur bei der Erstellung** im Klartext angezeigt. Nach dem Schliessen der Antwort kann der Key nicht mehr abgerufen werden. Bitte sicher aufbewahren.
:::

## API-Key verwenden

Der API-Key wird als Bearer-Token im `Authorization`-Header gesendet — genau wie ein Login-Token:

### cURL

```bash
curl -X GET "https://pim.example.com/api/v1/products" \
  -H "Authorization: Bearer 3|a1b2c3d4e5f6..." \
  -H "Accept: application/json"
```

### JavaScript

```javascript
const response = await fetch('https://pim.example.com/api/v1/products', {
  headers: {
    'Authorization': 'Bearer 3|a1b2c3d4e5f6...',
    'Accept': 'application/json'
  }
});
```

### PHP

```php
$response = Http::withToken('3|a1b2c3d4e5f6...')
    ->get('https://pim.example.com/api/v1/products');
```

## Eigene Keys auflisten

```
GET /api/v1/user/api-keys
Authorization: Bearer {token}
```

**Antwort:**

```json
{
  "data": [
    {
      "id": 42,
      "name": "SAP Integration",
      "description": "Produktdaten-Export für SAP",
      "last_used_at": "2026-03-29T14:30:00Z",
      "expires_at": "2027-12-31T00:00:00Z",
      "created_at": "2026-03-15T10:00:00Z"
    }
  ]
}
```

## API-Key widerrufen

```
DELETE /api/v1/user/api-keys/42
Authorization: Bearer {token}
```

**Antwort:** `204 No Content`

::: tip
Nach dem Widerrufen ist der Key sofort ungültig. Alle Systeme, die den Key nutzen, verlieren den Zugriff.
:::

## Berechtigungen

API-Keys erben die **Berechtigungen der Rolle** des Benutzers. Es gibt kein separates Scope-System.

| Benutzer-Rolle | API-Key kann... |
|---|---|
| Admin | Alles (alle Endpunkte) |
| Editor | Produkte lesen/bearbeiten, Media verwalten, Export |
| Viewer | Nur lesen (Produkte, Attribute, Hierarchien) |
| Eigene Rolle | Genau die Berechtigungen der Rolle |

**Tipp:** Für eingeschränkte Integrationen erstellen Sie einen eigenen Benutzer mit einer passenden Rolle (z.B. "API Nur-Lesen") und erstellen den API-Key für diesen Benutzer.

## Verwendung mit anyPIM Sync

Für die Synchronisation zwischen anyPIM-Instanzen:

1. Erstellen Sie auf der **Remote-Instanz** einen Benutzer mit der Rolle "Admin" (oder einer Sync-spezifischen Rolle)
2. Erstellen Sie einen **API-Key** für diesen Benutzer
3. Tragen Sie den API-Key in der **lokalen Instanz** unter **Connectoren → anyPIM Sync → Neue Verbindung** ein
4. Der API-Key wird verschlüsselt gespeichert und für alle Sync-Operationen verwendet

## Sicherheitshinweise

- **Keys sicher aufbewahren**: API-Keys gewähren denselben Zugriff wie der zugehörige Benutzer. Behandeln Sie sie wie Passwörter.
- **Ablaufdatum setzen**: Für temporäre Integrationen immer ein Ablaufdatum vergeben.
- **Eigene Benutzer für Integrationen**: Erstellen Sie dedizierte Benutzer mit eingeschränkten Rollen statt Admin-Keys zu teilen.
- **Keys regelmässig rotieren**: Widerrufen Sie alte Keys und erstellen Sie neue, um das Risiko bei kompromittierten Keys zu minimieren.
- **HTTPS verwenden**: Übertragen Sie API-Keys niemals über unverschlüsselte Verbindungen.
