# anyPIM — Audit Trail & System Journal

> **Purpose:** Audit logging, user activity tracking, access links, and system journal. Use this skill when implementing change tracking, user audit logs, access link management, or data retention policies.

---

## Audit Log Data Model

The `audit_logs` table is defined in `01-datenmodell`. It records all create/update/delete operations on PIM entities automatically via Laravel Model Observers.

### audit_logs (Reference)

```
id, user_id (FK), auditable_type VARCHAR(100), auditable_id CHAR(36),
action ENUM('created','updated','deleted'),
old_values JSON nullable, new_values JSON nullable,
ip_address VARCHAR(45), user_agent TEXT, created_at
INDEX(auditable_type, auditable_id), INDEX(user_id), INDEX(created_at)
```

### Audit Log Entry Example

```json
{
  "id": "uuid-audit-1",
  "user_id": "uuid-user-123",
  "auditable_type": "App\\Models\\Product",
  "auditable_id": "uuid-product-456",
  "action": "updated",
  "old_values": {
    "name": "Bohrmaschine ProMax",
    "status": "draft"
  },
  "new_values": {
    "name": "Bohrmaschine ProMax 800",
    "status": "active"
  },
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0 ...",
  "created_at": "2026-03-15T10:30:00Z"
}
```

Only changed fields are stored in `old_values` / `new_values`, not the entire record.

---

## User Audit Logs

A separate table tracks user-specific security events.

### user_audit_logs

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | CHAR(36) PK | No | UUID |
| user_id | FK → users.id | No | The affected user |
| action | ENUM('login','logout','login_failed','password_changed','role_changed','permission_changed','created','deactivated','activated') | No | Action type |
| performed_by | FK → users.id | Yes | Who performed the action (NULL for self-actions like login) |
| details | JSON | Yes | Additional context |
| ip_address | VARCHAR(45) | Yes | IP address |
| user_agent | TEXT | Yes | Browser/client info |
| created_at | TIMESTAMP | No | |

```sql
CREATE TABLE user_audit_logs (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  action ENUM('login','logout','login_failed','password_changed','role_changed',
              'permission_changed','created','deactivated','activated') NOT NULL,
  performed_by CHAR(36) NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
);
```

### User Audit Entry Example

```json
{
  "action": "role_changed",
  "user_id": "uuid-user-123",
  "performed_by": "uuid-admin-1",
  "details": {
    "old_roles": ["Product Manager"],
    "new_roles": ["Product Manager", "Export Manager"]
  }
}
```

---

## System Journal

The system journal is an aggregated, read-only view across both audit tables. It provides a unified timeline of all changes in the system, accessible to administrators.

### Journal Query

The journal endpoint merges `audit_logs` and `user_audit_logs` into a single chronological feed, sorted by `created_at` descending.

```
GET /api/v1/journal?filter[type]=all&per_page=50
```

| Filter | Values | Description |
|--------|--------|-------------|
| type | `all`, `data`, `user` | Data changes only, user events only, or both |
| entity | `Product`, `Attribute`, etc. | Filter by entity type |
| user_id | UUID | Filter by acting user |
| date_from | ISO date | Start date |
| date_to | ISO date | End date |
| action | `created`, `updated`, `deleted`, `login`, etc. | Specific action |

---

## Access Links

Shareable, token-based access links with configurable permissions and expiration.

### access_links

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | CHAR(36) PK | No | UUID |
| token | VARCHAR(64) UNIQUE | No | Secure random token |
| name | VARCHAR(255) | No | Descriptive name for the link |
| permissions | JSON | No | Array of granted permissions |
| expires_at | DATETIME | Yes | Expiration date (NULL = never) |
| max_uses | INT | Yes | Maximum number of uses (NULL = unlimited) |
| current_uses | INT | No | Default: 0 |
| is_active | BOOLEAN | No | Default: true |
| created_by | FK → users.id | No | User who created the link |
| last_used_at | DATETIME | Yes | Last usage timestamp |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | |

```sql
CREATE TABLE access_links (
  id CHAR(36) PRIMARY KEY,
  token VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  permissions JSON NOT NULL,
  expires_at DATETIME NULL,
  max_uses INT NULL,
  current_uses INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_by CHAR(36) NOT NULL,
  last_used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_token (token)
);
```

### Access Link Example

```json
{
  "id": "uuid-link-1",
  "token": "a1b2c3d4e5f6...",
  "name": "External reviewer — Q2 catalog",
  "permissions": ["products.view", "media.view", "prices.view"],
  "expires_at": "2026-06-30T23:59:59Z",
  "max_uses": 100,
  "current_uses": 12,
  "is_active": true,
  "created_by": "uuid-admin-1"
}
```

---

## API Endpoints

### Audit Logs

```
GET    /api/v1/audit-logs                      List audit logs (paginated, filterable)
GET    /api/v1/audit-logs/{id}                 Get single audit log entry
POST   /api/v1/audit-logs/export               Export audit logs (CSV/XLSX)
```

### Filters for Audit Logs

```
GET /api/v1/audit-logs?filter[auditable_type]=Product&filter[action]=updated
GET /api/v1/audit-logs?filter[user_id]={uuid}&filter[date_from]=2026-01-01
GET /api/v1/audit-logs?filter[auditable_id]={uuid}    # All changes for a specific entity
```

