# Publixx PIM — Auth & Rechteverwaltung

> **Zweck:** Authentifizierung und Autorisierung. Verwende diesen Skill bei Login, Token-Management, Rollen, Berechtigungen und Policies.

---

## Stack

- **Auth:** Laravel Sanctum (Bearer Tokens)
- **Rechte:** Spatie Laravel-Permission (Rollen + Permissions mit Cache)
- **Optional:** LDAP/SSO via Laravel Socialite

---

## Authentifizierung

### Token-Lifecycle

```
POST /api/v1/auth/login    → { email, password } → Bearer Token
POST /api/v1/auth/logout   → Token invalidieren
POST /api/v1/auth/refresh  → Neues Token
GET  /api/v1/auth/me       → User + Rollen + Permissions
```

### Token-Konfiguration

- Ablaufzeit: 24h (konfigurierbar)
- Rate Limit: 60 req/min (Standard), 600/min (Export)
- CORS: Konfiguriert für Frontend-Domain

---

## Rollenmodell

### Vordefinierte Rollen

| Rolle | Beschreibung | Kern-Berechtigungen |
|-------|-------------|-------------------|
| Admin | Voller Zugriff | `*` (alle) |
| Data Steward | Strukturverwaltung | attributes.*, hierarchies.*, unit-groups.*, value-lists.* |
| Product Manager | Datenpflege | products.view/edit/create, media.*, prices.view |
| Viewer | Nur-Lese | *.view |
| Export Manager | Export + Publixx | export.*, publixx-mappings.*, pxf-templates.* |

### Berechtigungsgranularität

```
Schema: {entität}.{aktion}[:{einschränkung}]

Beispiele:
products.view                    Produkte sehen
products.create                  Anlegen
products.edit                    Bearbeiten
products.edit:eshop_view         Nur E-Shop-Attribute bearbeiten
products.edit:node-uuid-123      Nur Produkte unter einem Hierarchieknoten
products.delete                  Löschen

attributes.view
attributes.create
attributes.edit
attributes.delete

hierarchies.view
hierarchies.edit
hierarchy-nodes.create
hierarchy-nodes.move

export.view
export.execute
export.mappings.edit

users.view
users.create
users.edit
users.delete
roles.edit
```

---

## Laravel Policy Beispiel

```php
class ProductPolicy {
    public function view(User $user, Product $product): bool {
        return $user->hasPermissionTo('products.view');
    }
    
    public function update(User $user, Product $product): bool {
        if (!$user->hasPermissionTo('products.edit')) return false;
        
        // Hierarchie-Einschränkung prüfen
        $nodePerms = $user->getPermissionsViaRoles()
            ->filter(fn($p) => str_starts_with($p->name, 'products.edit:node-'));
        
        if ($nodePerms->isNotEmpty()) {
            $allowedNodeIds = $nodePerms->map(fn($p) => str_replace('products.edit:node-', '', $p->name));
            return $allowedNodeIds->contains($product->master_hierarchy_node_id);
        }
        
        return true;
    }
}
```

---

## User-Entität

```sql
CREATE TABLE users (
  id CHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,        -- bcrypt
  language VARCHAR(5) DEFAULT 'de',
  is_active BOOLEAN DEFAULT true,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

---

## API-Endpunkte

```
GET    /users                   Alle Benutzer
POST   /users                   Anlegen {name, email, password, role_id}
PUT    /users/{id}              Aktualisieren
DELETE /users/{id}              Löschen

GET    /roles                   Alle Rollen (?include=permissions)
POST   /roles                   Anlegen {name, permissions: [...]}
PUT    /roles/{id}              Aktualisieren
DELETE /roles/{id}              Löschen
PUT    /roles/{id}/permissions  Berechtigungen setzen {permissions: ['products.edit', ...]}
```
