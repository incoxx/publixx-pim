---
title: Export Jobs
---

# Export Jobs

Export Jobs in anyPIM automate the delivery of product data to external systems, partners, and sales channels. Unlike manual exports, export jobs run on a defined schedule, apply filters to select the right products, and deliver the output to a configured destination such as SFTP, webhook, or email. Every execution is logged, and failed jobs can be retried automatically or manually.

## Export Job Overview

Navigate to **Export Jobs** in the sidebar to see all configured jobs:

| Column | Description |
|---|---|
| **Name** | The descriptive name of the export job |
| **Profile** | The export profile used (defines format and field mapping) |
| **Schedule** | The configured frequency (e.g., Daily, Weekly, or Manual) |
| **Delivery** | The delivery method (SFTP, Webhook, Email, Download) |
| **Last Run** | Timestamp and status of the most recent execution |
| **Next Run** | Scheduled time of the next automatic execution |

## Creating an Export Job

Click **+ New Export Job** to open the creation wizard. The wizard guides you through four steps:

### Step 1: Basic Settings

| Field | Description | Required |
|---|---|---|
| **Name** | A descriptive name for the job | Yes |
| **Description** | Internal notes about the job's purpose | No |
| **Export Profile** | The profile that defines the output format and field mapping | Yes |
| **Active** | Whether the job is enabled for scheduled execution | Yes |

The export profile determines the structure and format of the exported data. Profiles are configured separately in the export module and can be reused across multiple jobs.

### Step 2: Filters

Filters define which products are included in the export. You can combine multiple filter conditions:

| Filter | Description |
|---|---|
| **Status** | Include only products with a specific status (e.g., Active) |
| **Product Type** | Restrict to one or more product types |
| **Manufacturer** | Include only products from specific manufacturers |
| **Hierarchy** | Export only products in specific categories |
| **Modified Since** | Include only products modified after a given date (useful for delta exports) |
| **Custom Attribute Filters** | Filter by any attribute value using operators (equals, contains, greater than, etc.) |

::: tip Note
For delta exports that only include recently changed products, use the **Modified Since** filter with a relative date such as "last 24 hours" or "since last successful run." This significantly reduces export volume and processing time.
:::

### Step 3: Schedule

Configure when and how often the export job runs:

| Setting | Description |
|---|---|
| **Frequency** | Manual (on-demand only), Daily, Weekly, or Monthly |
| **Time** | The time of day when the job executes (24-hour format) |
| **Day of Week** | For weekly jobs, the day of the week (Monday through Sunday) |
| **Day of Month** | For monthly jobs, the day of the month (1-28) |
| **Time Zone** | The time zone used for scheduling |

For **Manual** jobs, no schedule is configured. The job runs only when triggered explicitly by a user or via the API.

### Step 4: Delivery

Define how the generated export file is delivered:

| Method | Description |
|---|---|
| **SFTP** | Upload the file to an SFTP server. Requires host, port, username, and authentication (password or SSH key). |
| **Webhook** | Send the export data as an HTTP POST request to a specified URL. Supports custom headers for authentication. |
| **Email** | Send the export file as an email attachment to one or more recipients. |
| **Download** | Store the file in the anyPIM download area for manual retrieval. |

You can configure multiple delivery methods for the same job. For example, a job might upload the file to an SFTP server and simultaneously send a notification email with the file attached.

::: warning Warning
When using SFTP delivery, verify the connection settings by clicking **Test Connection** before saving the job. An incorrect configuration will cause every scheduled execution to fail.
:::

## Execution History

Every job execution is recorded in the execution history. Open the export job detail view and navigate to the **History** tab to see all past runs:

| Column | Description |
|---|---|
| **Execution Time** | When the job started running |
| **Duration** | How long the export took to complete |
| **Products** | Number of products included in the export |
| **File Size** | Size of the generated export file |
| **Status** | Success, Failed, or Partial (some products skipped) |
| **Details** | Link to view the full execution log |

### Execution Log

Click **Details** on any history entry to view the full execution log. The log includes:

- Start and end timestamps
- Filter conditions applied
- Number of products matched and exported
- Any warnings (e.g., products with missing required values)
- Error details if the execution failed
- Delivery status for each configured delivery method

## Error Handling and Retry

When an export job fails, anyPIM provides several mechanisms for recovery:

### Automatic Retry

You can configure automatic retries in the job settings:

| Setting | Description |
|---|---|
| **Max Retries** | The number of retry attempts before the job is marked as permanently failed (default: 3) |
| **Retry Interval** | The wait time between retry attempts in minutes (default: 15) |
| **Retry on Delivery Failure** | Whether to retry when the export generates successfully but delivery fails |

### Manual Retry

In the execution history, failed runs show a **Retry** button. Clicking it re-executes the job with the same configuration and filters.

### Failure Notifications

When an export job fails (after all retry attempts are exhausted), a notification is sent to:

- The user who created the job
- All users configured as notification recipients in the job settings

::: danger Warning
If a recurring export job fails repeatedly, it continues to attempt execution at each scheduled time. Monitor failure notifications and resolve the underlying issue promptly to avoid a backlog of failed executions.
:::

## Manual Execution

Any export job can be triggered manually, regardless of its schedule configuration:

1. Open the export job detail view.
2. Click **Run Now** in the toolbar.
3. The job executes immediately using the current filter conditions and delivery settings.
4. Monitor the progress in the execution history.

Manual execution does not affect the job's regular schedule. The next scheduled run will still execute at its configured time.

## Pausing and Deactivating Jobs

To temporarily stop a job from executing on schedule:

- **Pause** -- Click the **Pause** button in the job detail view. The job retains its schedule but skips executions until resumed.
- **Deactivate** -- Toggle the **Active** switch to off. The job is disabled entirely and does not appear in the scheduler.

Paused and deactivated jobs can be triggered manually at any time using the **Run Now** button.

## Permissions

Export job management depends on user roles:

- **Administrators** can create, edit, delete, and execute all export jobs.
- **Regular users** can view export jobs and their history, and can trigger manual execution for jobs they have access to.
- **Read-only users** can view the execution history and download completed export files.

## Next Steps

- Learn about configuring [export profiles](../export/index) for defining output formats and field mappings.
- Use the [Planning Calendar](./calendar) to schedule one-time exports visually.
- Explore [Price Regions](./price-regions) to include region-specific pricing in your exports.
- Review [Reports](./reports) for ad-hoc data extraction needs that do not require automated delivery.
