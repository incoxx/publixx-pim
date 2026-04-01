# anyPIM — Workflow & Task Management

> **Purpose:** Workflow task system for coordinating product data work. Use this skill when creating, assigning, or managing tasks, implementing status transitions, or linking tasks to products.

---

## Data Model

### workflow_tasks

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | CHAR(36) PK | No | UUID |
| title | VARCHAR(255) | No | Task title |
| description | TEXT | Yes | Detailed task description |
| status | ENUM('open','in_progress','review','done','cancelled') | No | Current status (default: open) |
| priority | ENUM('low','medium','high','urgent') | No | Priority level (default: medium) |
| assigned_to | FK → users.id | Yes | User responsible for the task |
| created_by | FK → users.id | No | User who created the task |
| product_id | FK → products.id | Yes | Linked product (optional) |
| due_date | DATE | Yes | Deadline |
| completed_at | DATETIME | Yes | When the task was completed |
| created_at | TIMESTAMP | No | |
| updated_at | TIMESTAMP | No | |
| INDEX(status) | | |
| INDEX(assigned_to) | | |
| INDEX(product_id) | | |
| INDEX(due_date) | | |

```sql
CREATE TABLE workflow_tasks (
  id CHAR(36) PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('open','in_progress','review','done','cancelled') NOT NULL DEFAULT 'open',
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  assigned_to CHAR(36) NULL,
  created_by CHAR(36) NOT NULL,
  product_id CHAR(36) NULL,
  due_date DATE NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_assigned (assigned_to),
  INDEX idx_product (product_id),
  INDEX idx_due_date (due_date)
);
```

---

## Status Workflow

```
open → in_progress → review → done
  ↓         ↓          ↓
  └─────────┴──────────┴──→ cancelled
```

### Allowed Transitions

| From | To | Condition |
|------|----|-----------|
| open | in_progress | Assignee picks up the task |
| open | cancelled | Creator or admin cancels |
| in_progress | review | Assignee submits for review |
| in_progress | cancelled | Creator or admin cancels |
| review | done | Reviewer approves |
| review | in_progress | Reviewer sends back |
| done | — | Terminal state |
| cancelled | open | Reopen (admin only) |

When a task transitions to `done`, `completed_at` is automatically set to `now()`.

---

## API Endpoints

### CRUD

```
GET    /api/v1/workflow-tasks                  List all tasks (paginated, filterable)
POST   /api/v1/workflow-tasks                  Create a new task
GET    /api/v1/workflow-tasks/{id}             Get task details
PUT    /api/v1/workflow-tasks/{id}             Update task
DELETE /api/v1/workflow-tasks/{id}             Delete task (admin only)
```

### Status Transitions

```
PUT    /api/v1/workflow-tasks/{id}/status      Change task status
```

### Bulk Operations

```
POST   /api/v1/workflow-tasks/bulk             Create tasks from product selection
PUT    /api/v1/workflow-tasks/bulk-status       Update status for multiple tasks
```

### Filters

```
GET /api/v1/workflow-tasks?filter[status]=open&filter[assigned_to]={user_id}
GET /api/v1/workflow-tasks?filter[priority]=urgent&filter[due_before]=2026-04-01
GET /api/v1/workflow-tasks?filter[product_id]={product_id}
GET /api/v1/workflow-tasks?sort=due_date&order=asc
```

---

## Request / Response Examples

### Create Task

```json
// POST /api/v1/workflow-tasks
{
  "title": "Complete product descriptions for Q2 catalog",
  "description": "Add missing DE and EN descriptions for all power tools",
  "priority": "high",
  "assigned_to": "uuid-user-123",
  "product_id": "uuid-product-456",
  "due_date": "2026-04-15"
}
```

### Bulk Create from Product Selection

```json
// POST /api/v1/workflow-tasks/bulk
{
  "title_template": "Review product data: {product.sku}",
  "description": "Check completeness and accuracy of all attributes",
  "priority": "medium",
  "assigned_to": "uuid-user-123",
  "product_ids": ["uuid-1", "uuid-2", "uuid-3"],
  "due_date": "2026-04-30"
}
```

Creates one task per product, with `{product.sku}` replaced by each product's SKU.

### Change Status

```json
// PUT /api/v1/workflow-tasks/{id}/status
{
  "status": "review",
  "comment": "All descriptions added, ready for review"
}
```

### Task Response

```json
{
  "data": {
    "id": "uuid-task-789",
    "title": "Complete product descriptions for Q2 catalog",
    "status": "in_progress",
    "priority": "high",
    "assigned_to": {
      "id": "uuid-user-123",
      "name": "Max Mustermann"
    },
    "created_by": {
      "id": "uuid-user-456",
      "name": "Admin User"
    },
    "product": {
      "id": "uuid-product-456",
      "sku": "BM-2024-001",
      "name": "Bohrmaschine ProMax 800"
    },
    "due_date": "2026-04-15",
    "completed_at": null,
    "created_at": "2026-03-10T09:00:00Z",
    "updated_at": "2026-03-12T14:30:00Z"
  }
}
```

---

## Notifications

Task events trigger notifications via Laravel's notification system:

| Event | Recipient | Channel |
|-------|-----------|---------|
| Task assigned | Assignee | Database, Email |
| Status changed | Assignee + Creator | Database |
| Due date approaching (1 day) | Assignee | Database, Email |
| Task overdue | Assignee + Creator | Database, Email |

---

## Laravel Classes

| Type | Class | Path |
|------|-------|------|
| Model | `App\Models\WorkflowTask` | `app/Models/WorkflowTask.php` |
| Controller | `App\Http\Controllers\Api\WorkflowTaskController` | `app/Http/Controllers/Api/` |
| FormRequest | `App\Http\Requests\StoreWorkflowTaskRequest` | `app/Http/Requests/` |
| FormRequest | `App\Http\Requests\UpdateWorkflowTaskRequest` | `app/Http/Requests/` |
| Resource | `App\Http\Resources\WorkflowTaskResource` | `app/Http/Resources/` |
| Policy | `App\Policies\WorkflowTaskPolicy` | `app/Policies/` |
| Notification | `App\Notifications\TaskAssigned` | `app/Notifications/` |
| Notification | `App\Notifications\TaskOverdue` | `app/Notifications/` |
| Command | `App\Console\Commands\CheckOverdueTasks` | `app/Console/Commands/` |

### Artisan Commands

```bash
# Check for overdue tasks and send notifications (run daily via scheduler)
php artisan pim:check-overdue-tasks

# Clean up completed/cancelled tasks older than N days
php artisan pim:cleanup-tasks --older-than=90
```

---

## Permissions

```
workflow-tasks.view          View tasks
workflow-tasks.create        Create tasks
workflow-tasks.edit          Edit tasks (own or assigned)
workflow-tasks.delete        Delete tasks
workflow-tasks.assign        Assign tasks to other users
workflow-tasks.bulk          Bulk create tasks
```
