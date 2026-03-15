# anyPIM — Data Model

> **Purpose:** Complete entity-relationship model. Use this skill when creating migrations, models, seeders, API resources, or when answering questions about the schema.

---

## Stack Context

- **DB:** MySQL 8.0+ (InnoDB, JSON columns, CTEs, FULLTEXT)
- **Backend:** PHP 8.3, Laravel 11
- **PKs:** UUID (CHAR(36)) everywhere
- **Timestamps:** created_at / updated_at on every entity

---

## Entity Overview (35 Tables)

| Area | Entities |
|------|----------|
| Attribute Model (10) | Attribute, AttributeType, UnitGroup, Unit, ValueList, ValueListEntry, AttributeView, AttributeViewAssignment, ComparisonOperatorGroup, ComparisonOperator |
| Product Model (6) | Product, ProductType, ProductAttributeValue, VariantInheritanceRule, ProductRelationType, ProductRelation |
| Hierarchy Model (4) | Hierarchy, HierarchyNode, HierarchyNodeAttributeAssignment, OutputHierarchyProductAssignment |
| Media Model (2) | Media, ProductMediaAssignment |
| Price Model (2) | PriceType, ProductPrice |
| Export & PXF (2) | PublixxExportMapping, PxfTemplate |
| Import (2) | ImportJob, ImportJobError |
| User Management (5) | User, Role, Permission, RolePermission, UserRole |
| Performance (1) | products_search_index |
| System (1) | AuditLog |

---

## Attribute Model

### attributes

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | CHAR(36) PK | No | UUID |
| technical_name | VARCHAR(100) UNIQUE | No | e.g. `product-weight-num` |
| name_de | VARCHAR(255) | No | Display name German |
| name_en | VARCHAR(255) | Yes | Display name English |
| name_json | JSON | Yes | Additional languages `{"fr":"Poids"}` |
| description_de | TEXT | Yes | Description |
| description_en | TEXT | Yes | |
| data_type | ENUM('String','Number','Float','Date','Flag','Selection','Dictionary','Collection') | No | |
| attribute_type_id | FK → attribute_types.id | Yes | Attribute group |
| value_list_id | FK → value_lists.id | Yes | For Selection/Dictionary |
| unit_group_id | FK → unit_groups.id | Yes | For numeric attributes |
| default_unit_id | FK → units.id | Yes | Default unit |
| comparison_operator_group_id | FK → comparison_operator_groups.id | Yes | |
| is_translatable | BOOLEAN | No | Values require translation |
| is_multipliable | BOOLEAN | No | Multipliable |
| max_multiplied | INT | Yes | Max. multiplications |
| max_pre_decimal | INT | Yes | Pre-decimal digits |
| max_post_decimal | INT | Yes | Post-decimal digits |
| max_characters | INT | Yes | Max. character length |
| is_searchable | BOOLEAN DEFAULT true | No | Included in search |
| is_mandatory | BOOLEAN DEFAULT false | No | Required field |
| is_unique | BOOLEAN DEFAULT false | No | System-wide unique value |
| is_country_specific | BOOLEAN DEFAULT false | No | Country-specific |
| is_inheritable | BOOLEAN DEFAULT true | No | Inheritable via hierarchy |
| parent_attribute_id | FK → attributes.id | Yes | Hierarchical attribute (parent) |
| position | INT | Yes | Sorting |
| source_system | VARCHAR(50) | Yes | PIM / SAP ERP / Other |
| source_attribute_name | VARCHAR(255) | Yes | Name in source system |
| source_attribute_key | VARCHAR(255) | Yes | Key in source system |
| status | ENUM('active','inactive') DEFAULT 'active' | No | |

### attribute_types

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | e.g. `technical_attributes` |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |
| sort_order | INT | |

### unit_groups

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | e.g. `weight`, `length` |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |

### units

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| unit_group_id | FK → unit_groups.id | |
| technical_name | VARCHAR(100) | e.g. `kilogram` |
| abbreviation | VARCHAR(20) | e.g. `kg` |
| abbreviation_json | JSON nullable | Translation: `{"de":"Stk.","en":"pcs."}` |
| conversion_factor | DECIMAL(20,10) DEFAULT 1 | Factor to base unit |
| is_base_unit | BOOLEAN | |
| is_translatable | BOOLEAN | Abbreviation requires translation |

### value_lists

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |
| value_data_type | ENUM('String','Number') DEFAULT 'String' | |
| max_depth | INT DEFAULT 1 | Max. nesting depth |

### value_list_entries

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| value_list_id | FK → value_lists.id | |
| parent_entry_id | FK → value_list_entries.id nullable | Hierarchical |
| technical_name | VARCHAR(100) | e.g. `red` |
| display_value_de | VARCHAR(255) | e.g. `Rot` |
| display_value_en | VARCHAR(255) nullable | e.g. `Red` |
| display_value_json | JSON nullable | `{"fr":"Rouge"}` |
| sort_order | INT | |
| is_active | BOOLEAN DEFAULT true | |
| UNIQUE(value_list_id, technical_name) | | |

