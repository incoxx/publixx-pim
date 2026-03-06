---
title: Frequently Asked Questions (FAQ)
---

# Frequently Asked Questions (FAQ)

Here you will find answers to the most frequently asked questions about anyPIM. The questions are grouped by topic and cover areas from everyday usage to system administration.

## User Management

### How do I reset a forgotten password?

There are two ways to reset a forgotten password:

**As an administrator:**
Navigate in the user interface to **Users**, select the affected user, and click **Reset Password**. The new password will be sent to the user by email.

**Via the command line:**
If no administrator access is available, you can reset the password directly via Artisan:

```bash
php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'user@example.com')->first();
$user->password = bcrypt('new_password');
$user->save();
```

::: warning Note
Ask the user to change the password immediately after the first login.
:::

### How can I restrict users to specific product categories?

anyPIM supports fine-grained access control through the roles and permissions system. Here is how to set up a restriction:

1. Under **Users > Roles**, create a new role (e.g., "Product Manager Electrical").
2. Assign the desired permissions to the role (e.g., read and edit products -- but not delete).
3. Restrict access via hierarchy nodes by assigning only specific nodes of the master hierarchy to the role.
4. Assign the role to the corresponding user.

Users with restricted roles will only see products that are assigned to the allocated hierarchy nodes.

::: info Note
The menu item **Users > Roles** is only visible to users with the **Admin** role.
:::

### Why don't I see the "Users" menu item?

The **Users** menu item is only visible to the **Admin** role. Users with other roles (Data Steward, Product Manager, Viewer, Export Manager) do not have access to user management. Contact your administrator if you need access.

### Why don't I see Save or Delete buttons?

The **Save** and **Delete** buttons are only displayed if your role includes the corresponding permissions (e.g., `products.update`, `products.delete`). The **Viewer** role has read-only access -- edit and delete buttons are therefore automatically hidden.

## Product Data

### How many products can the system manage?

anyPIM is designed for product inventories of **100,000+ products**, each with numerous attribute values, variants, and media. On the recommended hardware configuration (8 vCPU, 16 GB RAM, NVMe SSD), internal tests have shown that inventories with over 200,000 products and several million attribute values can be managed with good performance.

The actual performance depends on the following factors:

- Number of attributes per product
- Number of variants per product
- Complexity of PQL queries
- Number of concurrent users
- Server hardware specifications

::: tip Recommendation
For inventories exceeding 100,000 products, we recommend the extended hardware configuration described in the [Prerequisites](/en/installation/requirements).
:::

### How do variants inherit values from the parent product?

Inheritance follows the principle of **override at the variant level**:

1. **Default behavior**: A variant automatically inherits all attribute values from the parent product, provided the attribute is configured as **inheritable** (`inheritable`).
2. **Custom values**: Once an attribute value is set directly on the variant, it overrides the inherited value.
3. **Reset**: If an overridden value is deleted on the variant, the parent product's value takes effect again.
4. **Propagation**: Changes to the parent product are automatically propagated to all variants whose attribute values have not been overridden.

This behavior can be controlled per attribute via the `inheritable` property. Attributes that are not marked as inheritable must be maintained individually on each variant.

## Import

### What can I do if errors occur during import?

The import process in anyPIM is structured in three stages (upload, validation, execution). Errors are detected during the validation phase and reported in detail **before** data is written to the database.

When validation errors occur, proceed as follows:

1. **Review the error report**: After validation, you receive a detailed report listing each error with row, column, and error description.
2. **Correct the Excel file**: Fix the reported errors in your Excel file. Common causes include:
   - Missing required fields (e.g., SKU, product name)
   - Invalid data types (e.g., text instead of number)
   - Unknown references (e.g., attribute name does not exist)
   - Duplicate entries within the same file
3. **Re-upload**: Upload the corrected file again and start the validation.
4. **Use the preview**: In the preview, you can see before execution which records will be created, updated, or skipped.

::: info Fuzzy Matching
The system automatically detects typos in references (e.g., attribute names, hierarchy nodes). If the similarity exceeds 85%, a correction suggestion is displayed.
:::

### What tabs does the Excel import file contain?

The import file consists of 14 worksheets (tabs) that are processed in a defined dependency order. A complete description can be found under [Excel Format](/en/import/excel-format). The tabs include, among others, languages, attribute groups, units, value lists, attributes, hierarchies, products, and product values.

## Export

### How do I configure export mappings?

Export mappings define how PIM data is transformed into the target format. For the Publixx export, configure the mappings as follows:

1. Navigate to **Export > Publixx Mappings**.
2. Create a new mapping or edit an existing one.
3. Define the source mapping for each target field:
   - **Source field** (`source`): The PIM attribute or system field
   - **Target field** (`target`): The field name in the Publixx record
   - **Type** (`type`): The mapping type (e.g., `text`, `unit_value`, `media_url`, `price`)
4. Save the mapping.

Mappings can also be managed via the API. Details can be found under [Publixx Export](/en/export/publixx-export).

### What is the difference between full export and delta export?