### User Audit Logs

```
GET    /api/v1/user-audit-logs                 List user audit logs
GET    /api/v1/user-audit-logs?filter[user_id]={uuid}  Filter by user
GET    /api/v1/user-audit-logs?filter[action]=login_failed  Filter by action
```

### System Journal

```
GET    /api/v1/journal                         Unified timeline (merged view)
```

### Access Links

```
GET    /api/v1/access-links                    List all access links
POST   /api/v1/access-links                    Create access link
GET    /api/v1/access-links/{id}               Get access link details
PUT    /api/v1/access-links/{id}               Update access link
DELETE /api/v1/access-links/{id}               Revoke and delete access link
POST   /api/v1/access-links/redeem             Redeem a token → get Bearer token
```

### Create Access Link

```json
// POST /api/v1/access-links
{
  "name": "External reviewer — Q2 catalog",
  "permissions": ["products.view", "media.view", "prices.view"],
  "expires_at": "2026-06-30T23:59:59Z",
  "max_uses": 100
}
```

### Redeem Access Link

```json
// POST /api/v1/access-links/redeem
{
  "token": "a1b2c3d4e5f6..."
}
// Response: { "access_token": "bearer-token-...", "expires_in": 3600 }
```

---

## Retention & Cleanup

| Log Type | Default Retention | Configurable |
|----------|-------------------|-------------|
| audit_logs | 365 days | Yes, via `AUDIT_RETENTION_DAYS` env |
| user_audit_logs | 730 days (2 years) | Yes, via `USER_AUDIT_RETENTION_DAYS` env |
| access_links (expired) | 90 days after expiration | Yes |

### Artisan Commands

```bash
# Clean up old audit logs
php artisan pim:cleanup-audit-logs --older-than=365

# Clean up old user audit logs
php artisan pim:cleanup-user-audit-logs --older-than=730

# Remove expired access links
php artisan pim:cleanup-access-links

# Export audit logs for compliance
php artisan pim:export-audit-logs --from=2026-01-01 --to=2026-03-31 --output=/tmp/audit.csv
```

Schedule cleanup in `app/Console/Kernel.php`:

```php
$schedule->command('pim:cleanup-audit-logs')->daily()->at('02:00');
$schedule->command('pim:cleanup-user-audit-logs')->weekly();
$schedule->command('pim:cleanup-access-links')->daily()->at('03:00');
```

---

## Laravel Classes

| Type | Class | Path |
|------|-------|------|
| Model | `App\Models\AuditLog` | `app/Models/AuditLog.php` |
| Model | `App\Models\UserAuditLog` | `app/Models/UserAuditLog.php` |
| Model | `App\Models\AccessLink` | `app/Models/AccessLink.php` |
| Observer | `App\Observers\AuditObserver` | `app/Observers/AuditObserver.php` |
| Controller | `App\Http\Controllers\Api\AuditLogController` | `app/Http/Controllers/Api/` |
| Controller | `App\Http\Controllers\Api\UserAuditLogController` | `app/Http/Controllers/Api/` |
| Controller | `App\Http\Controllers\Api\JournalController` | `app/Http/Controllers/Api/` |
| Controller | `App\Http\Controllers\Api\AccessLinkController` | `app/Http/Controllers/Api/` |
| Service | `App\Services\AuditService` | `app/Services/` |
| Service | `App\Services\AccessLinkService` | `app/Services/` |
| Middleware | `App\Http\Middleware\AccessLinkAuth` | `app/Http/Middleware/` |
| Command | `App\Console\Commands\CleanupAuditLogs` | `app/Console/Commands/` |

### Automatic Audit Logging via Observer

```php
// app/Observers/AuditObserver.php
class AuditObserver
{
    public function created(Model $model): void
    {
        AuditLog::create([
            'user_id'         => auth()->id(),
            'auditable_type'  => get_class($model),
            'auditable_id'    => $model->id,
            'action'          => 'created',
            'new_values'      => $model->getAttributes(),
            'ip_address'      => request()->ip(),
            'user_agent'      => request()->userAgent(),
        ]);
    }

    public function updated(Model $model): void
    {
        AuditLog::create([
            'user_id'         => auth()->id(),
            'auditable_type'  => get_class($model),
            'auditable_id'    => $model->id,
            'action'          => 'updated',
            'old_values'      => $model->getOriginal(),
            'new_values'      => $model->getDirty(),
            'ip_address'      => request()->ip(),
            'user_agent'      => request()->userAgent(),
        ]);
    }

    public function deleted(Model $model): void
    {
        AuditLog::create([
            'user_id'         => auth()->id(),
            'auditable_type'  => get_class($model),
            'auditable_id'    => $model->id,
            'action'          => 'deleted',
            'old_values'      => $model->getAttributes(),
            'ip_address'      => request()->ip(),
            'user_agent'      => request()->userAgent(),
        ]);
    }
}
```

Register the observer on all auditable models in `AppServiceProvider`:

```php
Product::observe(AuditObserver::class);
Attribute::observe(AuditObserver::class);
HierarchyNode::observe(AuditObserver::class);
// ... etc.
```
