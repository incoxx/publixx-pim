---
title: Usage Guide
---

# Usage Guide

Welcome to the anyPIM usage guide. This chapter is intended for all users who work with the system on a daily basis: from product managers and data stewards to export administrators.

## Overview

The anyPIM user interface is a modern single-page application built with Vue 3, Tailwind CSS, and DaisyUI. After logging in, you'll reach the dashboard from which all functional areas are accessible via the sidebar.

## Key Areas

### Products

Create, edit, and manage products including their attributes, variants, media, and prices.

### Attributes

Configure the attribute system — define attribute types, units, value lists, and attribute views to structure your product data.

### Hierarchies

Build and manage category trees, assign products to hierarchy nodes, and configure node-specific attribute assignments.

### Import

Import product data from Excel files with validation, mapping, and preview capabilities.

### Export

Export product data in JSON format or as catalog exports using PXF templates.

### Media

Upload images, documents, and videos, organize them in the media library, and assign media to products via drag-and-drop.

### Prices

Manage different price types in multiple currencies with validity periods and assign prices to products.

### Users & Permissions

Manage user accounts, roles, and permissions to control access to the system. The user management section is only visible to users with the **Admin** role. The system provides five roles: Admin, Data Steward, Product Manager, Viewer, and Export Manager.

::: info Note
Users with the **Viewer** role have read-only access and cannot see the user management menu. Save and delete buttons are automatically hidden for users without the corresponding permissions.
:::

### Help

The sidebar includes a **Help** link that opens this documentation in the user's configured language (German or English).

## License

anyPIM is released under the **AGPL-3.0-only** license. See the `LICENSE` file in the project root for the full license text. A list of all third-party components and their licenses can be found in the `THIRD-PARTY-NOTICES` file.

::: tip
The German documentation is more comprehensive. For detailed instructions, see the [German usage guide](/de/bedienung/).
:::
