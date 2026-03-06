---
title: Services and Events
---

# Services and Events

The service layer forms the core of the business logic in anyPIM. All operations that go beyond simple CRUD operations are encapsulated in dedicated services. Controllers delegate to services, services orchestrate models and trigger events.

## Service Architecture

### Design Principles

Services in anyPIM follow consistent architectural principles:

1. **Single Responsibility**: Each service covers a clearly defined functional area
2. **Dependency Injection**: Services receive their dependencies via constructor injection and are resolved through the Laravel Service Container
3. **Transactional Integrity**: Operations that affect multiple tables are wrapped in database transactions
4. **Event Emission**: After successful completion of an operation, the service triggers the corresponding events
5. **Cache Awareness**: Services selectively invalidate affected caches via tags

### Call Hierarchy

```
Controller
  └─> FormRequest (Validation)
  └─> Service (Business Logic)
        └─> Model (Data Access)
        └─> Event (Notification)
              └─> Listener (Reaction)
                    └─> Queue Job (asynchronous)
```

---

## Core Services

### ExportService

The `ExportService` coordinates the entire export process from template evaluation to file generation.

**Responsibilities:**
- Loading and interpreting PXF export templates
- Resolving attribute mappings (source attribute to target field name)
- Aggregating product data according to template configuration
- Applying transformation rules (formatting, unit conversion, text truncation)
- Generating the output file (JSON for Publixx integration)
- Logging the export process

**Interaction with other services:**
- Uses the `InheritanceService` to obtain fully resolved attribute values (incl. inherited values)
- Uses the `PqlService` for optional filtering of products to be exported

```php
class ExportService
{
    public function execute(ExportTemplate $template, array $options): ExportResult
    {
        // 1. Load products according to template scope
        // 2. Resolve attribute values incl. inheritance
        // 3. Apply mappings
        // 4. Execute transformations
        // 5. Generate output
        // 6. Trigger ExportCompleted event
    }
}
```

### ImportService

The `ImportService` controls the three-phase import process and is one of the most complex services in the system.

**Phase 1 -- Parsing:**
- Reading the Excel file with 14 worksheets
- Structure recognition per worksheet (header row, data rows)
- Creation of a structured import model in memory

**Phase 2 -- Validation and Mapping:**
- Matching column names against existing attributes (exact and via fuzzy matching)
- Validating data values against attribute types and rules
- Detection of references (hierarchy paths, parent products, media)
- Creation of a validation report with warnings and errors

**Phase 3 -- Execution:**
- Transactional import of all validated data
- Creation or update of products, values, and assignments
- Triggering of corresponding events for cache invalidation and index updates

**Error Handling:**
Errors are not treated as aborts but stored as a structured log in JSON format in the `import_jobs` table. After the import, the user receives a detailed report with row and column references.

### InheritanceService

The `InheritanceService` is responsible for resolving attribute values taking into account the two-level inheritance.

**Responsibilities:**
- Resolving hierarchy inheritance (attributes from parent nodes)
- Resolving variant inheritance (values from the parent product)
- Determining the correct resolution order
- Considering the `dont_inherit` flag for node-specific attributes
- Providing metadata about the origin of each value (own value, inherited from variant, inherited from hierarchy)

```php
class InheritanceService
{
    public function resolveValue(
        Product $product,
        Attribute $attribute,
        string $locale
    ): ResolvedValue {
        // 1. Check for own value
        // 2. Check variant inheritance rule (if variant)
        // 3. Load parent value (if inherit rule is active)
        // 4. Check hierarchy default value
        // 5. Return ResolvedValue with origin information
    }
}
```

Detailed documentation of the inheritance logic can be found under [Inheritance](/en/architecture/inheritance).

### PqlService

The `PqlService` translates PQL queries into optimized SQL queries.

**Processing Steps:**
1. **Lexing**: Tokenization of the PQL string into tokens (operators, values, attribute names)
2. **Parsing**: Construction of an abstract syntax tree (AST) from the tokens
3. **Validation**: Checking whether the referenced attributes exist and the operators are valid for the respective attribute type
4. **SQL Generation**: Translation of the AST into a SQL query against the `products_search_index`
5. **Execution**: Running the query and returning the result set

**Special Operators:**
- `FUZZY` is translated into a combination of `SOUNDEX()` and Levenshtein distance calculation
- `SOUNDS_LIKE` uses MySQL's native `SOUNDEX()` function
- `SEARCH_FIELDS` is translated into a `MATCH ... AGAINST` query over the FULLTEXT index

### PreviewService

The `PreviewService` generates preview representations of products for the frontend display.

**Responsibilities:**
- Assembling all visible attribute values taking into account user permissions (attribute view)
- Marking inherited values with origin information
- Preparing media thumbnails
- Considering the active language and fallback logic

