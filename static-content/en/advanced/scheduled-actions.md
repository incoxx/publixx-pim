---
title: Scheduled Actions
---

# Scheduled Actions

**Scheduled actions** run product operations at a defined point in time — for example an activation at sales launch or a price change on a key date. They appear together with export jobs, scheduled product versions and project deadlines in the [Planning Calendar](/en/advanced/calendar).

## Action types

| Type | Effect |
|---|---|
| **Activation** | Activate the product at the scheduled time |
| **Deactivation** | Deactivate the product |
| **Price change** | Set price(s) (price type, amount, currency) |
| **Data change** | Change attribute values |
| **Export** | Trigger an export job |

## Creating an action

1. Open the **"Scheduled Actions"** tab in the product detail view.
2. Choose **"New Action"** and fill in:
   - **Title** of the action
   - **Action type** (see table)
   - **Scheduled at** (a point in the future)
   - type-specific **payload** (e.g. price type + amount for a price change)
   - optional **colour** (for calendar display) and **assignee**
3. Save — the action appears with a status (*Pending, In progress, Completed, Failed*).

## In the calendar

In the [Planning Calendar](/en/advanced/calendar) all scheduled actions are visible in the month, week or day view and can be filtered by type and status. Pending actions can be edited directly; export jobs, version publications and project deadlines are also shown for orientation (read-only).

::: warning Execution
Time-based execution requires a running scheduler/worker (see [Cron Jobs & Scheduling](/en/installation/cron-jobs)).
:::

## Permissions

Viewing requires the right to view products; creating/editing requires the right to edit products. Configuration is under [Roles & Permissions](/en/administration/roles).
