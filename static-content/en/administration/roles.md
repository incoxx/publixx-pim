---
title: Roles & Permissions
---

# Roles & Permissions

anyPIM includes a Role-Based Access Control (RBAC) system that allows you to precisely manage which users can access specific features and data. Roles bundle permissions into logical units and are assigned to users. Additionally, granular individual permissions can be configured to tailor access to each user's exact responsibilities.

## Overview

Access the role management via **Administration > Roles & Permissions** in the sidebar. The overview displays all roles defined in the system in a tabular format:

| Column | Description |
|---|---|
| **Name** | Name of the role |
| **Description** | Brief description of the role's scope |
| **Users** | Number of users assigned to this role |
| **Permissions** | Number of assigned individual permissions |
| **Created** | Date the role was created |

::: tip Note
Only users with the **Admin** role or the `roles.manage` permission can access the role management. This menu item is hidden for all other users.
:::

## Default Roles

anyPIM ships with three predefined default roles that cannot be deleted:

### Admin

Unrestricted full access to all system features. The Admin can manage users, define roles, modify system settings, and edit all data. At least one user must always hold the Admin role.

### Editor

Comprehensive editing rights for product data, attributes, media, and prices. The Editor can create new products, edit existing ones, and trigger exports. No access to user management or system configuration.

### Viewer

Read-only access to all product data, hierarchies, and media. The Viewer can browse data and use the search functionality but cannot make any changes. Save and delete buttons are automatically hidden.

### Default Role Comparison

| Permission | Admin | Editor | Viewer |
|---|---|---|---|
| Manage users | Yes | -- | -- |
| Manage roles | Yes | -- | -- |
| System settings | Yes | -- | -- |
| Create/edit products | Yes | Yes | -- |
| View products | Yes | Yes | Yes |
| Define attributes | Yes | Yes | -- |
| Manage hierarchies | Yes | Yes | -- |
| Manage media | Yes | Yes | -- |
| Edit prices | Yes | Yes | -- |
| Perform imports | Yes | Yes | -- |
| Trigger exports | Yes | Yes | -- |

## Creating Custom Roles

In addition to the default roles, you can create any number of custom roles to match the access model to your organizational structure.

1. Navigate to **Administration > Roles & Permissions**.
2. Click **+ New Role**.
3. Enter a name and an optional description.
4. Select the desired permissions from the permission list.
5. Save the role.

### Editing a Role

Click on a role in the list to modify its permissions. Changes to a role take effect immediately for all users assigned to that role.

### Deleting a Role

Custom roles can be deleted from the detail view. The role must have no users assigned to it before deletion.

::: warning Warning
Deleting a role cannot be undone. Make sure all affected users have been reassigned to a different role before proceeding.
:::

## Granular Permissions

Each permission follows the pattern `{entity}.{action}` and controls access to a specific operation within a functional area.

### Available Permissions

| Permission | Description |
|---|---|
| `products.view` | View products |
| `products.create` | Create new products |
| `products.edit` | Edit existing products |
| `products.delete` | Delete products |
| `attributes.view` | View attributes |
| `attributes.create` | Create new attributes |
| `attributes.edit` | Edit existing attributes |
| `attributes.delete` | Delete attributes |
| `hierarchies.view` | View hierarchies |
| `hierarchies.create` | Create new hierarchy nodes |
| `hierarchies.edit` | Edit hierarchy nodes |
| `hierarchies.delete` | Delete hierarchy nodes |
| `media.view` | View media |
| `media.create` | Upload new media |
| `media.edit` | Edit media |
| `media.delete` | Delete media |
| `prices.view` | View prices |
| `prices.edit` | Edit prices |
| `exports.create` | Trigger exports |
| `imports.create` | Perform imports |
| `users.manage` | Manage users |
| `roles.manage` | Manage roles |
| `settings.manage` | Edit system settings |

### Assigning Permissions to a Role

In the role editing dialog, all available permissions are displayed grouped by functional area. You can enable or disable individual permissions using checkboxes. The **Select All** button lets you activate all permissions within an area at once.

## Assigning Roles to Users

Role assignment is done through user management:

1. Navigate to **Administration > Users**.
2. Open the desired user.
3. Select the appropriate role from the **Role** dropdown.
4. Save the change.

Each user can hold exactly one role. The role determines the user's base permissions.

::: danger Warning
Do not remove the Admin role from yourself if you are the only administrator. The system prevents this action to avoid lockout.
:::

## Best Practices

- **Principle of Least Privilege** -- Only grant users the permissions they actually need for their daily tasks.
- **Plan Your Roles** -- Define your role structure before creating user accounts. Align roles with the responsibilities in your organization.
- **Use Custom Roles** -- Create dedicated roles for specific responsibilities instead of overloading default roles with additional permissions.
- **Regular Reviews** -- Periodically verify that assigned roles and permissions still match current requirements.
- **Documentation** -- Maintain an internal record of which role is intended for which group of users.

## Next Steps

- Learn how to [manage user accounts](../usage/users).
- Explore the [User Audit](./user-audit) to track user activities.
- Return to the [overview](../usage/index) to explore other functional areas.
