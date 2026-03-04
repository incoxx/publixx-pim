---
title: Users
---

# Users

The user management of Publixx PIM is based on a role-based access control system (RBAC). This chapter describes the creation and management of user accounts, the available roles, and the fine-grained permission system.

## User List

You can access the user management via the **Users** menu item in the sidebar. The overview shows all registered users in tabular form:

| Column | Description |
|---|---|
| **Name** | First and last name of the user |
| **Email** | Email address (also serves as login) |
| **Role** | Assigned system role |
| **Language** | Preferred interface language |
| **Created On** | Timestamp of account creation |

::: info Access Right
Only users with the **Admin** role have access to user management. The **Users** menu item is hidden for all other roles. Admins can create, edit, or delete accounts.
:::

## Creating a User

1. Click **+ New User** above the user list.
2. The form panel (UserFormPanel) opens with the following fields:

| Field | Description | Required |
|---|---|---|
| **First Name** | First name of the user | Yes |
| **Last Name** | Last name of the user | Yes |
| **Email** | Email address (login identifier, must be unique) | Yes |
| **Password** | Initial password | Yes |
| **Role** | Assignment of a system role | Yes |
| **Language** | Preferred interface language (DE/EN) | Yes |

3. Save the account. The user can log in immediately with the specified credentials.

### Editing a User

Click on a user in the user list to open their details. You can edit all fields, including the role. The password can be reset by the administrator.

### Deleting a User

Click **Delete** in the detail view to remove a user account. Deletion occurs after a confirmation prompt.

::: warning Note
Deleted user accounts cannot be restored. In the version history of products, the username remains visible even if the account has been deleted.
:::

## Roles

Publixx PIM defines five system roles with different areas of responsibility:

### Admin

| Property | Description |
|---|---|
| **Full Access** | Unrestricted access to all functional areas |
| **User Management** | Can create, edit, and delete users |
| **System Configuration** | Can configure system settings, price types, hierarchies, and attributes |
| **Data Management** | Full access to all products, media, and prices |

The Admin is the only role that has access to user management and system configuration.

### Data Steward

| Property | Description |
|---|---|
| **Data Modeling** | Can manage attributes, attribute groups, product types, and value lists |
| **Hierarchy Management** | Can create and edit hierarchies |
| **Quality Assurance** | Reviews and validates product data |
| **No User Access** | No permission for user management |

The Data Steward is responsible for data structure and quality, without directly maintaining product content.

### Product Manager

| Property | Description |
|---|---|
| **Product Maintenance** | Can create and edit products, create variants, and assign media |
| **Price Maintenance** | Can enter and edit prices |
| **Limited Configuration** | No access to attribute definitions or hierarchy structure |
| **Export Access** | Can trigger exports |

The Product Manager is the typical role for daily product data maintenance.

### Viewer

| Property | Description |
|---|---|
| **Read-Only Access** | Can view products, attributes, and hierarchies but not edit them |
| **Search and Navigation** | Can use the product search and hierarchy navigation |
| **No Write Access** | No permission to create, edit, or delete data |
| **No Access to User Management** | The Users and Roles menu items are not visible |

The Viewer role is suitable for stakeholders who need to view product data but should not edit it. Buttons such as **Save** and **Delete** are automatically hidden for Viewers.

### Export Manager

| Property | Description |
|---|---|
| **Export Management** | Can configure export templates and trigger exports |
| **Read Access to Products** | Can view product data but not edit it |
| **Import Management** | Can configure and perform data imports |

The Export Manager is responsible for data output and input via interfaces.

### Role Comparison

