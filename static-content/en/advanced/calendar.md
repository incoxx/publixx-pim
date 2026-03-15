---
title: Planning Calendar
---

# Planning Calendar

The Planning Calendar in anyPIM provides a visual timeline for scheduling and managing planned activities related to your product data. It gives your team a clear overview of upcoming events such as export executions, product launches, review deadlines, and publication dates. By centralizing scheduling in a calendar view, you can coordinate activities across departments and avoid conflicts.

## Calendar View

Navigate to **Calendar** in the sidebar to access the planning calendar. The calendar supports three view modes:

| View | Description |
|---|---|
| **Month** | Displays the full month as a traditional grid with events placed on their scheduled dates |
| **Week** | Shows a seven-day view with time slots for more granular scheduling |
| **Day** | Displays a single day with detailed time-based event placement |

Switch between views using the toggle buttons in the calendar header. Use the navigation arrows to move forward or backward in time.

### Color Coding

Events are color-coded by type for quick visual identification:

| Color | Event Type |
|---|---|
| **Blue** | Export job executions |
| **Green** | Product launches or activations |
| **Orange** | Review deadlines and workflow tasks |
| **Purple** | Publication or catalog release events |
| **Gray** | Custom events without a specific category |

## Creating Events

Click on any date or time slot in the calendar to create a new event. The event creation panel opens with the following fields:

| Field | Description | Required |
|---|---|---|
| **Title** | A short description of the event | Yes |
| **Date/Time** | The scheduled date and optionally a start time | Yes |
| **End Date/Time** | For multi-day events or events with a defined duration | No |
| **Type** | The event category (Export, Launch, Review, Publication, Custom) | Yes |
| **Description** | Detailed notes about the event | No |
| **Linked Export Job** | Associate the event with a specific export job for automatic triggering | No |
| **Linked Products** | Products related to this event | No |
| **Assigned Users** | Team members involved in the event | No |

After saving, the event appears on the calendar at the scheduled position.

::: tip Note
When you link an export job to a calendar event, the export can be configured to run automatically at the scheduled time. This provides a visual way to plan and manage your export schedule.
:::

## Managing Events

### Editing Events

Click on an existing event in the calendar to open its detail panel. All fields can be modified. Changes are saved when you click **Save**.

### Drag and Drop

Events can be rescheduled by dragging them to a new date or time slot directly on the calendar:

- **In month view** -- Drag an event from one day to another to change its date.
- **In week or day view** -- Drag an event vertically to change its time, or horizontally to change its day.

The system updates the event's schedule immediately after dropping it.

### Resizing Events

In the week and day views, you can resize events by dragging their bottom edge to extend or shorten the duration.

::: warning Warning
Dragging an event that is linked to an export job will also update the export job's scheduled execution time. Verify the change is intentional before confirming.
:::

## Linking to Export Jobs

One of the most powerful features of the planning calendar is its integration with [Export Jobs](./export-jobs). You can link calendar events to export profiles to create a visual export schedule.

### How It Works

1. Create a calendar event with the type **Export**.
2. In the **Linked Export Job** field, select an existing export profile.
3. Set the date and time for the event.
4. When the scheduled time arrives, the export job executes automatically.

This approach complements the recurring schedule feature in export jobs by allowing one-time or ad-hoc exports to be planned visually on the calendar.

### Export Event Status

After an export event executes, the calendar event updates its status indicator:

| Status | Display |
|---|---|
| **Pending** | The event is scheduled but has not yet executed |
| **Running** | The export is currently in progress |
| **Completed** | The export finished successfully |
| **Failed** | The export encountered an error (click to view details) |

## Visual Timeline

The calendar also offers a **Timeline** view, accessible via the **Timeline** tab at the top of the calendar page. This view presents events as horizontal bars on a scrollable timeline, grouped by type or by assignee.

### Timeline Features

- **Zoom control** -- Adjust the time scale from days to weeks to months.
- **Grouping** -- Group events by type, by assigned user, or by linked product.
- **Overlaps** -- Overlapping events are stacked vertically, making conflicts immediately visible.

The timeline view is particularly useful for project managers who need to see the full scope of planned activities across a longer period.

## Filtering Events

The calendar provides filter options to focus on specific event types or time ranges:

- **Type filter** -- Show only events of a specific type (e.g., only Export events).
- **User filter** -- Show only events assigned to a specific user.
- **Product filter** -- Show only events linked to a specific product.

Active filters are displayed as chips above the calendar and can be removed individually.

## Permissions

Calendar access and management depend on user roles:

- **Administrators** can view, create, edit, and delete all events.
- **Regular users** can view all events and manage events they created or are assigned to.
- **Read-only users** can view the calendar but cannot create or modify events.

## Next Steps

- Set up [Export Jobs](./export-jobs) to link automated exports to calendar events.
- Use the [Workflow](../usage/workflow) module to manage tasks that appear on the calendar as review deadlines.
- Explore [Reports](./reports) to generate summaries of planned and completed activities.
