---
title: Dashboard
---

# Dashboard

The Dashboard is the first screen you see after logging into anyPIM. It provides a centralized overview of key performance indicators, recent activity, and quick access to frequently used actions. Each user can configure the dashboard to display the widgets most relevant to their daily work.

## KPI Widgets

The top section of the dashboard displays a row of KPI widgets that summarize the current state of your product data at a glance:

| Widget | Description |
|---|---|
| **Total Products** | The total number of products in the system, broken down by status (Draft, Active, Inactive) |
| **Data Completeness** | A percentage score indicating how many required attributes are filled across all active products |
| **Recent Changes** | The number of products modified within the last 24 hours, 7 days, and 30 days |
| **Active Exports** | The number of export jobs that ran successfully in the last cycle |

Each widget is rendered as a card with a prominent number and a trend indicator showing the change compared to the previous period. Clicking on a widget navigates you to the corresponding detail view -- for example, clicking **Total Products** opens the product list filtered by the relevant status.

### Completeness Score

The completeness score is calculated based on the required attributes defined in your product types. It considers all products with **Active** status and computes the ratio of filled required attributes to total required attributes. This metric helps you identify gaps in your product data before exporting to downstream channels.

::: tip Note
The completeness score only considers attributes marked as **Required** in the product type configuration. Optional attributes do not affect the score.
:::

## Recently Edited Products

Below the KPI widgets, the dashboard displays a list of **recently edited products**. This list shows the last 10 products you personally modified, along with key details:

| Column | Description |
|---|---|
| **SKU** | The unique article number of the product |
| **Name** | The product name in the current interface language |
| **Status** | The current product status (Draft, Active, Inactive) |
| **Last Modified** | Timestamp of the most recent change |
| **Modified By** | The user who made the last change |

Clicking on any row opens the product detail view directly. This widget is particularly useful for resuming work on products you were editing earlier.

## Quick Actions

The dashboard includes a **Quick Actions** panel that provides one-click access to common operations:

- **Create Product** -- Opens the product creation panel immediately without navigating to the product list first.
- **Start Import** -- Launches the import wizard for uploading product data from Excel or CSV files.
- **Run Export** -- Triggers a manual execution of a configured export profile.
- **Open Reports** -- Navigates to the report designer for generating product data reports.

Quick actions save time by reducing the number of clicks needed to reach frequently used features.

## Configuring Widgets

Each user can customize the dashboard layout according to their preferences. To configure the dashboard:

1. Click the **Customize** button in the top-right corner of the dashboard.
2. A configuration panel opens, showing all available widgets.
3. Toggle the visibility of each widget using the checkbox next to its name.
4. Drag and drop widgets to rearrange their position on the dashboard.
5. Click **Save** to apply your configuration.

### Available Widgets

| Widget | Default | Description |
|---|---|---|
| **KPI Summary** | Visible | Product count, completeness, and recent changes |
| **Recently Edited** | Visible | Products you recently modified |
| **Quick Actions** | Visible | Shortcuts to common operations |
| **Task Overview** | Hidden | Summary of your open workflow tasks |
| **Watchlist Preview** | Hidden | The first five products from your personal watchlist |
| **Export Status** | Hidden | Status of recent export job executions |

::: warning Warning
Dashboard configurations are stored per user. Changes you make to the dashboard layout do not affect other users.
:::

## Data Refresh

The dashboard data is refreshed automatically each time you navigate to the dashboard. You can also trigger a manual refresh by clicking the **Refresh** icon next to the page title. KPI values are cached for performance and may show data that is up to five minutes old.

## Permissions

The dashboard respects the permissions assigned to your user role. If you do not have access to a particular module (for example, export jobs), the corresponding widget will not appear in your available widget list, even if another user has it enabled.

## Next Steps

- Set up your personal [Watchlist](./watchlist) to track important products.
- Learn about [Workflow](./workflow) tasks to manage your team's product maintenance work.
- Explore [Products](./products) to start creating and editing product data.