### ProductVersioningService

The `ProductVersioningService` manages the change history of products.

**Responsibilities:**
- Creating version snapshots before changes
- Comparing between versions (diff calculation)
- Providing version history for the user interface
- Optional restoration of previous versions

---

## Event System

anyPIM makes extensive use of the Laravel event system to enable loosely coupled reactions to business events. Events are triggered synchronously, but the associated listeners can dispatch asynchronous queue jobs.

### Core Events

| Event | Triggered by | Description |
|---|---|---|
| `ProductCreated` | ProductService | New product was created |
| `ProductUpdated` | ProductService | Product master data was changed |
| `ProductDeleted` | ProductService | Product was deleted |
| `ProductValueChanged` | ProductService | An attribute value was set or changed |
| `VariantInheritanceChanged` | InheritanceService | Inheritance rule of a variant was changed |
| `HierarchyNodeMoved` | HierarchyService | A node was moved in the hierarchy |
| `HierarchyAttributeAssigned` | HierarchyService | An attribute was assigned to a node |
| `ImportCompleted` | ImportService | An import process was completed |
| `ExportCompleted` | ExportService | An export process was completed |
| `SearchIndexStale` | various | A search index entry needs to be updated |

### Event-Listener Mappings

```
ProductValueChanged
  ├─> InvalidateProductCacheListener      (sync)
  ├─> UpdateSearchIndexListener           (async, Queue)
  └─> PropagateToVariantsListener         (async, Queue)

HierarchyNodeMoved
  ├─> RecalculateInheritedAttributesListener  (async, Queue)
  ├─> InvalidateHierarchyCacheListener        (sync)
  └─> UpdateAffectedSearchIndexListener       (async, Queue)

VariantInheritanceChanged
  ├─> RecalculateVariantValuesListener    (async, Queue)
  └─> InvalidateVariantCacheListener      (sync)
```

---

## Cache Invalidation

Cache invalidation follows a **tag-based system**. Each cache entry is tagged with one or more tags that describe its functional affiliation.

### Tag Conventions

| Tag Pattern | Example | Description |
|---|---|---|
| `product:{id}` | `product:550e8400-...` | All caches of a specific product |
| `products` | `products` | All product-related caches (lists, overviews) |
| `hierarchy:{id}` | `hierarchy:a1b2c3d4-...` | All caches of a hierarchy |
| `node:{id}` | `node:x9y8z7w6-...` | All caches of a hierarchy node |
| `attributes` | `attributes` | All attribute-related caches |
| `search_index` | `search_index` | Search index-related caches |

### Invalidation Cascades

Certain changes trigger cascading invalidations:

1. **Attribute value change on parent product**: Invalidates the cache of the parent product and all variants that inherit the value
2. **Hierarchy node move**: Invalidates all caches of the affected nodes and their child products
3. **Attribute definition change**: Invalidates all product-related caches, as validation or rendering may change

---

## Queue Jobs and Background Processing

Asynchronous jobs are processed via Redis queues and monitored by Laravel Horizon.

### Job Categories

**High priority (Queue: `high`):**
- `UpdateSearchIndexJob` -- Updating individual search index entries
- `PropagateInheritedValueJob` -- Propagating changed values to variants
- `InvalidateCascadingCacheJob` -- Cascading cache invalidation

**Normal priority (Queue: `default`):**
- `ProcessImportJob` -- Processing an Excel import
- `GenerateExportJob` -- Creating an export file
- `RecalculateInheritedAttributesJob` -- Recalculation after hierarchy changes

**Low priority (Queue: `low`):**
- `GenerateMediaThumbnailJob` -- Thumbnail generation for uploaded media
- `CleanupExpiredExportsJob` -- Cleanup of expired export files
- `WarmCacheJob` -- Proactive cache warming after invalidation

### Horizon Configuration

Horizon distributes workers across queues by priority:

```php
'environments' => [
    'production' => [
        'supervisor-high' => [
            'queue' => ['high'],
            'processes' => 4,
            'tries' => 3,
            'timeout' => 120,
        ],
        'supervisor-default' => [
            'queue' => ['default'],
            'processes' => 2,
            'tries' => 3,
            'timeout' => 600,
        ],
        'supervisor-low' => [
            'queue' => ['low'],
            'processes' => 1,
            'tries' => 1,
            'timeout' => 300,
        ],
    ],
],
```

### Retry Strategy

Failed jobs are automatically retried. The retry logic distinguishes between transient errors (database connection interrupted, Redis unreachable) and business errors (invalid data). Transient errors are retried up to three times with exponential backoff. Business errors are immediately marked as permanently failed and displayed in the Horizon dashboard.
