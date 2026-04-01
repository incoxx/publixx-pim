# anyPIM — Auth & Permission Management

> **Purpose:** Authentication and authorization. Use this skill for login, token management, roles, permissions and policies.

---

## Stack

- **Auth:** Laravel Sanctum (Bearer Tokens)
- **Permissions:** Spatie Laravel-Permission (Roles + Permissions with cache)
- **Optional:** LDAP/SSO via Laravel Socialite

---

## Authentication

### Token Lifecycle

```
POST /api/v1/auth/login    → { email, password } → Bearer Token
POST /api/v1/auth/logout   → Invalidate token
POST /api/v1/auth/refresh  → New token
GET  /api/v1/auth/me       → User + Roles + Permissions
```

### Token Configuration

- Expiration: 24h (configurable)
- Rate Limit: 60 req/min (default), 600/min (export)
- CORS: Configured for frontend domain

---

## Role Model

### Predefined Roles

| Role | Description | Core Permissions |
|------|-------------|-----------------|
| Admin | Full access | `*` (all) |
| Data Steward | Structure management | attributes.*, hierarchies.*, unit-groups.*, value-lists.* |
| Product Manager | Data maintenance | products.view/edit/create, media.*, prices.view |
| Viewer | Read-only | *.view |
| Export Manager | Export + Publixx | export.*, publixx-mappings.* |
| API Designer | API Templates | api-templates.* |
| Project Management | Dashboard, Workflows, Teams, Projects | dashboard.*, workflows.*, workflow-statuses.*, teams.*, projects.* |

### Permission Granularity

```
Schema: {entity}.{action}[:{restriction}]

Examples:
products.view                    View products
products.create                  Create
products.edit                    Edit
products.edit:eshop_view         Edit only e-shop attributes
products.edit:node-uuid-123      Edit only products under a hierarchy node
products.delete                  Delete

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

## Laravel Policy Example

```php
class ProductPolicy {
    public function view(User $user, Product $product): bool {
        return $user->hasPermissionTo('products.view');
    }

    public function update(User $user, Product $product): bool {
        if (!$user->hasPermissionTo('products.edit')) return false;

        // Check hierarchy restriction
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

## User Entity

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

## API Endpoints

```
GET    /users                   All users
POST   /users                   Create {name, email, password, role_id}
PUT    /users/{id}              Update
DELETE /users/{id}              Delete

GET    /roles                   All roles (?include=permissions)
POST   /roles                   Create {name, permissions: [...]}
PUT    /roles/{id}              Update
DELETE /roles/{id}              Delete
PUT    /roles/{id}/permissions  Set permissions {permissions: ['products.edit', ...]}
```
