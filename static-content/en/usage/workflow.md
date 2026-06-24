---
title: Workflow
---

# Workflow

The Workflow module in anyPIM provides task management capabilities designed for coordinating product data maintenance across teams. You can create tasks, assign them to specific users, set due dates, and track progress through defined statuses. This ensures that product updates, reviews, and corrections are handled systematically.

## Task Overview

Navigate to **Workflow** in the sidebar to access the task overview. The overview displays all tasks visible to you in a tabular list:

| Column | Description |
|---|---|
| **Title** | Short description of the task |
| **Assignee** | The user responsible for completing the task |
| **Status** | Current status: Open, In Progress, or Closed |
| **Due Date** | The deadline for task completion |
| **Products** | Number of products linked to the task |
| **Created** | Date and time when the task was created |

### Filtering Tasks

The filter bar above the task list allows you to narrow down the view:

- **Status filter** -- Show only tasks with a specific status (Open, In Progress, Closed).
- **Assignee filter** -- Display tasks assigned to a specific user or to yourself.
- **Due date range** -- Filter tasks by their due date within a specific period.
- **Full-text search** -- Search task titles and descriptions for keywords.

### Sorting

Click on any sortable column header to sort the task list. The default sort order is by due date ascending, ensuring that the most urgent tasks appear first.

## Creating a Task

Click the **+ New Task** button above the task list. A creation panel opens with the following fields:

| Field | Description | Required |
|---|---|---|
| **Title** | A concise description of what needs to be done | Yes |
| **Description** | Detailed instructions or context for the task | No |
| **Assignee** | The user who should complete the task | Yes |
| **Due Date** | The deadline for completing the task | No |
| **Priority** | Priority level (Low, Normal, High) | Yes |
| **Products** | One or more products linked to the task | No |

After saving, the task is created with the status **Open** and the assignee receives a notification.

::: tip Note
You can link a task to multiple products. This is useful when the same action (such as updating images or verifying prices) needs to be performed across several products.
:::

## Task Statuses

Every task follows a linear status progression:

| Status | Description |
|---|---|
| **Open** | The task has been created but work has not yet begun. This is the initial status for all new tasks. |
| **In Progress** | The assignee has started working on the task. Transition from Open to In Progress can be done manually or happens automatically when the assignee opens the task. |
| **Closed** | The task has been completed. The assignee or the creator can mark a task as Closed. |

To change a task's status, open the task detail view and select the new status from the status dropdown, or use the quick-action buttons in the task list.

### Reopening Tasks

A completed task can be reopened by changing its status back to **Open**. This is useful when a review reveals that additional work is needed.

## Task Detail View

Clicking on a task in the overview opens the task detail view, which shows all task information along with additional features:

### Task Properties

The detail view displays the full task description, assignee, due date, priority, and the list of linked products. Each linked product is shown as a clickable row that opens the product detail view directly.

### Comments

The task detail view includes a **comments section** where team members can discuss the task. Comments support the following capabilities:

- **Add a comment** -- Type your message in the text field and click **Post**.
- **Mention users** -- Use the `@` symbol followed by a username to notify a specific user.
- **Timestamps** -- Each comment displays the author and the time it was posted.

Comments provide a transparent audit trail of communication related to the task. They are visible to all users who have access to the task.

::: tip Note
Comments are permanent and cannot be deleted. This ensures that the full history of task-related discussions is preserved.
:::

## Bulk Task Creation

When you need to create tasks for a large number of products at once, the bulk task creation feature saves time. There are two ways to create tasks in bulk:

### From the Product List

1. Navigate to **Products** and select multiple products using the checkboxes.
2. Click **Create Task** in the bulk actions toolbar.
3. A task creation panel opens with the selected products pre-linked.
4. Fill in the task title, description, assignee, and due date.
5. Click **Save** to create a single task linked to all selected products.

### From the Watchlist

1. Navigate to your **Watchlist** and select the relevant products.
2. Click **Create Task** in the toolbar.
3. Complete the task details and save.

### One Task per Product

If you prefer to create a separate task for each product rather than one shared task, enable the **One task per product** toggle in the creation panel. This generates individual tasks, each linked to a single product, with the same title, description, assignee, and due date.

::: warning Warning
Bulk task creation with the "One task per product" option can generate a large number of tasks. Review the product selection carefully before confirming.
:::

## Notifications

anyPIM sends notifications for the following workflow events:

| Event | Recipient |
|---|---|
| **Task created** | The assigned user |
| **Task status changed** | The task creator and the assignee |
| **Comment added** | All users involved in the task |
| **Due date approaching** | The assigned user (one day before the due date) |

Notifications appear in the notification bell in the top navigation bar and can optionally be sent via email, depending on user preferences.

## Permissions

Task visibility and management depend on user roles:

- **Administrators** can view and manage all tasks.
- **Regular users** can view tasks assigned to them and tasks they created. They can also create new tasks and assign them to any user.
- **Read-only users** can view tasks assigned to them but cannot create or modify tasks.

## Next Steps

- Use the [Dashboard](./dashboard) to see a summary of your open tasks.
- Learn about [Products](./products) to understand the product data you will be working with.
- Explore [Users](./users) to configure roles and permissions for your team.
