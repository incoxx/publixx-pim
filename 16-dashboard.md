# anyPIM — Dashboard & Widgets

> **Purpose:** Dashboard API with KPI widgets and quick actions. Use this skill when implementing the main dashboard view, widget data aggregation, or real-time update mechanisms.

---

## Overview

The dashboard provides a fixed-layout overview of the PIM system state. It is the landing page after login and displays aggregated KPIs, recent activity, and quick actions. There is no custom widget configuration — all users see the same dashboard layout (filtered by their permissions).

---

## API Endpoint

```
GET /api/v1/dashboard                    Returns all widget data in a single response
```

No request parameters required. The response is tailored to the authenticated user's permissions.

---

## Response Structure

```json
{
  "data": {
    "widgets": [
      {
        "type": "product_count",
        "title": "Products by Status",
        "data": { ... }
      },
      {
        "type": "recent_products",
        "title": "Recently Edited",
        "data": { ... }
      }
    ],
    "quick_actions": [ ... ],
    "generated_at": "2026-03-15T10:30:00Z"
  }
}
```

---

## Widget Types

### 1. product_count

Product count grouped by status.

```json
{
  "type": "product_count",
  "title": "Products by Status",
  "data": {
    "total": 12450,
    "by_status": {
      "draft": 1230,
      "active": 9850,
      "inactive": 1120,
      "discontinued": 250
    },
    "by_product_type": {
      "physical_product": 8500,
      "software": 2100,
      "service": 1200,
      "bundle": 650
    }
  }
}
```

### 2. recent_products

Last 10 products edited by any user.

```json
{
  "type": "recent_products",
  "title": "Recently Edited",
  "data": {
    "products": [
      {
        "id": "uuid-1",
        "sku": "BM-2024-001",
        "name": "Bohrmaschine ProMax 800",
        "status": "active",
        "updated_at": "2026-03-15T09:45:00Z",
        "updated_by": "Max Mustermann"
      }
    ]
  }
}
```

### 3. completeness_overview

Average attribute completeness across all active products.

```json
{
  "type": "completeness_overview",
  "title": "Data Completeness",
  "data": {
    "average_completeness": 78.5,
    "distribution": {
      "0-25": 120,
      "26-50": 450,
      "51-75": 2800,
      "76-99": 5200,
      "100": 1280
    }
  }
}
```

### 4. import_status

Recent import jobs and their status.

```json
{
  "type": "import_status",
  "title": "Recent Imports",
  "data": {
    "recent": [
      {
        "id": "uuid-import-1",
        "file_name": "products-q2-2026.xlsx",
        "status": "completed",
        "rows_processed": 1500,
        "errors": 3,
        "completed_at": "2026-03-14T16:00:00Z"
      }
    ],
    "summary": {
      "last_7_days": 5,
      "total_rows_imported": 8200,
      "total_errors": 12
    }
  }
}
```

### 5. export_status

Recent export jobs and their status.

```json
{
  "type": "export_status",
  "title": "Recent Exports",
  "data": {
    "recent": [
      {
        "id": "uuid-export-1",
        "name": "Full Catalog Export",
        "format": "json",
        "status": "completed",
        "product_count": 9850,
        "completed_at": "2026-03-15T06:00:00Z"
      }
    ],
    "scheduled_next": {
      "name": "Daily Publixx Export",
      "next_run": "2026-03-16T06:00:00Z"
    }
  }
}
```

### 6. workflow_tasks

Open tasks assigned to the current user.

```json
{
  "type": "workflow_tasks",
  "title": "My Open Tasks",
  "data": {
    "total_open": 8,
    "overdue": 2,
    "tasks": [
      {
        "id": "uuid-task-1",
        "title": "Review product descriptions",
        "priority": "high",
        "status": "in_progress",
        "due_date": "2026-03-20",
        "product_sku": "BM-2024-001"
      }
    ]
  }
}
```

### 7. translation_progress

Translation completeness for translatable attributes.

```json
{
  "type": "translation_progress",
  "title": "Translation Progress",
  "data": {
    "languages": {
      "de": { "total": 5000, "translated": 5000, "percentage": 100.0 },
      "en": { "total": 5000, "translated": 3800, "percentage": 76.0 },
      "fr": { "total": 5000, "translated": 1200, "percentage": 24.0 }
    }
  }
}
```

---

## KPI Calculations

| KPI | SQL / Logic |
|-----|-------------|
| Total products | `SELECT COUNT(*) FROM products` |
| Active % | `COUNT(status='active') / COUNT(*) * 100` |
| Completeness avg | `SELECT AVG(attribute_completeness) FROM products_search_index` |
| Translation % | Translatable attributes with non-null value per language / total translatable |
| Overdue tasks | `WHERE status NOT IN ('done','cancelled') AND due_date < CURDATE()` |

All KPI queries use the `products_search_index` denormalized table where possible for performance.

---

## Quick Actions

```json
{
  "quick_actions": [
    {
      "key": "create_product",
      "label": "Create Product",
      "icon": "plus-circle",
      "route": "/products/create",
      "permission": "products.create"
    },
    {
      "key": "start_import",
      "label": "Start Import",
      "icon": "upload",
      "route": "/imports/new",
      "permission": "import.execute"
    },
    {
      "key": "run_export",
      "label": "Run Export",
      "icon": "download",
      "route": "/exports",
      "permission": "export.execute"
    },
    {
      "key": "create_task",
      "label": "Create Task",
      "icon": "clipboard-list",
      "route": "/workflow/create",
      "permission": "workflow-tasks.create"
    }
  ]
}
```

Quick actions are filtered by the user's permissions — only actions the user is authorized for are returned.

---

## Real-Time Updates

Two strategies are supported:

### Polling (Default)

The frontend polls `GET /api/v1/dashboard` every **60 seconds**. The response includes `generated_at` to detect staleness.

### WebSocket (Optional)

When Laravel Echo + Pusher/Soketi is configured:

```
Channel: private-dashboard
Event: DashboardUpdated
```

The backend broadcasts `DashboardUpdated` when significant changes occur (product created/updated, import completed, task status change). The frontend receives the event and refreshes the affected widget only.

---

## Laravel Classes

| Type | Class | Path |
|------|-------|------|
| Controller | `App\Http\Controllers\Api\DashboardController` | `app/Http/Controllers/Api/` |
| Service | `App\Services\DashboardService` | `app/Services/` |
| Service | `App\Services\KpiCalculationService` | `app/Services/` |
| Event | `App\Events\DashboardUpdated` | `app/Events/` |
| Resource | `App\Http\Resources\DashboardResource` | `app/Http/Resources/` |

### Caching

Dashboard data is cached for **60 seconds** using Laravel Cache:

```php
Cache::remember('dashboard:' . auth()->id(), 60, function () {
    return app(DashboardService::class)->buildDashboard(auth()->user());
});
```

Cache is invalidated on product/import/export/task mutations via model observers.