### attribute_views

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | e.g. `eshop_view` |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |
| sort_order | INT | |
| is_write_protected | BOOLEAN DEFAULT false | |

### attribute_view_assignments

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| attribute_id | FK → attributes.id | |
| attribute_view_id | FK → attribute_views.id | |
| UNIQUE(attribute_id, attribute_view_id) | | |

### comparison_operator_groups / comparison_operators

```
comparison_operator_groups: id, technical_name, name_de, name_en
comparison_operators: id, group_id (FK), technical_name, symbol, description_de
```

---

## Product Model

### product_types

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| technical_name | VARCHAR(100) UNIQUE | `physical_product`, `training`, `service`, `software`, `bundle`, `digital_asset` |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| description | TEXT nullable | |
| icon | VARCHAR(50) nullable | Lucide icon name |
| color | VARCHAR(7) nullable | #hex |
| has_variants | BOOLEAN | |
| has_ean | BOOLEAN | |
| has_prices | BOOLEAN | |
| has_media | BOOLEAN | |
| has_stock | BOOLEAN | |
| has_physical_dimensions | BOOLEAN | |
| default_attribute_groups | JSON nullable | Auto-assigned groups |
| allowed_relation_types | JSON nullable | |
| validation_rules | JSON nullable | |
| sort_order | INT | |
| is_active | BOOLEAN DEFAULT true | |

### products

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| product_type_id | FK → product_types.id NOT NULL | |
| sku | VARCHAR(100) UNIQUE | Article number |
| ean | VARCHAR(20) nullable | EAN / GTIN |
| name | VARCHAR(500) | Product name (primary language) |
| status | ENUM('draft','active','inactive','discontinued') DEFAULT 'draft' | |
| product_type_ref | ENUM('product','variant') DEFAULT 'product' | Product or variant |
| parent_product_id | FK → products.id nullable | For variants: parent product |
| master_hierarchy_node_id | FK → hierarchy_nodes.id nullable | Single assignment master |
| created_by | FK → users.id nullable | |
| updated_by | FK → users.id nullable | |
| INDEX(status), INDEX(sku), INDEX(ean), INDEX(master_hierarchy_node_id), FULLTEXT(name) | | |

### product_attribute_values

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| product_id | FK → products.id | |
| attribute_id | FK → attributes.id | |
| value_string | TEXT nullable | For String/Selection |
| value_number | DECIMAL(20,6) nullable | For Number/Float |
| value_date | DATE nullable | For Date |
| value_flag | BOOLEAN nullable | For Flag |
| value_selection_id | FK → value_list_entries.id nullable | For Selection/Dictionary |
| unit_id | FK → units.id nullable | Selected unit |
| comparison_operator_id | FK → comparison_operators.id nullable | |
| language | VARCHAR(5) nullable | NULL=language-independent, 'de'/'en'/etc. for translated values |
| multiplied_index | INT DEFAULT 0 | For multipliable attributes: 0,1,2,... |
| is_inherited | BOOLEAN DEFAULT false | Value comes from hierarchy |
| inherited_from_node_id | FK → hierarchy_nodes.id nullable | |
| inherited_from_product_id | FK → products.id nullable | For variants |
| UNIQUE(product_id, attribute_id, language, multiplied_index) | | |
| INDEX(product_id, attribute_id) | | |
| INDEX(attribute_id, value_string(100)) | | |

### variant_inheritance_rules

```
id, product_id (FK → products), attribute_id (FK → attributes),
inheritance_mode ENUM('inherit','override')
UNIQUE(product_id, attribute_id)
```

### product_relation_types

```
id, technical_name UNIQUE, name_de, name_en, name_json, is_bidirectional BOOLEAN
```

### product_relations

```
id, source_product_id (FK), target_product_id (FK), relation_type_id (FK), sort_order
UNIQUE(source_product_id, target_product_id, relation_type_id)
```

---

## Hierarchy Model

### hierarchies

```
id, technical_name UNIQUE, name_de, name_en, name_json,
hierarchy_type ENUM('master','output'), description
```

### hierarchy_nodes

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| hierarchy_id | FK → hierarchies.id | |
| parent_node_id | FK → hierarchy_nodes.id nullable | |
| name_de | VARCHAR(255) | |
| name_en | VARCHAR(255) nullable | |
| name_json | JSON nullable | |
| path | VARCHAR(1000) | Materialized Path: `/node-1/node-2/` |
| depth | INT | Depth in tree (0 = Root) |
| sort_order | INT | |
| is_active | BOOLEAN DEFAULT true | |
| INDEX(hierarchy_id, parent_node_id) | | |
| INDEX(path) | | |

### hierarchy_node_attribute_assignments