- **Full export**: Exports all products that match the specified filter criteria. Suitable for initial data transfers or complete synchronizations.
- **Delta export**: Exports only products that have been changed since a specific point in time. Use the `updated_after` filter with an ISO 8601 timestamp for this purpose. Suitable for regular incremental updates.

```
GET /api/v1/export/products?updated_after=2025-01-15T08:00:00Z
```

## Search and PQL

### What is the difference between regular search and PQL?

The **regular search** is a simple free-text field that searches product names and SKUs. It is suitable for quickly finding known products.

**PQL (Product Query Language)** is an SQL-like query language that allows you to formulate arbitrarily complex filter criteria across all product attributes. PQL supports:

- Comparison operators (`=`, `>`, `<`, `LIKE`, `IN`, `BETWEEN`)
- Logical operators (`AND`, `OR`, `NOT`)
- Fuzzy search (`FUZZY` with configurable threshold)
- Phonetic search (`SOUNDS_LIKE` with Cologne phonetics for German text)
- Weighted full-text search (`SEARCH_FIELDS` with boost factors)
- Sorting by relevance (`ORDER BY SCORE`)

Example of a PQL query:

```sql
SELECT sku, name, preis FROM products
WHERE kategorie = 'Elektrowerkzeuge'
  AND preis BETWEEN 50 AND 200
  AND FUZZY(name, 'Bohrmaschine', 0.8)
ORDER BY SCORE
```

Detailed documentation can be found under [PQL](/en/api/pql).

## System and Administration

### How do I best secure the system?

A reliable backup strategy includes three components:

**1. Database backup (daily):**

```bash
mysqldump -u pim_user -p publixx_pim | gzip > /backup/pim_$(date +%Y%m%d).sql.gz
```

**2. Media backup (daily):**

```bash
rsync -avz /var/www/publixx-pim/storage/app/public/ /backup/media/
```

**3. Configuration (after changes):**

```bash
cp /var/www/publixx-pim/.env /backup/env_$(date +%Y%m%d).env
```

Automate the backups via cron jobs and retain at least the backups from the last 30 days. Test the restoration process regularly.

### How can I optimize performance?

The following measures noticeably improve system performance:

**Enable caching:**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

**Optimize database:**

```bash
php artisan db:optimize  # Update indexes and statistics
```

**Check Redis cache:**
Ensure that Redis has sufficient memory and that the `maxmemory` setting is appropriate. Monitor `evicted_keys` -- a high value indicates insufficient memory.

**Enable PHP OPcache:**
Ensure that OPcache is enabled in the production environment (see [Deployment](/en/installation/deployment)).

**Optimize images:**
Upload images at an appropriate resolution. The system generates thumbnails automatically, but the original files affect storage consumption.

**Queue processing:**
Ensure that Laravel Horizon is configured with sufficient workers so that import and export jobs are processed in a timely manner.

### How do I add a new language?

anyPIM supports any number of content languages for product data. Here is how to add a new language:

1. **Create the language via import**: Add the language in the `01_Sprachen` tab of the Excel import file (ISO 639-1 code and label).
2. **Alternatively via the API**:
   ```bash
   curl -X POST /api/v1/languages \
     -H "Authorization: Bearer {token}" \
     -d '{"code": "fr", "name_de": "Französisch", "name_en": "French"}'
   ```
3. **Maintain translatable attributes**: Navigate to the products and maintain the attribute values in the new language. Only attributes configured as `translatable` support multilingual values.

::: info Note
The PIM's interface language (German, English) is independent of the content languages of product data. New interface languages require an adjustment of the frontend translation files.
:::

### Where do I find the log files for troubleshooting?

The most important log files for troubleshooting:

| File | Contents |
|---|---|
| `storage/logs/laravel.log` | Application errors, warnings, and debug information |
| `storage/logs/horizon.log` | Queue worker logs (import, export) |
| `/var/log/nginx/pim-error.log` | Web server errors |
| `/var/log/mysql/error.log` | Database errors |

For real-time monitoring, use:

```bash
tail -f storage/logs/laravel.log
```

### Can I run the system in a Docker environment?

anyPIM is primarily designed and documented for native operation on a Linux server. A Docker setup is possible in principle but is not officially provided at this time. When containerizing, you need to account for the following services as separate containers or services:

- PHP-FPM (with all required extensions)
- Nginx
- MySQL 8.0+
- Redis
- Supervisor/Horizon (as a separate worker container)

### Under what license is anyPIM released?

anyPIM is released under the **AGPL-3.0-only** license. This permits the free use, modification, and distribution of the software. If you make the software available (including modified versions) over a network, you must make the source code available under the same license. All third-party components used and their licenses can be found in the `THIRD-PARTY-NOTICES` file in the project directory.

## More Questions?

If your question was not answered here, consult the detailed sections of the documentation:

- [Installation and Deployment](/en/installation/)
- [Import Documentation](/en/import/)
- [Export Documentation](/en/export/)
- [API Reference](/en/api/)
- [PQL Query Language](/en/api/pql)

Via the **Help** menu item in the sidebar, you can access this documentation at any time.
