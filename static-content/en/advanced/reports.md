---
title: Reports
---

# Reports

The Reports module in anyPIM provides a flexible report designer that allows you to generate structured outputs from your product data. You can select specific fields, define filter conditions, and produce reports in multiple formats including PDF, DOCX, and CSV. Reports can be saved as templates for recurring use and scheduled for automatic execution.

## Report Designer

Navigate to **Reports** in the sidebar to access the report designer. The designer is divided into three main areas:

### Field Selection

The left panel displays all available data fields organized by category. You build your report by dragging fields from this panel into the report layout area:

| Category | Example Fields |
|---|---|
| **Product** | SKU, Name, Status, Product Type, Created, Modified |
| **Attributes** | All custom attributes defined in your system |
| **Prices** | Price values by price type, currency, and region |
| **Media** | Image URLs, file names, media types |
| **Relations** | Related product SKUs and relation types |
| **Manufacturer** | Manufacturer name, website, logo URL |
| **Hierarchy** | Category path, hierarchy level |

To add a field to your report, drag it from the field list and drop it into the column layout area. You can reorder columns by dragging them left or right within the layout.

### Filter Conditions

The filter panel allows you to restrict which products are included in the report. Filters use a condition-based approach:

| Component | Description |
|---|---|
| **Field** | The data field to filter on (e.g., Status, Product Type, Manufacturer) |
| **Operator** | The comparison operator (equals, not equals, contains, greater than, less than, is empty, is not empty) |
| **Value** | The value to compare against |

You can add multiple filter conditions. By default, all conditions are combined with **AND** logic, meaning all conditions must be met. Use the toggle to switch between AND and OR logic.

::: tip Note
Filters are applied at report generation time using the current product data. If products change between report runs, the results will reflect the latest data.
:::

### Preview

The right side of the designer shows a live preview of the report output using a sample of matching products. The preview updates automatically as you add or remove fields and modify filters. This allows you to verify the report structure before generating the final output.

## Generating a Report

Once you have configured the fields and filters, click the **Generate** button. A dialog appears where you select the output format:

| Format | Description |
|---|---|
| **PDF** | A formatted document with headers, table layout, and optional branding. Suitable for printing or sharing with external stakeholders. |
| **DOCX** | A Microsoft Word document that can be further edited. Useful when the report needs manual adjustments before distribution. |
| **CSV** | A plain-text file with comma-separated values. Ideal for importing into spreadsheet applications or other systems. |

After selecting the format, the report is generated and downloaded to your browser. For large reports with many products, generation may take a few moments.

::: warning Warning
Reports containing a very large number of products (more than 10,000 rows) may take significant time to generate in PDF or DOCX format. For large datasets, consider using the CSV format or applying filters to reduce the result set.
:::

## Saving Report Templates

If you need to run the same report regularly, you can save it as a template:

1. Configure the report fields and filters as desired.
2. Click **Save Template** in the designer toolbar.
3. Enter a descriptive name for the template (e.g., "Monthly Active Products Report").
4. Optionally add a description explaining the template's purpose.
5. Click **Save**.

Saved templates appear in the **Templates** list on the left side of the report designer. Click on a template to load its configuration instantly.

### Managing Templates

| Action | Description |
|---|---|
| **Load** | Click a template name to load its field selection and filters into the designer |
| **Edit** | Modify the loaded template and click **Save Template** to update it |
| **Duplicate** | Create a copy of a template with a new name |
| **Delete** | Remove a template that is no longer needed |

## Scheduling Reports

Report templates can be scheduled for automatic execution at defined intervals. Scheduled reports are generated and delivered without manual intervention.

### Creating a Schedule

1. Open a saved report template.
2. Click **Schedule** in the toolbar.
3. Configure the schedule settings:

| Setting | Description |
|---|---|
| **Frequency** | How often the report runs (Daily, Weekly, Monthly) |
| **Day/Time** | The specific day and time for execution |
| **Format** | The output format for the scheduled report (PDF, DOCX, or CSV) |
| **Delivery** | How the report is delivered (Email, download area) |
| **Recipients** | Email addresses that receive the generated report |

4. Click **Activate** to enable the schedule.

::: tip Note
Scheduled reports run using the system's time zone. Verify the configured time zone in the system settings to ensure reports are generated at the expected time.
:::

### Viewing Scheduled Reports

The **Scheduled** tab in the report designer lists all active schedules with their next execution time, frequency, and last run status. You can pause, edit, or delete schedules from this view.

## Report History

Every generated report -- whether manual or scheduled -- is logged in the **History** tab. The history shows:

| Column | Description |
|---|---|
| **Report Name** | The template name or "Ad-hoc" for unsaved reports |
| **Generated** | Date and time of generation |
| **Format** | The output format used |
| **Products** | Number of products included in the report |
| **Status** | Success or failure |
| **Download** | Link to download the generated file (available for 30 days) |

## Permissions

Report access is controlled by user roles:

- **Administrators** can access all reports, templates, and schedules.
- **Regular users** can create and run reports but can only manage their own templates and schedules.
- **Read-only users** can view and download reports from the history but cannot create new reports.

## Next Steps

- Explore [PDF Templates](./pdf-templates) for visually rich product data sheets.
- Use [Export Jobs](./export-jobs) for automated data delivery to external systems.
- Learn about [Products](../usage/products) to understand the data fields available for reports.