| Permission | Admin | Data Steward | Product Manager | Viewer | Export Manager |
|---|---|---|---|---|---|
| Manage users | Yes | -- | -- | -- | -- |
| User management visible | Yes | -- | -- | -- | -- |
| System settings | Yes | -- | -- | -- | -- |
| Define attributes | Yes | Yes | -- | -- | -- |
| Manage hierarchies | Yes | Yes | -- | -- | -- |
| Maintain value lists | Yes | Yes | -- | -- | -- |
| Create/edit products | Yes | -- | Yes | -- | -- |
| View products | Yes | Yes | Yes | Yes | Yes |
| Manage media | Yes | -- | Yes | -- | -- |
| Maintain prices | Yes | -- | Yes | -- | -- |
| Perform imports | Yes | -- | -- | -- | Yes |
| Configure exports | Yes | -- | -- | -- | Yes |

## Permission System

In addition to roles, Publixx PIM offers a **fine-grained permission system** that enables individual permissions per user.

### Permission Schema

Each permission follows the schema:

```
{entity}.{action}[:{restriction}]
```

| Component | Description | Examples |
|---|---|---|
| **Entity** | The functional area | `products`, `attributes`, `hierarchies`, `media`, `prices`, `users` |
| **Action** | The permitted operation | `view`, `create`, `update`, `delete`, `export`, `import` |
| **Restriction** | Optional scope limitation | `attribute_view:ecommerce`, `hierarchy:electronics` |

### Permission Examples

| Permission | Meaning |
|---|---|
| `products.view` | View all products |
| `products.update` | Edit all products |
| `products.create` | Create new products |
| `products.delete` | Delete products |
| `products.update:attribute_view:ecommerce` | Edit products, but only attributes of the "E-Commerce" view |
| `products.view:hierarchy:electronics` | View only products in the "Electronics" hierarchy node |
| `attributes.create` | Define new attributes |
| `attributes.update` | Edit existing attributes |
| `media.create` | Upload new media |
| `media.delete` | Delete media |
| `prices.update` | Edit prices |
| `exports.create` | Trigger exports |

### Restrictions on Attribute Views

Permissions can be restricted to specific **attribute views**. A user with the permission `products.update:attribute_view:ecommerce` can only edit the attributes defined in the "E-Commerce" attribute view. All other attributes are read-only for them.

Typical use cases:
- A marketing employee may only edit marketing texts (view "Marketing").
- A technician may only maintain technical data (view "Technical Data").
- The e-commerce manager may only edit online shop-relevant fields (view "E-Shop").

### Restrictions on Hierarchy Nodes

Permissions can be restricted to specific **hierarchy nodes**. A user with the permission `products.update:hierarchy:electronics` can only edit products assigned to the "Electronics" hierarchy node (and its subnodes).

Typical use cases:
- A product manager for the "Household" division only sees products from their division.
- A regional manager only maintains products in their product group.

### Combined Restrictions

Attribute view and hierarchy restrictions can be combined. For example, a user can be restricted to the "E-Shop" attribute view while simultaneously only seeing the "Clothing" hierarchy node. This creates a matrix permission that controls both the data scope (which attributes) and the product scope (which products).

## User Settings

Each user can configure the following options in their personal settings:

| Setting | Description |
|---|---|
| **Language** | Preferred interface language (German/English) |
| **Change Password** | Update own password |

The settings can be accessed via the **Settings** menu item in the sidebar or via your user profile.

## Best Practices

- **Minimal permissions** -- Grant users only the permissions they need for their tasks (Principle of Least Privilege).
- **Role planning** -- Define which roles and permissions are needed in your organization before creating users.
- **Use attribute views** -- Create attribute views for different departments and restrict editing rights accordingly. This prevents accidental changes to data outside one's area of expertise.
- **Hierarchy restrictions** -- Use hierarchy restrictions to make only the relevant area of responsibility visible to product managers.
- **Regular review** -- Regularly review user accounts and permissions. Deactivate accounts of employees who have left the organization.

## Next Steps

- Learn how [Attribute Views](./attributes#attribute-views) are configured for permission control.
- Get to know the [Hierarchy management](./hierarchies) to define restrictions on categories.
- Return to the [Overview](./index) to explore other functional areas.
