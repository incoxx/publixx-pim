---
title: User Audit
---

# User Audit

The User Audit logs all user activities within anyPIM and provides a complete trail of logins, logouts, and security-relevant actions. The audit feature supports compliance with internal policies and allows you to detect unusual activity early.

## Overview

Access the User Audit via **Administration > User Audit** in the sidebar. The main view displays a chronological list of all recorded user actions.

| Column | Description |
|---|---|
| **Timestamp** | Date and time of the action (server timezone) |
| **User** | Name and email address of the acting user |
| **Action** | Type of action performed |
| **IP Address** | IP address from which the action originated |
| **User Agent** | Browser and operating system information |
| **Details** | Additional information about the action |

::: tip Note
The User Audit is accessible only to users with the **Admin** role. Audit records are read-only and cannot be edited or deleted.
:::

## Recorded Actions

The system automatically records the following user actions:

### Authentication

| Action | Description |
|---|---|
| `login.success` | Successful login |
| `login.failed` | Failed login attempt |
| `logout` | User-initiated logout |
| `session.expired` | Automatic logout after session expiry |
| `password.changed` | Password changed by the user |
| `password.reset` | Password reset by an administrator |

### User Management

| Action | Description |
|---|---|
| `user.created` | New user account was created |
| `user.updated` | User account was modified |
| `user.deleted` | User account was deleted |
| `role.changed` | User's role was changed |

### Data Access

| Action | Description |
|---|---|
| `export.triggered` | Export was triggered |
| `import.triggered` | Import was started |
| `api.access` | API access via token |

## Login History

The login history provides a detailed overview of all login and logout events. The following information is captured for each entry:

- **Timestamp** -- Date and time of the login
- **IP Address** -- Source IP address of the connection
- **Location** -- Approximate location based on IP address (if available)
- **User Agent** -- Browser and operating system information
- **Duration** -- Session duration until logout
- **Status** -- Successful login or failed attempt

::: warning Warning
Multiple failed login attempts from the same IP address may indicate a brute-force attack. Review such entries promptly and consider IP blocking at the server level if necessary.
:::

## Filter Options

The audit list can be filtered by several criteria to quickly locate relevant entries:

| Filter | Description | Example |
|---|---|---|
| **User** | Filter by a specific user | John Smith |
| **Action** | Filter by action type | `login.success`, `user.created` |
| **Date Range** | Filter by start/end date | 2026-01-01 -- 2026-01-31 |
| **IP Address** | Filter by IP address | 192.168.1.100 |
| **Status** | Filter by success/failure | Failed actions only |

Filters can be combined freely. The result list updates automatically whenever a filter criterion is changed.

## Exporting the Audit Log

The audit log can be exported for external archiving or further processing:

1. Set the desired filters to narrow the export scope.
2. Click **Export** above the result list.
3. Select the desired format:

| Format | Description |
|---|---|
| **CSV** | Comma-separated values, suitable for spreadsheets |
| **JSON** | Structured data format for machine processing |
| **PDF** | Print-ready report with summary |

4. The download starts automatically.

::: tip Note
For large data volumes, the export is processed as a background task. You will receive a notification once the file is ready for download.
:::

## Retention Period

Audit entries are retained for **365 days** by default. After this period, entries are automatically archived. The retention period can be adjusted in the system settings.

| Setting | Default | Description |
|---|---|---|
| `audit.retention_days` | 365 | Retention period in days |
| `audit.archive_enabled` | true | Automatic archiving enabled |
| `audit.log_api_access` | true | Log API access events |

## Best Practices

- **Regular Reviews** -- Check the audit log at least weekly for unusual activity.
- **Monitor Failed Logins** -- Watch for clusters of failed login attempts, especially from unfamiliar IP addresses.
- **Export and Archive** -- Export the audit log regularly for long-term retention, particularly if regulatory requirements apply.
- **Privacy Compliance** -- Observe applicable data protection regulations (GDPR) when analyzing audit logs. Inform your users about the logging.

## Next Steps

- Learn more about [Roles & Permissions](./roles) to control feature access.
- Use the [Journal](./journal) to track changes to product data.
- Return to the [overview](../usage/index) to explore other functional areas.