| Field | Type | Description |
|-------|------|-------------|
| id | CHAR(36) PK | |
| hierarchy_node_id | FK → hierarchy_nodes.id | |
| attribute_id | FK → attributes.id | |
| collection_name | VARCHAR(255) nullable | Group heading in UI |
| collection_sort | INT DEFAULT 0 | Order of collection (10,20,30) |
| attribute_sort | INT DEFAULT 0 | Order within collection |
| dont_inherit | BOOLEAN DEFAULT false | Suppress inheritance to children |
| access_hierarchy | ENUM('hidden','visible','editable') DEFAULT 'visible' | |
| access_product | ENUM('hidden','visible','editable') DEFAULT 'editable' | |
| access_variant | ENUM('hidden','visible','editable') DEFAULT 'editable' | |

### output_hierarchy_product_assignments

```
id, hierarchy_node_id (FK), product_id (FK), sort_order
UNIQUE(hierarchy_node_id, product_id)
```

---

## Media Model

### media

```
id, file_name, file_path, mime_type, file_size BIGINT,
media_type ENUM('image','document','video','other'),
title_de, title_en, description_de, description_en,
alt_text_de, alt_text_en, width INT, height INT,
created_at, updated_at
```

### product_media_assignments

```
id, product_id (FK), media_id (FK), usage_type ENUM('teaser','gallery','document','technical_drawing'),
sort_order, is_primary BOOLEAN DEFAULT false
```

---

## Price Model

### price_types

```
id, technical_name UNIQUE, name_de, name_en, name_json
```

### product_prices

```
id, product_id (FK), price_type_id (FK), amount DECIMAL(12,2),
currency VARCHAR(3) (ISO 4217), valid_from DATE, valid_to DATE nullable,
country VARCHAR(2) nullable (ISO 3166-1),
scale_from INT nullable, scale_to INT nullable,
created_at, updated_at
```

---

## Export & PXF

### publixx_export_mappings

```
id, name VARCHAR(255), attribute_view_id (FK nullable), output_hierarchy_id (FK nullable),
mapping_rules JSON, include_media BOOLEAN, include_prices BOOLEAN,
include_variants BOOLEAN, include_relations BOOLEAN,
languages JSON (["de","en"]), flatten_mode ENUM('flat','nested','publixx')
```

### pxf_templates

```
id, name VARCHAR(255), description TEXT nullable, pxf_data JSON (LONGTEXT),
version VARCHAR(10), orientation ENUM('a4hoch','a4quer','custom'),
product_type_id (FK nullable), export_mapping_id (FK nullable),
thumbnail VARCHAR(500) nullable, is_default BOOLEAN, is_active BOOLEAN,
created_at, updated_at
```

---

## Import

### import_jobs

```
id, user_id (FK), file_name, file_path, status ENUM('uploaded','validating','validated','executing','completed','failed'),
sheets_found JSON, summary JSON, result JSON,
started_at DATETIME nullable, completed_at DATETIME nullable, created_at
```

### import_job_errors

```
id, import_job_id (FK), sheet VARCHAR(100), row INT, column VARCHAR(5),
field VARCHAR(100), value TEXT, error TEXT, suggestion TEXT nullable
```

---

## User Management (Spatie Permission)

### users

```
id, name, email UNIQUE, password (bcrypt), language VARCHAR(5) DEFAULT 'de',
is_active BOOLEAN DEFAULT true, last_login_at DATETIME nullable, created_at, updated_at
```

### roles, permissions, role_has_permissions, model_has_roles

Standard Spatie Permission schema.

---

## Performance

### products_search_index

```
product_id CHAR(36) PK, sku, ean, product_type, status,
name_de VARCHAR(500), name_en VARCHAR(500), description_de TEXT,
hierarchy_path VARCHAR(1000), primary_image VARCHAR(500),
list_price DECIMAL(12,2), attribute_completeness TINYINT,
phonetic_name_de VARCHAR(100), updated_at TIMESTAMP,
FULLTEXT(name_de, name_en), FULLTEXT(description_de),
INDEX(status), INDEX(product_type), INDEX(sku), INDEX(list_price)
```

---

## System

### audit_logs

```
id, user_id (FK), auditable_type VARCHAR(100), auditable_id CHAR(36),
action ENUM('created','updated','deleted'),
old_values JSON nullable, new_values JSON nullable,
ip_address VARCHAR(45), user_agent TEXT, created_at
INDEX(auditable_type, auditable_id), INDEX(user_id), INDEX(created_at)
```

---

## Inheritance Concept

### Hierarchy Inheritance (Order)
1. Attributes are inherited from the root node through intermediate nodes down to the leaf
2. `dont_inherit = true` breaks the inheritance chain
3. Sorting: `collection_sort` (groups in steps of 10) → `attribute_sort` (within group)
4. A product inherits all attributes from its `master_hierarchy_node_id` and all ancestors

### Variant Inheritance (Resolution Order)
1. Own value on the product (override)
2. Value from the parent product (inherit, controlled via `variant_inheritance_rules`)
3. Value from hierarchy
4. Empty
